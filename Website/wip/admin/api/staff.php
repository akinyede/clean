<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');
ensure_api_authenticated(['admin', 'manager', 'staff']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $payload = read_staff_payload($_SERVER['REQUEST_METHOD'] !== 'DELETE');
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handle_staff_get();
        break;
    case 'POST':
        handle_staff_post($payload);
        break;
    case 'PUT':
        handle_staff_put($payload);
        break;
    case 'DELETE':
        handle_staff_delete($payload);
        break;
    default:
        json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

function handle_staff_get(): void
{
    $search = trim($_GET['search'] ?? '');
    $conn = db();

    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmt = $conn->prepare("
            SELECT
                s.id,
                s.first_name,
                s.last_name,
                s.role,
                s.email,
                s.phone,
                s.color_tag,
                s.notes,
                s.is_active,
                (
                    SELECT COUNT(*)
                    FROM booking_assignments ba
                    WHERE ba.staff_id = s.id
                ) AS assignments,
                (
                    SELECT CONCAT(available_date, ' ', start_time)
                    FROM staff_availability sa
                    WHERE sa.staff_id = s.id
                    AND sa.is_available = 1
                    AND sa.available_date >= CURDATE()
                    ORDER BY sa.available_date, sa.start_time
                    LIMIT 1
                ) AS next_available
            FROM staff s
            WHERE s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.phone LIKE ?
            ORDER BY s.updated_at DESC
            LIMIT 100
        ");
        $stmt->bind_param('ssss', $like, $like, $like, $like);
    } else {
        $stmt = $conn->prepare("
            SELECT
                s.id,
                s.first_name,
                s.last_name,
                s.role,
                s.email,
                s.phone,
                s.color_tag,
                s.notes,
                s.is_active,
                (
                    SELECT COUNT(*)
                    FROM booking_assignments ba
                    WHERE ba.staff_id = s.id
                ) AS assignments,
                (
                    SELECT CONCAT(available_date, ' ', start_time)
                    FROM staff_availability sa
                    WHERE sa.staff_id = s.id
                    AND sa.is_available = 1
                    AND sa.available_date >= CURDATE()
                    ORDER BY sa.available_date, sa.start_time
                    LIMIT 1
                ) AS next_available
            FROM staff s
            ORDER BY s.last_name, s.first_name
            LIMIT 100
        ");
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $staff = [];

    while ($row = $result->fetch_assoc()) {
        $staff[] = [
            'id' => (int) $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            'role' => ucfirst(str_replace('_', ' ', $row['role'])),
            'email' => $row['email'],
            'phone' => format_phone_display($row['phone']),
            'raw_phone' => $row['phone'],
            'color_tag' => $row['color_tag'],
            'notes' => $row['notes'],
            'is_active' => (bool) $row['is_active'],
            'assignments' => (int) ($row['assignments'] ?? 0),
            'next_available' => $row['next_available']
                ? date('M j, g:i A', strtotime($row['next_available']))
                : 'Not provided',
        ];
    }

    $stmt->close();
    
    json_response([
        'success' => true,
        'staff' => $staff
    ]);
}

function handle_staff_post(array $payload): void
{
    $required = ['first_name', 'last_name', 'email'];
    foreach ($required as $field) {
        if (empty($payload[$field])) {
            json_response(['success' => false, 'message' => sprintf('%s is required', ucfirst(str_replace('_', ' ', $field)))], 400);
        }
    }

    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO staff (first_name, last_name, email, phone, role, color_tag, notes, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $phone = normalize_phone($payload['phone'] ?? '');
    $role = $payload['role'] ?? 'cleaner';
    $color = $payload['color_tag'] ?? '#6b7280';
    $notes = $payload['notes'] ?? null;
    $isActive = isset($payload['is_active']) ? (int) (bool) $payload['is_active'] : 1;
    $stmt->bind_param(
        'sssssssi',
        $payload['first_name'],
        $payload['last_name'],
        $payload['email'],
        $phone,
        $role,
        $color,
        $notes,
        $isActive
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    json_response([
        'success' => true,
        'id' => (int) $newId,
        'message' => 'Staff member created',
    ], 201);
}

function handle_staff_put(array $payload): void
{
    $staffId = (int) ($payload['id'] ?? $payload['staff_id'] ?? 0);
    if ($staffId <= 0) {
        json_response(['success' => false, 'message' => 'Staff ID required'], 400);
    }

    $updates = $payload['updates'] ?? $payload;
    unset($updates['id'], $updates['staff_id']);

    $allowed = [
        'first_name' => 's',
        'last_name' => 's',
        'email' => 's',
        'phone' => 's',
        'role' => 's',
        'color_tag' => 's',
        'notes' => 's',
        'is_active' => 'i',
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
        } elseif ($field === 'is_active') {
            $params[] = (int) (bool) $value;
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
        "UPDATE staff SET %s WHERE id = ?",
        implode(', ', $setParts)
    ));
    $types .= 'i';
    $params[] = $staffId;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true, 'message' => 'Staff member updated']);
}

function handle_staff_delete(array $payload): void
{
    $staffId = (int) ($_GET['id'] ?? $payload['id'] ?? $payload['staff_id'] ?? 0);
    if ($staffId <= 0) {
        json_response(['success' => false, 'message' => 'Staff ID required'], 400);
    }

    $conn = db();
    $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        json_response(['success' => false, 'message' => 'Staff member not found'], 404);
    }

    json_response(['success' => true, 'message' => 'Staff member deleted']);
}

function read_staff_payload(bool $requireJson = true): array
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

