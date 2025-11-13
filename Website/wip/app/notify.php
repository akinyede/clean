<?php
/**
 * app/notify.php
 * 
 * Handles all booking-related notifications (emails, SMS, invoices)
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send booking confirmation email with invoice PDF
 */
function send_booking_confirmation_email(array $booking, string $bookingId, float $estimatedPrice): bool
{
    $logger = getLogger();
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings - Use constants from config.php
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, 'Wasatch Cleaners');
        $mail->addAddress($booking['email'], $booking['firstName'] . ' ' . $booking['lastName']);
        $mail->addReplyTo(COMPANY_EMAIL ?? SMTP_FROM_EMAIL, 'Wasatch Cleaners');
        
        // Generate and attach PDF invoice
        $pdfPath = generate_booking_invoice_pdf($booking, $bookingId, $estimatedPrice);
        if ($pdfPath && file_exists($pdfPath)) {
            $mail->addAttachment($pdfPath);
        }
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Booking Confirmation - $bookingId";
        $mail->Body = get_confirmation_email_html($booking, $bookingId, $estimatedPrice);
        $mail->AltBody = get_confirmation_email_text($booking, $bookingId, $estimatedPrice);
        
        $result = $mail->send();
        
        // Clean up temporary PDF
        if ($pdfPath && file_exists($pdfPath)) {
            @unlink($pdfPath);
        }
        
        // Log the email attempt
        log_email_sent($bookingId, $booking['email'], $mail->Subject, $result ? 'sent' : 'failed');
        
        if ($result) {
            $logger->info('Confirmation email sent successfully', ['booking_id' => $bookingId]);
        } else {
            $logger->warning('Failed to send confirmation email', ['booking_id' => $bookingId]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        $logger->error('Email sending exception', [
            'booking_id' => $bookingId,
            'error' => $e->getMessage()
        ]);
        
        log_email_sent($bookingId, $booking['email'], "Booking Confirmation - $bookingId", 'failed', $e->getMessage());
        
        return false;
    }
}

/**
 * Generate PDF invoice for booking
 */
