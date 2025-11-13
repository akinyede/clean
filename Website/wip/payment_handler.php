<?php
/**
 * payment_handler.php
 *
 * QuickBooks Online invoicing + payment synchronization endpoint.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create-invoice':
        handleCreateInvoice();
        break;
    case 'invoice-status':
        handleInvoiceStatus();
        break;
    case 'webhook':
        handleWebhook();
        break;
    default:
        json_response(['success' => false, 'error' => 'Invalid action'], 400);
}

function handleCreateInvoice(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $bookingId = trim((string) ($payload['bookingId'] ?? ''));
    $amount = isset($payload['amount']) ? (float) $payload['amount'] : 0.0;
    $dueDate = isset($payload['dueDate']) ? trim((string) $payload['dueDate']) : null;

    if ($bookingId === '' || $amount <= 0) {
        json_response(['success' => false, 'error' => 'Booking ID and a positive amount are required'], 400);
    }

    $booking = getBooking($bookingId);
    if (!$booking) {
        json_response(['success' => false, 'error' => 'Booking not found'], 404);
    }

    try {
        $client = quickbooks_client();
        if (!$client->isConfigured()) {
            json_response(['success' => false, 'error' => 'QuickBooks credentials are not configured'], 500);
        }

        $invoice = $client->createInvoice($booking, $amount, $dueDate);
        persistInvoice($booking, $invoice);

        json_response([
            'success' => true,
            'invoice' => [
                'id' => $invoice['Id'] ?? null,
                'docNumber' => $invoice['DocNumber'] ?? null,
                'dueDate' => $invoice['DueDate'] ?? null,
                'balance' => $invoice['Balance'] ?? null,
                'total' => $invoice['TotalAmt'] ?? null,
            ],
        ]);
    } catch (Throwable $exception) {
        getLogger()->error('QuickBooks invoice creation failed', [
            'booking_id' => $bookingId,
            'error' => $exception->getMessage(),
        ]);

        json_response([
            'success' => false,
            'error' => 'Failed to create QuickBooks invoice: ' . $exception->getMessage(),
        ], 500);
    }
}

function handleInvoiceStatus(): void
{
    $bookingId = trim((string) ($_GET['bookingId'] ?? ''));
    if ($bookingId === '') {
        json_response(['success' => false, 'error' => 'bookingId is required'], 400);
    }

    $booking = getBooking($bookingId);
    if (!$booking) {
        json_response(['success' => false, 'error' => 'Booking not found'], 404);
    }

    $invoiceId = $booking['quickbooks_invoice_id'] ?? null;
    if (!$invoiceId) {
        json_response([
            'success' => true,
            'invoice' => null,
            'paymentStatus' => $booking['payment_status'] ?? 'unpaid',
        ]);
    }

    try {
        $client = quickbooks_client();
        $invoice = $client->getInvoice($invoiceId);
        $status = ((float) ($invoice['Balance'] ?? 0)) <= 0 ? 'paid' : 'unpaid';

        updateBookingInvoiceMeta($bookingId, $invoiceId, $status);
        upsertInvoiceRecord($booking, $invoice);

        json_response([
            'success' => true,
            'invoice' => [
                'id' => $invoice['Id'],
                'docNumber' => $invoice['DocNumber'] ?? null,
                'status' => $status,
                'dueDate' => $invoice['DueDate'] ?? null,
                'balance' => $invoice['Balance'] ?? null,
                'total' => $invoice['TotalAmt'] ?? null,
            ],
            'paymentStatus' => $status,
        ]);
    } catch (Throwable $exception) {
        json_response([
            'success' => false,
            'error' => 'Unable to fetch invoice status: ' . $exception->getMessage(),
        ], 500);
    }
}

function handleWebhook(): void
{
    $payload = file_get_contents('php://input') ?: '';
    if ($payload === '') {
        json_response(['success' => false, 'error' => 'Empty payload'], 400);
    }

    $signature = $_SERVER['HTTP_INTUIT_SIGNATURE'] ?? '';
    $client = quickbooks_client();

    if (!$client->verifyWebhookSignature($payload, $signature)) {
        json_response(['success' => false, 'error' => 'Invalid webhook signature'], 400);
    }

    $data = json_decode($payload, true);
    if (!$data || empty($data['eventNotifications'])) {
        json_response(['success' => false, 'error' => 'Malformed webhook payload'], 400);
    }

    foreach ($data['eventNotifications'] as $notification) {
        $entities = $notification['dataChangeEvent']['entities'] ?? [];
        foreach ($entities as $entity) {
            $name = $entity['name'] ?? '';
            $id = $entity['id'] ?? '';

            if ($name === 'Invoice' && $id !== '') {
                processInvoiceEvent($id);
            } elseif ($name === 'Payment' && $id !== '') {
                processPaymentEvent($id);
            }
        }
    }

    json_response(['success' => true]);
}

function processInvoiceEvent(string $invoiceId): void
{
    try {
        $client = quickbooks_client();
        $invoice = $client->getInvoice($invoiceId);
        $bookingId = $invoice['DocNumber'] ?? null;
        if (!$bookingId) {
            return;
        }

        $booking = getBooking($bookingId);
        if (!$booking) {
            return;
        }

        $status = ((float) ($invoice['Balance'] ?? 0)) <= 0 ? 'paid' : 'unpaid';
        updateBookingInvoiceMeta($bookingId, $invoiceId, $status);
        upsertInvoiceRecord($booking, $invoice);

        if ($status === 'paid') {
            sendPaymentConfirmationEmail($booking, $invoice);
        }
    } catch (Throwable $exception) {
        getLogger()->error('Failed to process QuickBooks invoice webhook', [
            'invoice_id' => $invoiceId,
            'error' => $exception->getMessage(),
        ]);
    }
}

function processPaymentEvent(string $paymentId): void
{
    try {
        $client = quickbooks_client();
        $payment = $client->getPayment($paymentId);
        $linked = $payment['Line'][0]['LinkedTxn'][0]['TxnId'] ?? null;
        if (!$linked) {
            return;
        }

        $invoice = $client->getInvoice($linked);
        $bookingId = $invoice['DocNumber'] ?? null;
        if (!$bookingId) {
            return;
        }

        $booking = getBooking($bookingId);
        if (!$booking) {
            return;
        }

        recordPayment(
            $bookingId,
            $paymentId,
            $linked,
            (float) ($payment['TotalAmt'] ?? 0),
            'paid',
            'quickbooks',
            null
        );

        updateBookingInvoiceMeta($bookingId, $linked, 'paid');
        upsertInvoiceRecord($booking, $invoice);
        sendPaymentConfirmationEmail($booking, $invoice, $payment);
    } catch (Throwable $exception) {
        getLogger()->error('Failed to process QuickBooks payment webhook', [
            'payment_id' => $paymentId,
            'error' => $exception->getMessage(),
        ]);
    }
}

function persistInvoice(array $booking, array $invoice): void
{
    updateBookingInvoiceMeta(
        $booking['booking_id'],
        $invoice['Id'] ?? null,
        ((float) ($invoice['Balance'] ?? 0)) <= 0 ? 'paid' : 'unpaid'
    );

    upsertInvoiceRecord($booking, $invoice);
}

function updateBookingInvoiceMeta(string $bookingId, ?string $invoiceId, string $paymentStatus): void
{
    $conn = db();
    $stmt = $conn->prepare("
        UPDATE bookings
        SET quickbooks_invoice_id = ?, payment_status = ?
        WHERE booking_id = ?
    ");

    $stmt->bind_param('sss', $invoiceId, $paymentStatus, $bookingId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function upsertInvoiceRecord(array $booking, array $invoice): void
{
    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO invoices (
            invoice_number,
            quickbooks_invoice_id,
            booking_id,
            customer_id,
            issue_date,
            due_date,
            subtotal,
            tax,
            total,
            status,
            notes
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
        ON DUPLICATE KEY UPDATE
            issue_date = VALUES(issue_date),
            due_date = VALUES(due_date),
            subtotal = VALUES(subtotal),
            tax = VALUES(tax),
            total = VALUES(total),
            status = VALUES(status),
            notes = VALUES(notes),
            updated_at = NOW()
    ");

    $invoiceNumber = $invoice['DocNumber'] ?? $invoice['Id'] ?? '';
    $status = ((float) ($invoice['Balance'] ?? 0)) <= 0 ? 'paid' : 'sent';
    $tax = (float) ($invoice['TxnTaxDetail']['TotalTax'] ?? 0);
    $total = (float) ($invoice['TotalAmt'] ?? 0);
    $subtotal = $total - $tax;
    $notes = $invoice['PrivateNote'] ?? null;

    $types = 'sss' . 'i' . 'ss' . 'ddd' . 'ss';
    $stmt->bind_param(
        $types,
        $invoiceNumber,
        $invoice['Id'],
        $booking['booking_id'],
        $booking['customer_id'],
        $invoice['TxnDate'],
        $invoice['DueDate'],
        $subtotal,
        $tax,
        $total,
        $status,
        $notes
    );

    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function recordPayment(
    string $bookingId,
    string $paymentId,
    string $invoiceId,
    float $amount,
    string $status,
    string $method,
    ?string $receiptUrl
): void {
    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO payments (
            booking_id,
            quickbooks_payment_id,
            quickbooks_invoice_id,
            amount,
            currency,
            status,
            payment_method,
            receipt_url
        ) VALUES (
            ?, ?, ?, ?, 'USD', ?, ?, ?
        )
        ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            status = VALUES(status),
            payment_method = VALUES(payment_method),
            receipt_url = VALUES(receipt_url),
            updated_at = NOW()
    ");

    $types = str_repeat('s', 3) . 'd' . str_repeat('s', 3);
    $stmt->bind_param(
        $types,
        $bookingId,
        $paymentId,
        $invoiceId,
        $amount,
        $status,
        $method,
        $receiptUrl
    );

    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function sendPaymentConfirmationEmail(array $booking, array $invoice, ?array $payment = null): void
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, 'Wasatch Cleaners');
        $mail->addAddress($booking['email'], $booking['first_name'] . ' ' . $booking['last_name']);
        $mail->addReplyTo(COMPANY_EMAIL ?? SMTP_FROM_EMAIL, 'Wasatch Cleaners');

        $amount = isset($payment['TotalAmt'])
            ? (float) $payment['TotalAmt']
            : (float) ($invoice['TotalAmt'] ?? 0);

        $mail->isHTML(true);
        $mail->Subject = sprintf('Payment received - %s', $booking['booking_id']);
        $mail->Body = getPaymentEmailTemplate($booking, $invoice, $amount);
        $mail->AltBody = strip_tags($mail->Body);
        $mail->send();
    } catch (Exception $exception) {
        getLogger()->warning('Payment confirmation email failed', [
            'booking_id' => $booking['booking_id'],
            'error' => $exception->getMessage(),
        ]);
    }
}

function getPaymentEmailTemplate(array $booking, array $invoice, float $amount): string
{
    $formattedAmount = number_format($amount, 2);
    $invoiceNumber = $invoice['DocNumber'] ?? $invoice['Id'] ?? 'Invoice';
    $customerName = trim(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? ''));
    $serviceDate = trim(($booking['appointment_date'] ?? '') . ' at ' . ($booking['appointment_time'] ?? ''));
    $invoiceLink = sprintf(
        'https://app.qbo.intuit.com/app/invoice?txnId=%s',
        urlencode($invoice['Id'] ?? '')
    );
    $companyPhone = COMPANY_PHONE ?: '(385) 213-8900';

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <style>
        body { font-family: Arial, sans-serif; color: #111827; }
        .container { max-width: 640px; margin: 0 auto; padding: 24px; background: #f9fafb; border-radius: 12px; }
        .header { text-align: center; margin-bottom: 24px; }
        .amount { font-size: 32px; font-weight: 700; color: #059669; }
        .details { background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e5e7eb; }
        .details p { margin: 4px 0; }
        .btn { display: inline-block; background: #0d9488; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Confirmed</h1>
            <p>Thank you for your payment toward booking {$booking['booking_id']}.</p>
            <div class="amount">\${$formattedAmount}</div>
        </div>
        <div class="details">
            <p><strong>Invoice:</strong> {$invoiceNumber}</p>
            <p><strong>Customer:</strong> {$customerName}</p>
            <p><strong>Service Date:</strong> {$serviceDate}</p>
        </div>
        <a class="btn" href="{$invoiceLink}">View Invoice in QuickBooks</a>
        <p style="margin-top:16px;">If you have any questions, call us at {$companyPhone} or reply to this email.</p>
    </div>
</body>
</html>
HTML;
}

function getBooking(string $bookingId): ?array
{
    $conn = db();
    $stmt = $conn->prepare("
        SELECT *
        FROM bookings
        WHERE booking_id = ?
        LIMIT 1
    ");

    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc() ?: null;
    $stmt->close();
    $conn->close();

    return $booking;
}
