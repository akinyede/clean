<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');
ensure_api_authenticated(['admin', 'manager', 'staff']);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get();
        break;
    case 'POST':
    case 'PUT':
    case 'DELETE':
        json_response([
            'success' => false,
            'message' => 'Invoice creation will be released alongside the billing module.',
        ], 501);
        break;
    default:
        json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

function handle_get(): void
{
    $status = $_GET['status'] ?? '';
    $conn = db();

    if ($status !== '') {
        $stmt = $conn->prepare("
            SELECT i.invoice_number,
                   i.booking_id,
                   i.issue_date,
                   i.total,
                   i.status,
                   COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Walk-in') AS customer_name
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.status = ?
            ORDER BY i.issue_date DESC
            LIMIT 50
        ");
        $stmt->bind_param('s', $status);
    } else {
        $stmt = $conn->prepare("
            SELECT i.invoice_number,
                   i.booking_id,
                   i.issue_date,
                   i.total,
                   i.status,
                   COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Walk-in') AS customer_name
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            ORDER BY i.issue_date DESC
            LIMIT 50
        ");
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = [
            'invoice_number' => $row['invoice_number'],
            'booking_id' => $row['booking_id'],
            'issue_date' => date('M j, Y', strtotime($row['issue_date'])),
            'total' => format_currency((float) $row['total']),
            'status' => $row['status'],
            'customer_name' => $row['customer_name'],
        ];
    }
    $stmt->close();

    json_response([
        'success' => true,
        'invoices' => $invoices,
    ]);
}