function generate_booking_invoice_pdf(array $booking, string $bookingId, float $estimatedPrice): ?string 
{
    try {
        require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
        
        // TCPDF page settings
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        $pdf->SetCreator('Wasatch Cleaners');
        $pdf->SetAuthor('Wasatch Cleaners');
        $pdf->SetTitle('Invoice - ' . $bookingId);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $serviceNames = [
            'regular' => 'Regular Cleaning',
            'deep' => 'Deep Cleaning',
            'move' => 'Move In/Out Cleaning',
            'onetime' => 'One-Time Cleaning'
        ];

        $frequencyNames = [
            'weekly' => 'Weekly',
            'biweekly' => 'Bi-Weekly',
            'monthly' => 'Monthly',
            'onetime' => 'One-Time'
        ];

        $serviceName = $serviceNames[$booking['serviceType']] ?? $booking['serviceType'];
        $frequencyName = $frequencyNames[$booking['frequency']] ?? $booking['frequency'];
        
        // Format price for display
        $formattedPrice = number_format($estimatedPrice, 2);

        $html = <<<HTML
<style>
h1 { color: #14b8a6; font-size: 24px; }
h2 { color: #333; font-size: 18px; margin-top: 20px; }
.header { margin-bottom: 30px; }
.info-row { margin: 5px 0; }
.label { font-weight: bold; width: 150px; display: inline-block; }
.total { background-color: #f0f9ff; padding: 15px; margin-top: 20px; }
.footer { margin-top: 40px; font-size: 10px; color: #666; }
</style>

<div class="header">
    <h1>WASATCH CLEANERS</h1>
    <p>Professional Cleaning Services</p>
    <p>Phone: (385)213-8900 | Email: hello@wasatchcleaners.com</p>
</div>

<h2>INVOICE / BOOKING CONFIRMATION</h2>
<div class="info-row"><span class="label">Booking ID:</span> {$bookingId}</div>
<div class="info-row"><span class="label">Date:</span> {$booking['date']}</div>
<div class="info-row"><span class="label">Time:</span> {$booking['time']}</div>

<h2>CUSTOMER INFORMATION</h2>
<div class="info-row"><span class="label">Name:</span> {$booking['firstName']} {$booking['lastName']}</div>
<div class="info-row"><span class="label">Email:</span> {$booking['email']}</div>
<div class="info-row"><span class="label">Phone:</span> {$booking['phone']}</div>
<div class="info-row"><span class="label">Address:</span> {$booking['address']}, {$booking['city']}, {$booking['state']} {$booking['zip']}</div>

<h2>SERVICE DETAILS</h2>
<div class="info-row"><span class="label">Service:</span> {$serviceName}</div>
<div class="info-row"><span class="label">Frequency:</span> {$frequencyName}</div>
<div class="info-row"><span class="label">Property Type:</span> {$booking['propertyType']}</div>
<div class="info-row"><span class="label">Bedrooms:</span> {$booking['bedrooms']}</div>
<div class="info-row"><span class="label">Bathrooms:</span> {$booking['bathrooms']}</div>

<div class="total">
    <h2>ESTIMATED TOTAL: \${$formattedPrice}</h2>
    <p><em>Final price will be confirmed after property inspection</em></p>
</div>

<div class="footer">
    <p>Thank you for choosing Wasatch Cleaners! We look forward to serving you.</p>
    <p>Questions? Call us at (385)213-8900 or email hello@wasatchcleaners.com</p>
</div>
HTML;

        $pdf->writeHTML($html, true, false, true, false, '');

        // Ensure temp directory exists
        $tempDir = __DIR__ . '/../temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filename = $tempDir . '/invoice-' . $bookingId . '-' . time() . '.pdf';
        $pdf->Output($filename, 'F');

        return $filename;

    } catch (Exception $e) {
        $logger = getLogger();
        $logger->error('PDF generation failed', [
            'booking_id' => $bookingId,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

/**
 * Get HTML email template for booking confirmation
 */
function get_confirmation_email_html(array $booking, string $bookingId, float $estimatedPrice): string
{
    $serviceNames = [
        'regular' => 'Regular Cleaning',
        'deep' => 'Deep Cleaning',
        'move' => 'Move In/Out Cleaning',
        'onetime' => 'One-Time Cleaning'
    ];
    
    $serviceName = $serviceNames[$booking['serviceType']] ?? $booking['serviceType'];
    $formattedPrice = number_format($estimatedPrice, 2);
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #14b8a6, #0d9488); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; }
        .info-box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #14b8a6; }
        .button { display: inline-block; background: #fb7185; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Booking Confirmed! ✓</h1>
            <p>Your cleaning service has been scheduled</p>
        </div>
        
        <div class="content">
            <p>Hi {$booking['firstName']},</p>
            
            <p>Thank you for choosing Wasatch Cleaners! We're excited to help you maintain a spotless space.</p>
            
            <div class="info-box">
                <h2 style="margin-top: 0; color: #14b8a6;">Booking Details</h2>
                <p><strong>Booking ID:</strong> {$bookingId}</p>
                <p><strong>Service:</strong> {$serviceName}</p>
                <p><strong>Date:</strong> {$booking['date']}</p>
                <p><strong>Time:</strong> {$booking['time']}</p>
                <p><strong>Address:</strong> {$booking['address']}, {$booking['city']}, {$booking['state']} {$booking['zip']}</p>
            </div>
            
            <div class="info-box">
                <h2 style="margin-top: 0; color: #14b8a6;">Estimated Price: \${$formattedPrice}</h2>
                <p><em>Final price will be confirmed after property inspection</em></p>
            </div>
            
            <p><strong>What's Next?</strong></p>
            <ul>
                <li>We'll contact you within 2 hours to confirm your appointment</li>
                <li>Our team will arrive at your scheduled time</li>
                <li>Sit back and relax while we make your space sparkle!</li>
            </ul>
            
            <p>Your invoice is attached to this email for your records.</p>
            
            <center>
                <a href="tel:3852138900" class="button">Call Us: (385)213-8900</a>
            </center>
            
            <p>Need to make changes? Just reply to this email or give us a call.</p>
            
            <p>Best regards,<br>The Wasatch Cleaners Team</p>
        </div>
        
        <div class="footer">
            <p>Wasatch Cleaners | Salt Lake City, UT</p>
            <p>(385)213-8900 | hello@wasatchcleaners.com</p>
            <p>&copy; 2025 Wasatch Cleaners. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Get plain text email for booking confirmation
 */
function get_confirmation_email_text(array $booking, string $bookingId, float $estimatedPrice): string
{
    $serviceNames = [
        'regular' => 'Regular Cleaning',
        'deep' => 'Deep Cleaning',
        'move' => 'Move In/Out Cleaning',
        'onetime' => 'One-Time Cleaning'
    ];
    
    $serviceName = $serviceNames[$booking['serviceType']] ?? $booking['serviceType'];
    $formattedPrice = number_format($estimatedPrice, 2);
    
    return <<<TEXT
BOOKING CONFIRMED

Hi {$booking['firstName']},

Thank you for choosing Wasatch Cleaners!

BOOKING DETAILS:
Booking ID: {$bookingId}
Service: {$serviceName}
Date: {$booking['date']}
Time: {$booking['time']}
Address: {$booking['address']}, {$booking['city']}, {$booking['state']} {$booking['zip']}

Estimated Price: \${$formattedPrice}
(Final price will be confirmed after property inspection)

We'll contact you within 2 hours to confirm your appointment.

Questions? Call us at (385)213-8900 or reply to this email.

Best regards,
The Wasatch Cleaners Team
TEXT;
}

/**
 * Log email sent attempt to database
 */
function log_email_sent(string $bookingId, string $email, string $subject, string $status, ?string $error = null): void
{
    try {
        $conn = db();
        
        $stmt = $conn->prepare("
            INSERT INTO email_logs (booking_id, recipient_email, subject, status, error_message)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($stmt) {
            $stmt->bind_param("sssss", $bookingId, $email, $subject, $status, $error);
            $stmt->execute();
            $stmt->close();
        }
        
    } catch (Exception $e) {
        error_log("Failed to log email: " . $e->getMessage());
    }
}

/**
 * Send SMS confirmation via OpenPhone API
 */
function send_booking_sms_confirmation(array $booking, string $bookingId): bool
{
    $logger = getLogger();

    // Check if OpenPhone is configured
    if (!defined('OPENPHONE_API_KEY') || empty(OPENPHONE_API_KEY) ||
        !defined('OPENPHONE_NUMBER') || empty(OPENPHONE_NUMBER)) {
        $logger->warning('OpenPhone SMS not configured', ['booking_id' => $bookingId]);
        return false;
    }

    try {
        // Normalize phone number to E.164 format
        $phone = preg_replace('/[^0-9]/', '', $booking['phone']);
        
        if (strlen($phone) === 10) {
            $phone = '+1' . $phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '1') {
            $phone = '+' . $phone;
        } else {
            $phone = '+' . $phone;
        }
        
        $message = "Wasatch Cleaners: Booking confirmed! ID: {$bookingId}. Date: {$booking['date']} at {$booking['time']}. We'll contact you soon to confirm. Questions? Call (385)213-8900";

        // OpenPhone API endpoint
        $url = 'https://api.openphone.com/v1/messages';

        $data = [
            'to' => [$phone],
            'from' => OPENPHONE_NUMBER,
            'content' => $message
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: " . OPENPHONE_API_KEY
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // OpenPhone returns 202 for success
        $success = ($httpCode === 202 || $httpCode === 200 || $httpCode === 201);

        log_sms_sent($bookingId, $booking['phone'], $message, $success ? 'sent' : 'failed', $success ? null : 'HTTP ' . $httpCode);

        if ($success) {
            $logger->info('SMS confirmation sent via OpenPhone', ['booking_id' => $bookingId]);
        } else {
            $logger->warning('Failed to send SMS confirmation via OpenPhone', [
                'booking_id' => $bookingId, 
                'http_code' => $httpCode, 
                'response' => $response
            ]);
        }

        return $success;
        
    } catch (Exception $e) {
        $logger->error('OpenPhone SMS sending exception', [
            'booking_id' => $bookingId,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Log SMS sent attempt to database
 */
function log_sms_sent(string $bookingId, string $phone, string $message, string $status, ?string $error = null): void
{
    try {
        $conn = db();
        
        $stmt = $conn->prepare("
            INSERT INTO sms_logs (booking_id, recipient_phone, message, status, error_message)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($stmt) {
            $stmt->bind_param("sssss", $bookingId, $phone, $message, $status, $error);
            $stmt->execute();
            $stmt->close();
        }
        
    } catch (Exception $e) {
        error_log("Failed to log SMS: " . $e->getMessage());
    }
}

/**
 * Send booking alert email to admin users
 * Called when a new booking is created
 */
function send_admin_booking_alert(string $bookingId, array $bookingDetails): bool
{
    $logger = getLogger();
    
    try {
        // Get all admin emails from database
        $conn = db();
        $stmt = $conn->prepare("
            SELECT email, full_name 
            FROM admin_users 
            WHERE is_active = 1 AND role IN ('admin', 'manager')
            ORDER BY role ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $admins = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($admins)) {
            $logger->warning('No active admins found to notify', ['booking_id' => $bookingId]);
            return false;
        }
        
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // From address
        $mail->setFrom(SMTP_FROM_EMAIL, 'Wasatch Cleaners Alert System');
        $mail->addReplyTo(COMPANY_EMAIL ?? SMTP_FROM_EMAIL, 'Wasatch Cleaners');
        
        // Add all admins as recipients
        foreach ($admins as $admin) {
            $mail->addAddress($admin['email'], $admin['full_name']);
        }
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = "📋 New Booking Alert: $bookingId";
        $mail->Body = get_admin_alert_email_html($bookingId, $bookingDetails);
        $mail->AltBody = get_admin_alert_email_text($bookingId, $bookingDetails);
        
        $result = $mail->send();
        
        // Log the email
        foreach ($admins as $admin) {
            log_email_sent($bookingId, $admin['email'], $mail->Subject, $result ? 'sent' : 'failed');
        }
        
        if ($result) {
            $logger->info('Admin alert email sent successfully', ['booking_id' => $bookingId]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        $logger->error('Admin alert email exception', [
            'booking_id' => $bookingId,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Get HTML template for admin booking alert
 */
function get_admin_alert_email_html(string $bookingId, array $details): string
{
    $serviceNames = [
        'regular' => 'Regular Cleaning',
        'deep' => 'Deep Cleaning',
        'move' => 'Move In/Out Cleaning',
        'onetime' => 'One-Time Cleaning'
    ];
    
    // Handle both array key formats safely
    $serviceType = $details['service_type'] ?? $details['serviceType'] ?? '';
    $serviceName = $serviceNames[$serviceType] ?? $serviceType;
    
    $estimatedPrice = $details['estimated_price'] ?? $details['estimatedPrice'] ?? 0;
    $formattedPrice = number_format((float)$estimatedPrice, 2);
    
    $appointmentDate = $details['appointment_date'] ?? $details['date'] ?? '';
    $appointmentTime = $details['appointment_time'] ?? $details['time'] ?? '';
    $appointmentDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentDate . ' ' . $appointmentTime));
    
    // Use null coalescing for all array accesses
    $firstName = $details['first_name'] ?? $details['firstName'] ?? '';
    $lastName = $details['last_name'] ?? $details['lastName'] ?? '';
    $email = $details['email'] ?? '';
    $phone = $details['phone'] ?? '';
    $address = $details['address'] ?? '';
    $city = $details['city'] ?? '';
    $state = $details['state'] ?? '';
    $zip = $details['zip'] ?? '';
    $bedrooms = $details['bedrooms'] ?? '';
    $bathrooms = $details['bathrooms'] ?? '';
    $notes = $details['notes'] ?? '';
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #dc2626, #991b1b); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .urgent { background: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b; margin: 20px 0; }
        .content { background: #f9fafb; padding: 30px; }
        .info-box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #14b8a6; }
        .detail-row { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .label { font-weight: bold; color: #4b5563; }
        .button { display: inline-block; background: #f43f5e; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 28px;">📋 NEW BOOKING RECEIVED</h1>
            <p style="margin: 10px 0 0 0;">Booking ID: {$bookingId}</p>
        </div>
        
        <div class="content">
            <div class="urgent">
                <strong>⚠️ ACTION REQUIRED:</strong> This booking needs staff assignment
            </div>
            
            <div class="info-box">
                <div class="detail-row">
                    <span class="label">Customer:</span><br>
                    <span>{$firstName} {$lastName}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Contact:</span><br>
                    <span>📧 {$email}<br>📞 {$phone}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Service:</span><br>
                    <span>{$serviceName}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Date & Time:</span><br>
                    <span>{$appointmentDateTime}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Location:</span><br>
                    <span>{$address}, {$city}, {$state} {$zip}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Property:</span><br>
                    <span>{$bedrooms} bed, {$bathrooms} bath</span>
                </div>
                <div class="detail-row">
                    <span class="label">Estimated Price:</span><br>
                    <span style="font-size: 20px; color: #22c55e; font-weight: bold;">\${$formattedPrice}</span>
                </div>
                <div class="detail-row" style="border-bottom: none;">
                    <span class="label">Customer Notes:</span><br>
                    <span>{$notes}</span>
                </div>
            </div>

            <center>
                <a href="https://ekotgroup.enoudoh.com/admin/dashboard.php" class="button">
                    VIEW IN DASHBOARD →
                </a>
            </center>

            <p style="margin-top: 30px; font-size: 14px; color: #6b7280; text-align: center;">
                Please assign staff to this booking as soon as possible.
            </p>
        </div>
        
        <div class="footer">
            <p>Wasatch Cleaners Admin System</p>
            <p>This is an automated notification. Do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Get plain text template for admin booking alert
 */
function get_admin_alert_email_text(string $bookingId, array $details): string
{
    $serviceNames = [
        'regular' => 'Regular Cleaning',
        'deep' => 'Deep Cleaning',
        'move' => 'Move In/Out Cleaning',
        'onetime' => 'One-Time Cleaning'
    ];
    
    // Handle both array key formats safely
    $serviceType = $details['service_type'] ?? $details['serviceType'] ?? '';
    $serviceName = $serviceNames[$serviceType] ?? $serviceType;
    $formattedPrice = number_format((float)($details['estimated_price'] ?? $details['estimatedPrice'] ?? 0), 2);
    $appointmentDateTime = date('l, F j, Y \a\t g:i A', strtotime(($details['appointment_date'] ?? $details['date'] ?? '') . ' ' . ($details['appointment_time'] ?? $details['time'] ?? '')));
    
    // Use null coalescing for all array accesses
    $firstName = $details['first_name'] ?? $details['firstName'] ?? '';
    $lastName = $details['last_name'] ?? $details['lastName'] ?? '';
    $email = $details['email'] ?? '';
    $phone = $details['phone'] ?? '';
    $address = $details['address'] ?? '';
    $city = $details['city'] ?? '';
    $state = $details['state'] ?? '';
    $zip = $details['zip'] ?? '';
    $bedrooms = $details['bedrooms'] ?? '';
    $bathrooms = $details['bathrooms'] ?? '';
    $notes = $details['notes'] ?? '';
    
    return <<<TEXT
NEW BOOKING ALERT

⚠️ ACTION REQUIRED: This booking needs staff assignment

BOOKING ID: {$bookingId}

CUSTOMER INFORMATION:
Name: {$firstName} {$lastName}
Email: {$email}
Phone: {$phone}

SERVICE DETAILS:
Service: {$serviceName}
Date & Time: {$appointmentDateTime}
Location: {$address}, {$city}, {$state} {$zip}
Property: {$bedrooms} bed, {$bathrooms} bath

Estimated Price: \${$formattedPrice}

Customer Notes: {$notes}

Please log in to the admin dashboard to assign staff:
'/admin/dashboard.php'

---
Wasatch Cleaners Admin System
TEXT;
}

/**
 * Send assignment notification email to staff member
 */
function send_staff_assignment_email(string $staffEmail, string $staffName, string $bookingId, array $bookingDetails): bool
{
    $logger = getLogger();
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, 'Wasatch Cleaners - Assignments');
        $mail->addAddress($staffEmail, $staffName);
        $mail->addReplyTo(COMPANY_EMAIL ?? SMTP_FROM_EMAIL, 'Wasatch Cleaners');
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = "New Assignment: Booking $bookingId";
        $mail->Body = get_staff_assignment_email_html($staffName, $bookingId, $bookingDetails);
        $mail->AltBody = get_staff_assignment_email_text($staffName, $bookingId, $bookingDetails);
        
        $result = $mail->send();
        
        log_email_sent($bookingId, $staffEmail, $mail->Subject, $result ? 'sent' : 'failed');
        
        if ($result) {
            $logger->info('Staff assignment email sent', ['booking_id' => $bookingId, 'staff_email' => $staffEmail]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        $logger->error('Staff assignment email exception', [
            'booking_id' => $bookingId,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Get HTML template for staff assignment email
 */
function get_staff_assignment_email_html(string $staffName, string $bookingId, array $details): string
{
    $serviceNames = [
        'regular' => 'Regular Cleaning',
        'deep' => 'Deep Cleaning',
        'move' => 'Move In/Out Cleaning',
        'onetime' => 'One-Time Cleaning'
    ];
    
    $serviceType = $details['service_type'] ?? $details['serviceType'] ?? '';
    $serviceName = $serviceNames[$serviceType] ?? $serviceType;
    
    $appointmentDate = $details['appointment_date'] ?? $details['date'] ?? '';
    $appointmentTime = $details['appointment_time'] ?? $details['time'] ?? '';
    $appointmentDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentDate . ' ' . $appointmentTime));
    
    $location = ($details['address'] ?? '') . ', ' . ($details['city'] ?? '') . ', ' . ($details['state'] ?? '');
    
    // Use safe array access for property details
    $bedrooms = $details['bedrooms'] ?? '';
    $bathrooms = $details['bathrooms'] ?? '';
    $firstName = $details['first_name'] ?? $details['firstName'] ?? '';
    $lastName = $details['last_name'] ?? $details['lastName'] ?? '';
    $phone = $details['phone'] ?? '';
    $email = $details['email'] ?? '';
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; }
        .info-box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #8b5cf6; }
        .detail-row { padding: 8px 0; }
        .label { font-weight: bold; color: #4b5563; display: inline-block; width: 140px; }
        .highlight { background: #f0fdf4; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #22c55e; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">👋 You've Been Assigned!</h1>
            <p style="margin: 10px 0 0 0;">New Job Assignment</p>
        </div>
        
        <div class="content">
            <p>Hi {$staffName},</p>
            
            <p>You have been assigned to a new cleaning job. Please review the details below:</p>
            
            <div class="info-box">
                <h2 style="margin-top: 0; color: #8b5cf6;">Job Details</h2>
                <div class="detail-row">
                    <span class="label">Booking ID:</span>
                    <span>{$bookingId}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Service:</span>
                    <span>{$serviceName}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Date & Time:</span>
                    <span style="font-weight: bold;">{$appointmentDateTime}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Location:</span>
                    <span>{$location}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Property:</span>
                    <span>{$bedrooms} bed, {$bathrooms} bath</span>
                </div>
            </div>
            
            <div class="info-box">
                <h2 style="margin-top: 0; color: #8b5cf6;">Customer Contact</h2>
                <div class="detail-row">
                    <span class="label">Name:</span>
                    <span>{$firstName} {$lastName}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Phone:</span>
                    <span><a href="tel:{$phone}">{$phone}</a></span>
                </div>
                <div class="detail-row">
                    <span class="label">Email:</span>
                    <span>{$email}</span>
                </div>
            </div>
            
            <div class="highlight">
                <strong>📋 Important:</strong>
                <ul style="margin: 10px 0;">
                    <li>Arrive 10 minutes early</li>
                    <li>Bring all necessary equipment</li>
                    <li>Contact customer if running late</li>
                    <li>Report any issues immediately</li>
                </ul>
            </div>
            
            <p>If you have any questions or need to decline this assignment, please contact the office immediately at <strong>(385)213-8900</strong>.</p>
            
            <p>Thank you!</p>
            
            <p>Best regards,<br>Wasatch Cleaners Management</p>
        </div>
        
        <div class="footer">
            <p>Wasatch Cleaners</p>
            <p>(385)213-8900 | hello@wasatchcleaners.com</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Get plain text template for staff assignment email
 */
function get_staff_assignment_email_text(string $staffName, string $bookingId, array $details): string
{
    $serviceNames = [
        'regular' => 'Regular Cleaning',
        'deep' => 'Deep Cleaning',
        'move' => 'Move In/Out Cleaning',
        'onetime' => 'One-Time Cleaning'
    ];
    
    $serviceType = $details['service_type'] ?? $details['serviceType'] ?? '';
    $serviceName = $serviceNames[$serviceType] ?? $serviceType;
    
    $appointmentDate = $details['appointment_date'] ?? $details['date'] ?? '';
    $appointmentTime = $details['appointment_time'] ?? $details['time'] ?? '';
    $appointmentDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentDate . ' ' . $appointmentTime));
    
    // Use safe array access
    $address = $details['address'] ?? '';
    $city = $details['city'] ?? '';
    $state = $details['state'] ?? '';
    $bedrooms = $details['bedrooms'] ?? '';
    $bathrooms = $details['bathrooms'] ?? '';
    $firstName = $details['first_name'] ?? $details['firstName'] ?? '';
    $lastName = $details['last_name'] ?? $details['lastName'] ?? '';
    $phone = $details['phone'] ?? '';
    $email = $details['email'] ?? '';
    
    return <<<TEXT
NEW JOB ASSIGNMENT

Hi {$staffName},

You have been assigned to a new cleaning job.

JOB DETAILS:
Booking ID: {$bookingId}
Service: {$serviceName}
Date & Time: {$appointmentDateTime}
Location: {$address}, {$city}, {$state}
Property: {$bedrooms} bed, {$bathrooms} bath

CUSTOMER CONTACT:
Name: {$firstName} {$lastName}
Phone: {$phone}
Email: {$email}

IMPORTANT REMINDERS:
- Arrive 10 minutes early
- Bring all necessary equipment
- Contact customer if running late
- Report any issues immediately

Questions? Call the office: (385)213-8900

Thank you!
Wasatch Cleaners Management
TEXT;
}

/**
 * Send assignment notification SMS to staff member
 */
function send_staff_assignment_sms(string $staffPhone, string $bookingId, string $appointmentDate, string $appointmentTime, string $location): bool
{
    // Check if OpenPhone is configured
    if (!defined('OPENPHONE_API_KEY') || empty(OPENPHONE_API_KEY) ||
        !defined('OPENPHONE_NUMBER') || empty(OPENPHONE_NUMBER)) {
        return false;
    }

    $logger = getLogger();

    try {
        // Normalize phone number to E.164 format
        $phone = preg_replace('/[^0-9]/', '', $staffPhone);
        
        if (strlen($phone) === 10) {
            $phone = '+1' . $phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '1') {
            $phone = '+' . $phone;
        } else {
            $phone = '+' . $phone;
        }
        
        // Format date for SMS
        $dateFormatted = date('m/d/Y', strtotime($appointmentDate));
        $timeFormatted = date('g:iA', strtotime($appointmentTime));
        
        $message = "Wasatch Cleaners: You've been assigned to booking {$bookingId} on {$dateFormatted} at {$timeFormatted}. Location: {$location}. Check your email for full details. Questions? Call (385)213-8900";

        // OpenPhone API endpoint
        $url = 'https://api.openphone.com/v1/messages';

        $data = [
            'to' => [$phone],
            'from' => OPENPHONE_NUMBER,
            'content' => $message
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: " . OPENPHONE_API_KEY
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $success = ($httpCode === 202 || $httpCode === 200 || $httpCode === 201);

        log_sms_sent($bookingId, $staffPhone, $message, $success ? 'sent' : 'failed', $success ? null : 'HTTP ' . $httpCode);

        if ($success) {
            $logger->info('Staff assignment SMS sent', ['booking_id' => $bookingId, 'staff_phone' => $staffPhone]);
        } else {
            $logger->warning('Failed to send staff assignment SMS', [
                'booking_id' => $bookingId,
                'http_code' => $httpCode,
                'response' => $response
            ]);
        }

        return $success;
        
    } catch (Exception $e) {
        $logger->error('Staff assignment SMS exception', [
            'booking_id' => $bookingId,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Notify staff when assigned to a booking
 */
function notify_staff_assignment(string $staffId, string $bookingId, array $bookingDetails): bool {
    $conn = db();
    
    // Get staff details
    $stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM staff WHERE id = ?");
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$staff) {
        return false;
    }

    $staffName = $staff['first_name'] . ' ' . $staff['last_name'];
    $success = true;

    // Send email notification
    if (!empty($staff['email'])) {
        $emailSent = send_staff_assignment_email($staff['email'], $staffName, $bookingId, $bookingDetails);
        if (!$emailSent) {
            error_log("Failed to send assignment email to staff: {$staff['email']}");
            $success = false;
        }
    }

    // Send SMS notification
    if (!empty($staff['phone'])) {
        $smsSent = send_staff_assignment_sms(
            $staff['phone'], 
            $bookingId, 
            $bookingDetails['appointment_date'] ?? $bookingDetails['date'],
            $bookingDetails['appointment_time'] ?? $bookingDetails['time'],
            $bookingDetails['address'] ?? ''
        );
        if (!$smsSent) {
            error_log("Failed to send assignment SMS to staff: {$staff['phone']}");
            $success = false;
        }
    }

    return $success;
}

/**
 * Notify customer and staff when booking is cancelled
 */
function notify_booking_cancelled(string $bookingId, string $reason): bool {
    $conn = db();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT b.*, 
               GROUP_CONCAT(s.id) as staff_ids,
               GROUP_CONCAT(s.email) as staff_emails,
               GROUP_CONCAT(s.phone) as staff_phones
        FROM bookings b
        LEFT JOIN booking_assignments ba ON b.booking_id = ba.booking_id
        LEFT JOIN staff s ON ba.staff_id = s.id
        WHERE b.booking_id = ?
        GROUP BY b.booking_id
    ");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        return false;
    }

    $success = true;

    // Notify customer via SMS
    if (!empty($booking['phone'])) {
        $message = "Wasatch Cleaners: Your booking #{$bookingId} has been cancelled. Reason: {$reason}. Questions? Call (385)213-8900";
        $smsSent = send_sms_via_provider($booking['phone'], $message);
        if (!$smsSent) {
            error_log("Failed to send cancellation SMS to customer");
            $success = false;
        }
    }

    // Notify assigned staff
    if (!empty($booking['staff_ids'])) {
        $staffIds = explode(',', $booking['staff_ids']);
        $staffEmails = explode(',', $booking['staff_emails']);
        $staffPhones = explode(',', $booking['staff_phones']);

        foreach ($staffIds as $index => $staffId) {
            $staffEmail = $staffEmails[$index] ?? null;
            $staffPhone = $staffPhones[$index] ?? null;

            if ($staffEmail) {
                // Send cancellation email to staff
                send_staff_cancellation_email($staffEmail, $bookingId, $booking);
            }

            if ($staffPhone) {
                $message = "Wasatch Cleaners: Booking #{$bookingId} has been cancelled. You are no longer assigned.";
                send_sms_via_provider($staffPhone, $message);
            }
        }
    }

    return $success;
}

/**
 * Send cancellation notification email to staff member
 */
function send_staff_cancellation_email(string $staffEmail, string $bookingId, array $bookingDetails): bool
{
    $logger = getLogger();
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, 'Wasatch Cleaners - Notifications');
        $mail->addAddress($staffEmail);
        $mail->addReplyTo(COMPANY_EMAIL ?? SMTP_FROM_EMAIL, 'Wasatch Cleaners');
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Assignment Cancelled: Booking $bookingId";
        $mail->Body = get_staff_cancellation_email_html($bookingId, $bookingDetails);
        $mail->AltBody = get_staff_cancellation_email_text($bookingId, $bookingDetails);
        
        $result = $mail->send();
        
        log_email_sent($bookingId, $staffEmail, $mail->Subject, $result ? 'sent' : 'failed');
        
        if ($result) {
            $logger->info('Staff cancellation email sent', ['booking_id' => $bookingId, 'staff_email' => $staffEmail]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        $logger->error('Staff cancellation email exception', [
            'booking_id' => $bookingId,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Get HTML template for staff cancellation email
 */
function get_staff_cancellation_email_html(string $bookingId, array $details): string
{
    $customerName = ($details['first_name'] ?? '') . ' ' . ($details['last_name'] ?? '');
    $appointmentDate = $details['appointment_date'] ?? $details['date'] ?? '';
    $appointmentTime = $details['appointment_time'] ?? $details['time'] ?? '';
    $appointmentDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentDate . ' ' . $appointmentTime));
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #6b7280, #4b5563); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; }
        .info-box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #6b7280; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">Assignment Cancelled</h1>
            <p style="margin: 10px 0 0 0;">Booking #{$bookingId}</p>
        </div>
        
        <div class="content">
            <p>Your assignment for the following booking has been cancelled:</p>
            
            <div class="info-box">
                <h2 style="margin-top: 0; color: #6b7280;">Booking Details</h2>
                <p><strong>Booking ID:</strong> {$bookingId}</p>
                <p><strong>Customer:</strong> {$customerName}</p>
                <p><strong>Date & Time:</strong> {$appointmentDateTime}</p>
                <p><strong>Location:</strong> {$details['address']}, {$details['city']}, {$details['state']}</p>
            </div>
            
            <p>You are no longer assigned to this booking. Please remove it from your schedule.</p>
            
            <p>If you have any questions, please contact the office at <strong>(385)213-8900</strong>.</p>
            
            <p>Best regards,<br>Wasatch Cleaners Management</p>
        </div>
        
        <div class="footer">
            <p>Wasatch Cleaners</p>
            <p>(385)213-8900 | hello@wasatchcleaners.com</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Get plain text template for staff cancellation email
 */
function get_staff_cancellation_email_text(string $bookingId, array $details): string
{
    $customerName = ($details['first_name'] ?? '') . ' ' . ($details['last_name'] ?? '');
    $appointmentDate = $details['appointment_date'] ?? $details['date'] ?? '';
    $appointmentTime = $details['appointment_time'] ?? $details['time'] ?? '';
    $appointmentDateTime = date('l, F j, Y \a\t g:i A', strtotime($appointmentDate . ' ' . $appointmentTime));
    
    return <<<TEXT
ASSIGNMENT CANCELLED

Your assignment for the following booking has been cancelled:

Booking ID: {$bookingId}
Customer: {$customerName}
Date & Time: {$appointmentDateTime}
Location: {$details['address']}, {$details['city']}, {$details['state']}

You are no longer assigned to this booking. Please remove it from your schedule.

If you have any questions, please contact the office at (385)213-8900.

Best regards,
Wasatch Cleaners Management
TEXT;
}

/**
 * Notify customer and staff when booking is rescheduled
 */
function notify_booking_rescheduled(string $bookingId, string $oldDate, string $oldTime, string $newDate, string $newTime): bool {
    $conn = db();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT b.*, 
               GROUP_CONCAT(s.id) as staff_ids,
               GROUP_CONCAT(s.phone) as staff_phones
        FROM bookings b
        LEFT JOIN booking_assignments ba ON b.booking_id = ba.booking_id
        LEFT JOIN staff s ON ba.staff_id = s.id
        WHERE b.booking_id = ?
        GROUP BY b.booking_id
    ");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        return false;
    }

    $success = true;
    $oldDateTime = date('M j, Y g:i A', strtotime("$oldDate $oldTime"));
    $newDateTime = date('M j, Y g:i A', strtotime("$newDate $newTime"));

    // Notify customer
    if (!empty($booking['phone'])) {
        $message = "Wasatch Cleaners: Your booking #{$bookingId} has been rescheduled from {$oldDateTime} to {$newDateTime}. Questions? Call (385)213-8900";
        $smsSent = send_sms_via_provider($booking['phone'], $message);
        if (!$smsSent) {
            error_log("Failed to send reschedule SMS to customer");
            $success = false;
        }
    }

    // Notify staff
    if (!empty($booking['staff_phones'])) {
        $staffPhones = explode(',', $booking['staff_phones']);
        foreach ($staffPhones as $staffPhone) {
            if ($staffPhone) {
                $message = "Wasatch Cleaners: Booking #{$bookingId} has been rescheduled to {$newDateTime}. Please update your schedule.";
                send_sms_via_provider($staffPhone, $message);
            }
        }
    }

    return $success;
}

/**
 * Generic SMS sending function using OpenPhone
 */
function send_sms_via_provider(string $phone, string $message): bool {
    // Use OpenPhone for all SMS
    if (function_exists('send_booking_sms_confirmation')) {
        $tempBooking = ['phone' => $phone];
        // We'll create a modified version that accepts custom messages
        return send_custom_sms_openphone($phone, $message);
    }

    return false;
}

/**
 * Send custom SMS via OpenPhone API
 */
function send_custom_sms_openphone(string $phone, string $message): bool
{
    // Check if OpenPhone is configured
    if (!defined('OPENPHONE_API_KEY') || empty(OPENPHONE_API_KEY) ||
        !defined('OPENPHONE_NUMBER') || empty(OPENPHONE_NUMBER)) {
        return false;
    }

    try {
        // Normalize phone number to E.164 format
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 10) {
            $phone = '+1' . $phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '1') {
            $phone = '+' . $phone;
        } else {
            $phone = '+' . $phone;
        }

        // OpenPhone API endpoint
        $url = 'https://api.openphone.com/v1/messages';

        $data = [
            'to' => [$phone],
            'from' => OPENPHONE_NUMBER,
            'content' => $message
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: " . OPENPHONE_API_KEY
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // OpenPhone returns 202 for success
        return ($httpCode === 202 || $httpCode === 200 || $httpCode === 201);
        
    } catch (Exception $e) {
        error_log("OpenPhone SMS exception: " . $e->getMessage());
        return false;
    }
}