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
        handle_post();
        break;
    case 'PUT':
        handle_put();
        break;
    case 'DELETE':
        handle_delete();
        break;
    default:
        json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

function handle_get(): void
{
    $query = trim($_GET['search'] ?? $_GET['q'] ?? '');

    $conn = db();
    if ($query !== '') {
        $like = '%' . $query . '%';
        $stmt = $conn->prepare("
            SELECT c.id,
                   c.first_name,
                   c.last_name,
                   c.email,
                   c.phone,
                   c.address,
                   c.city,
                   c.state,
                   c.zip,
                   c.notes,
                   MAX(b.appointment_date) AS last_booking
            FROM customers c
            LEFT JOIN bookings b ON b.customer_id = c.id
            WHERE c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?
            GROUP BY c.id
            ORDER BY c.updated_at DESC
            LIMIT 50
        ");
        $stmt->bind_param('ssss', $like, $like, $like, $like);
    } else {
        $stmt = $conn->prepare("
            SELECT c.id,
                   c.first_name,
                   c.last_name,
                   c.email,
                   c.phone,
                   c.address,
                   c.city,
                   c.state,
                   c.zip,
                   c.notes,
                   MAX(b.appointment_date) AS last_booking
            FROM customers c
            LEFT JOIN bookings b ON b.customer_id = c.id
            GROUP BY c.id
            ORDER BY c.updated_at DESC
            LIMIT 50
        ");
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = [
            'id' => (int) $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            'email' => $row['email'],
            'phone' => format_phone_display($row['phone']),
            'raw_phone' => $row['phone'],
            'address' => $row['address'],
            'city' => $row['city'],
            'state' => $row['state'],
            'zip' => $row['zip'],
            'notes' => $row['notes'],
            'last_booking' => $row['last_booking'] ? date('M j, Y', strtotime($row['last_booking'])) : null,
        ];
    }
    $stmt->close();

    json_response([
        'success' => true,
        'customers' => $customers,
    ]);
}

function handle_post(): void
{
    $payload = read_customer_payload();
    $required = ['first_name', 'last_name', 'email'];
    foreach ($required as $field) {
        if (empty($payload[$field])) {
            json_response(['success' => false, 'message' => sprintf('%s is required', ucfirst(str_replace('_', ' ', $field)))], 400);
        }
    }

    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO customers (first_name, last_name, email, phone, address, city, state, zip, notes, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $phone = normalize_phone($payload['phone'] ?? '');
    $address = $payload['address'] ?? '';
    $city = $payload['city'] ?? '';
    $state = $payload['state'] ?? '';
    $zip = $payload['zip'] ?? '';
    $notes = $payload['notes'] ?? '';
    
    $stmt->bind_param(
        'sssssssss',
        $payload['first_name'],
        $payload['last_name'],
        $payload['email'],
        $phone,
        $address,
        $city,
        $state,
        $zip,
        $notes
    );
    
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    json_response([
        'success' => true,
        'id' => (int) $newId,
        'message' => 'Customer created',
    ], 201);
}

function handle_put(): void
{
    $payload = read_customer_payload();
    $customerId = (int) ($payload['id'] ?? $payload['customer_id'] ?? 0);
    if ($customerId <= 0) {
        json_response(['success' => false, 'message' => 'Customer ID required'], 400);
    }

    $updates = $payload['updates'] ?? $payload;
    unset($updates['id'], $updates['customer_id']);

    $allowed = [
        'first_name' => 's',
        'last_name' => 's',
        'email' => 's',
        'phone' => 's',
        'address' => 's',
        'city' => 's',
        'state' => 's',
        'zip' => 's',
        'notes' => 's',
    ];

    $setParts = [];
    $params = [];
    $types = '';

    foreach ($updates as $field => $value) {
        if (!array_key_exists($field, $allowed)) {
            continue;
        }
        $setParts[] = "{$field} = ?";
        $types .= $allowed[$field];
        if ($field === 'phone') {
            $params[] = normalize_phone((string) $value);
        } else {
            $params[] = $value;
        }
    }

    if (empty($setParts)) {
        json_response(['success' => false, 'message' => 'No valid fields to update'], 400);
    }

    $setParts[] = "updated_at = NOW()";
    $conn = db();
    $stmt = $conn->prepare(sprintf(
        "UPDATE customers SET %s WHERE id = ?",
        implode(', ', $setParts)
    ));
    $types .= 'i';
    $params[] = $customerId;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true, 'message' => 'Customer updated']);
}

function handle_delete(): void
{
    $payload = read_customer_payload(false);
    $customerId = (int) ($_GET['id'] ?? $payload['id'] ?? $payload['customer_id'] ?? 0);
    if ($customerId <= 0) {
        json_response(['success' => false, 'message' => 'Customer ID required'], 400);
    }

    $conn = db();
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        json_response(['success' => false, 'message' => 'Customer not found'], 404);
    }

    json_response(['success' => true, 'message' => 'Customer deleted']);
}

function read_customer_payload(bool $requireJson = true): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return $requireJson ? [] : [];
    }
    $decoded = json_decode($raw, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        json_response(['success' => false, 'message' => 'Invalid JSON payload'], 400);
    }
    return is_array($decoded) ? $decoded : [];
}
