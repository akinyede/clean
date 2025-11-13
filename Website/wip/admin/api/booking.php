<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');

try {
    $user = ensure_api_authenticated(['admin', 'manager', 'staff']);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    switch ($method) {
        case 'GET':
            handle_get();
            break;
        case 'PUT':
            $payload = decode_request_body();
            handle_put($payload, $user);
            break;
        case 'POST':
            $payload = decode_request_body();
            handle_post($payload, $user);
            break;
        case 'DELETE':
            $payload = decode_request_body(false);
            handle_delete($payload, $user);
            break;
        default:
            json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $exception) {
    error_log('Booking API error: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Server error'], 500);
}

function handle_get(): void
{
    $bookingId = $_GET['id'] ?? null;
    $customerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null;

    if ($bookingId) {
        $detail = fetch_booking_detail($bookingId);
        if (!$detail) {
            json_response(['success' => false, 'message' => 'Booking not found'], 404);
        }
        json_response(array_merge(['success' => true], $detail));
    }

    if ($customerId) {
        $conn = db();
        $stmt = $conn->prepare("
            SELECT booking_id, appointment_date, appointment_time, service_type, status, status_label, estimated_price
            FROM bookings
            WHERE customer_id = ?
            ORDER BY appointment_date DESC, created_at DESC
            LIMIT 100
        ");
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();

        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $bookings[] = [
                'booking_id' => $row['booking_id'],
                'appointment_date' => $row['appointment_date'],
                'appointment_time' => $row['appointment_time'],
                'service_type' => $row['service_type'],
                'status' => $row['status'],
                'status_label' => $row['status_label'],
                'status_color' => status_color($row['status_label']),
                'estimated_price' => $row['estimated_price'] !== null ? format_currency((float) $row['estimated_price']) : null,
            ];
        }
        $stmt->close();

        json_response(['success' => true, 'bookings' => $bookings]);
    }

    json_response(['success' => false, 'message' => 'Booking id or customer id required'], 400);
}

function handle_put(array $payload, array $user): void
{
    $bookingId = trim((string) ($payload['booking_id'] ?? ''));
    if ($bookingId === '') {
        json_response(['success' => false, 'message' => 'Booking ID is required'], 400);
    }

    $current = get_booking_row($bookingId);
    if (!$current) {
        json_response(['success' => false, 'message' => 'Booking not found'], 404);
    }

    $updates = $payload['updates'] ?? [];
    if (isset($payload['status_label'])) {
        $updates['status_label'] = $payload['status_label'];
    }
    if (isset($payload['status'])) {
        $updates['status'] = $payload['status'];
    }

    if (empty($updates)) {
        json_response(['success' => false, 'message' => 'No updates specified'], 400);
    }

    apply_booking_updates($bookingId, $updates, $current, $user);
    json_response(['success' => true, 'message' => 'Booking updated']);
}

function handle_post(array $payload, array $user): void
{
    $action = $payload['action'] ?? null;
    if (!$action) {
        json_response(['success' => false, 'message' => 'Action is required'], 400);
    }

    switch ($action) {
        case 'reschedule':
            reschedule_single_booking($payload, $user);
            break;
        case 'bulk_reschedule':
            reschedule_bulk($payload, $user);
            break;
        case 'cancel':
            cancel_single_booking($payload, $user);
            break;
        case 'bulk_cancel':
            cancel_bulk($payload, $user);
            break;
        case 'bulk_update_status':
            bulk_update_status($payload, $user);
            break;
        case 'send_reminder':
            send_manual_reminder($payload, $user);
            break;
        case 'create_manual':
            create_manual_booking($payload, $user);
            break;
        default:
            json_response(['success' => false, 'message' => 'Unsupported action'], 400);
    }
}

function handle_delete(?array $payload, array $user): void
{
    $ids = [];
    if (!empty($payload['booking_ids']) && is_array($payload['booking_ids'])) {
        $ids = array_filter(array_map('strval', $payload['booking_ids']));
    } elseif (!empty($_GET['id'])) {
        $ids[] = trim((string) $_GET['id']);
    } elseif (!empty($payload['booking_id'])) {
        $ids[] = trim((string) $payload['booking_id']);
    }

    if (empty($ids)) {
        json_response(['success' => false, 'message' => 'Booking ID(s) required'], 400);
    }

    $conn = db();
    $conn->begin_transaction();
    try {
        $deleteAssignments = $conn->prepare("DELETE FROM booking_assignments WHERE booking_id = ?");
        $deleteBooking = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
        foreach ($ids as $bookingId) {
            $deleteAssignments->bind_param('s', $bookingId);
            $deleteAssignments->execute();
            $deleteBooking->bind_param('s', $bookingId);
            $deleteBooking->execute();
            cancel_booking_reminders($bookingId, 'Deleted via admin panel');
            insert_notification('booking_updated', $bookingId, null, [
                'message' => 'Booking deleted via admin panel',
            ]);
        }
        $deleteAssignments->close();
        $deleteBooking->close();
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    json_response([
        'success' => true,
        'message' => sprintf('Deleted %d booking(s)', count($ids)),
    ]);
}

function decode_request_body(bool $requireJson = true): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return $requireJson ? [] : [];
    }
    $decoded = json_decode($raw, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        json_response(['success' => false, 'message' => 'Invalid JSON payload'], 400);
    }
    return is_array($decoded) ? $decoded : [];
}

function reschedule_single_booking(array $payload, array $user): void
{
    $bookingId = trim((string) ($payload['booking_id'] ?? ''));
    $newDate = trim((string) ($payload['appointment_date'] ?? ($payload['new_date'] ?? '')));
    $newTime = trim((string) ($payload['appointment_time'] ?? ($payload['new_time'] ?? '')));
    $reason = trim((string) ($payload['reason'] ?? 'Rescheduled by admin'));

    if ($bookingId === '' || $newDate === '' || $newTime === '') {
        json_response(['success' => false, 'message' => 'Booking ID, date, and time are required'], 400);
    }

    perform_reschedule_booking($bookingId, $newDate, $newTime, $reason, $user);
    json_response(['success' => true, 'message' => 'Booking rescheduled']);
}

function reschedule_bulk(array $payload, array $user): void
{
    $bookingIds = $payload['booking_ids'] ?? [];
    if (!is_array($bookingIds) || empty($bookingIds)) {
        json_response(['success' => false, 'message' => 'Booking IDs required'], 400);
    }
    $newDate = trim((string) ($payload['appointment_date'] ?? ($payload['new_date'] ?? '')));
    $newTime = trim((string) ($payload['appointment_time'] ?? ($payload['new_time'] ?? '')));
    $reason = trim((string) ($payload['reason'] ?? 'Bulk reschedule'));
    if ($newDate === '' || $newTime === '') {
        json_response(['success' => false, 'message' => 'New date and time required'], 400);
    }
    foreach ($bookingIds as $bookingId) {
        perform_reschedule_booking((string) $bookingId, $newDate, $newTime, $reason, $user, false);
    }

    json_response(['success' => true, 'message' => 'Bulk reschedule complete']);
}

function cancel_single_booking(array $payload, array $user): void
{
    $bookingId = trim((string) ($payload['booking_id'] ?? ''));
    $reason = trim((string) ($payload['reason'] ?? 'Cancelled via admin panel'));

    if ($bookingId === '') {
        json_response(['success' => false, 'message' => 'Booking ID required'], 400);
    }

    perform_cancel_booking($bookingId, $reason, $user);
    json_response(['success' => true, 'message' => 'Booking cancelled']);
}

function cancel_bulk(array $payload, array $user): void
{
    $bookingIds = $payload['booking_ids'] ?? [];
    if (!is_array($bookingIds) || empty($bookingIds)) {
        json_response(['success' => false, 'message' => 'Booking IDs required'], 400);
    }
    $reason = trim((string) ($payload['reason'] ?? 'Bulk cancellation'));
    foreach ($bookingIds as $bookingId) {
        perform_cancel_booking((string) $bookingId, $reason, $user, false);
    }
    json_response(['success' => true, 'message' => 'Bulk cancellation complete']);
}

function bulk_update_status(array $payload, array $user): void
{
    $bookingIds = $payload['booking_ids'] ?? [];
    $statusLabel = $payload['status_label'] ?? null;
    if (!is_array($bookingIds) || empty($bookingIds) || !$statusLabel) {
        json_response(['success' => false, 'message' => 'Booking IDs and status_label required'], 400);
    }

    foreach ($bookingIds as $bookingId) {
        $bookingId = trim((string) $bookingId);
        if ($bookingId === '') {
            continue;
        }
        $current = get_booking_row($bookingId);
        if (!$current) {
            continue;
        }
        apply_booking_updates($bookingId, ['status_label' => $statusLabel], $current, $user, false);
    }

    json_response(['success' => true, 'message' => 'Statuses updated']);
}

function send_manual_reminder(array $payload, array $user): void
{
    $bookingId = trim((string) ($payload['booking_id'] ?? ''));
    if ($bookingId === '') {
        json_response(['success' => false, 'message' => 'Booking ID required'], 400);
    }
    $booking = get_booking_row($bookingId);
    if (!$booking) {
        json_response(['success' => false, 'message' => 'Booking not found'], 404);
    }

    $message = $payload['message'] ?? sprintf(
        'Friendly reminder: Wasatch Cleaners is scheduled for %s at %s.',
        date('M j, Y', strtotime($booking['appointment_date'])),
        date('g:i A', strtotime($booking['appointment_time']))
    );

    $sent = send_sms_message($booking['phone'], $message);
    log_sms_attempt($bookingId, $booking['phone'], $message, $sent ? 'sent' : 'failed', $sent ? null : 'SMS provider error');

    json_response([
        'success' => true,
        'message' => $sent ? 'Reminder sent' : 'Reminder attempt logged with failure status',
    ]);
}

function create_manual_booking(array $payload, array $user): void
{
    $fields = [
        'serviceType' => $payload['service_type'] ?? 'regular',
        'frequency' => $payload['frequency'] ?? 'onetime',
        'propertyType' => $payload['property_type'] ?? 'residential',
        'bedrooms' => (string) ($payload['bedrooms'] ?? '2'),
        'bathrooms' => (string) ($payload['bathrooms'] ?? '1'),
        'date' => $payload['appointment_date'] ?? date('Y-m-d'),
        'time' => $payload['appointment_time'] ?? '09:00:00',
        'firstName' => $payload['first_name'] ?? 'Manual',
        'lastName' => $payload['last_name'] ?? 'Booking',
        'email' => $payload['email'] ?? 'manual@example.com',
        'phone' => $payload['phone'] ?? '0000000000',
        'address' => $payload['address'] ?? 'TBD',
        'city' => $payload['city'] ?? 'Salt Lake City',
        'state' => $payload['state'] ?? 'UT',
        'zip' => $payload['zip'] ?? '00000',
        'notes' => $payload['notes'] ?? 'Created via admin calendar',
    ];

    $estimated = calculate_estimated_price($fields);
    $record = map_booking_payload($fields, $estimated);
    $bookingId = persist_booking($record);

    insert_notification('booking_created', $bookingId, $record['customer_id'] ?? null, [
        'message' => 'Manual booking added from admin calendar',
    ]);

    json_response([
        'success' => true,
        'message' => 'Manual booking created',
        'booking_id' => $bookingId,
    ], 201);
}

function apply_booking_updates(string $bookingId, array $updates, array $current, array $user, bool $failIfEmpty = true): void
{
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
        'service_type' => 's',
        'frequency' => 's',
        'property_type' => 's',
        'bedrooms' => 'i',
        'bathrooms' => 'i',
        'appointment_date' => 's',
        'appointment_time' => 's',
        'duration_minutes' => 'i',
        'estimated_price' => 'd',
        'final_price' => 'd',
        'status' => 's',
        'status_label' => 's',
        'payment_status' => 's',
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
        if ($failIfEmpty) {
            json_response(['success' => false, 'message' => 'No valid fields to update'], 400);
        }
        return;
    }

    $setParts[] = "updated_at = NOW()";
    $conn = db();
    $stmt = $conn->prepare(sprintf(
        "UPDATE bookings SET %s WHERE booking_id = ?",
        implode(', ', $setParts)
    ));
    $types .= 's';
    $params[] = $bookingId;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    $messages = [];
    if (isset($updates['status_label']) && $updates['status_label'] !== $current['status_label']) {
        $messages[] = sprintf(
            'Status update: your booking %s is now %s.',
            $bookingId,
            ucwords(str_replace('_', ' ', (string) $updates['status_label']))
        );
        log_status_change(
            $bookingId,
            $current['status_label'],
            $updates['status_label'],
            'Status updated via admin panel',
            $user['id'] ?? null
        );
    }

    if (isset($updates['appointment_date']) || isset($updates['appointment_time'])) {
        $newDate = $updates['appointment_date'] ?? $current['appointment_date'];
        $newTime = $updates['appointment_time'] ?? $current['appointment_time'];
        schedule_booking_reminder($bookingId, $current['phone'], $newDate, $newTime);
        $messages[] = sprintf(
            'Your appointment is confirmed for %s at %s.',
            date('M j, Y', strtotime($newDate)),
            date('g:i A', strtotime($newTime))
        );
    }

    if ($messages) {
        $customerMessage = implode(' ', $messages);
        $staffMessage = sprintf('Booking %s has been updated.', $bookingId);
        send_sms_message($current['phone'], $customerMessage);
        foreach (get_assigned_staff_contacts($bookingId) as $staffContact) {
            send_sms_message($staffContact['phone'], $staffMessage);
        }
    }

    insert_notification(
        'booking_updated',
        $bookingId,
        $current['customer_id'] ? (int) $current['customer_id'] : null,
        [
            'message' => 'Booking updated via admin panel',
            'updated_fields' => array_keys($updates),
        ]
    );
}

function perform_reschedule_booking(string $bookingId, string $newDate, string $newTime, string $reason, array $user, bool $failIfMissing = true): void
{
    $current = get_booking_row($bookingId);
    if (!$current) {
        if ($failIfMissing) {
            json_response(['success' => false, 'message' => 'Booking not found'], 404);
        }
        return;
    }

    $conn = db();
    $stmt = $conn->prepare("
        UPDATE bookings
        SET appointment_date = ?, appointment_time = ?, status_label = 'scheduled', updated_at = NOW()
        WHERE booking_id = ?
    ");
    $stmt->bind_param('sss', $newDate, $newTime, $bookingId);
    $stmt->execute();
    $stmt->close();

    log_status_change(
        $bookingId,
        $current['status_label'],
        'scheduled',
        sprintf('Rescheduled: %s', $reason),
        $user['id'] ?? null
    );

    schedule_booking_reminder($bookingId, $current['phone'], $newDate, $newTime);
    notify_booking_rescheduled($bookingId, $current['appointment_date'], $current['appointment_time'], $newDate, $newTime);

    $message = sprintf(
        'Updated schedule: %s now on %s at %s.',
        $bookingId,
        date('M j, Y', strtotime($newDate)),
        date('g:i A', strtotime($newTime))
    );

    send_sms_message($current['phone'], $message);
    foreach (get_assigned_staff_contacts($bookingId) as $staffContact) {
        send_sms_message($staffContact['phone'], $message);
    }

    insert_notification('booking_updated', $bookingId, $current['customer_id'] ? (int) $current['customer_id'] : null, [
        'message' => 'Booking rescheduled',
        'reason' => $reason,
        'appointment_date' => $newDate,
        'appointment_time' => $newTime,
    ]);
}

function perform_cancel_booking(string $bookingId, string $reason, array $user, bool $failIfMissing = true): void
{
    $current = get_booking_row($bookingId);
    if (!$current) {
        if ($failIfMissing) {
            json_response(['success' => false, 'message' => 'Booking not found'], 404);
        }
        return;
    }

    $conn = db();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("
            UPDATE bookings
            SET status = 'cancelled', status_label = 'cancelled', updated_at = NOW()
            WHERE booking_id = ?
        ");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $stmt->close();

        $deleteAssignments = $conn->prepare("DELETE FROM booking_assignments WHERE booking_id = ?");
        $deleteAssignments->bind_param('s', $bookingId);
        $deleteAssignments->execute();
        $deleteAssignments->close();

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    log_status_change(
        $bookingId,
        $current['status_label'],
        'cancelled',
        $reason,
        $user['id'] ?? null
    );
    cancel_booking_reminders($bookingId, 'Cancelled');
    notify_booking_cancelled($bookingId, $reason);

    $message = sprintf('Booking %s has been cancelled. %s', $bookingId, $reason);
    send_sms_message($current['phone'], $message);
    foreach (get_assigned_staff_contacts($bookingId) as $staffContact) {
        send_sms_message($staffContact['phone'], $message);
    }

    insert_notification('booking_cancelled', $bookingId, $current['customer_id'] ? (int) $current['customer_id'] : null, [
        'message' => $reason,
    ]);
}

function get_booking_row(string $bookingId): ?array
{
    $conn = db();
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ? LIMIT 1");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function get_assigned_staff_contacts(string $bookingId): array
{
    $conn = db();
    $stmt = $conn->prepare("
        SELECT s.id, s.phone, CONCAT(s.first_name, ' ', s.last_name) AS name
        FROM booking_assignments ba
        JOIN staff s ON s.id = ba.staff_id
        WHERE ba.booking_id = ?
    ");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = [
            'id' => (int) $row['id'],
            'phone' => $row['phone'],
            'name' => $row['name'],
        ];
    }
    $stmt->close();
    return $contacts;
}

function log_status_change(string $bookingId, ?string $previous, string $new, string $notes, ?int $adminId = null): void
{
    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO booking_status_history (booking_id, previous_status, new_status, changed_by, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssis', $bookingId, $previous, $new, $adminId, $notes);
    $stmt->execute();
    $stmt->close();
}

function insert_notification(string $type, ?string $bookingId, ?int $customerId, array $payload = []): void
{
    $conn = db();
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    $customerValue = $customerId ?? 0;

    $stmt = $conn->prepare("
        INSERT INTO notifications (type, booking_id, customer_id, payload, is_read, created_at)
        VALUES (?, ?, NULLIF(?, 0), ?, 0, NOW())
    ");
    $stmt->bind_param('ssis', $type, $bookingId, $customerValue, $jsonPayload);
    $stmt->execute();
    $stmt->close();
}

function send_sms_message(?string $phone, string $message): bool
{
    if (!$phone) {
        return false;
    }
    return send_custom_sms_openphone($phone, $message);
}

function log_sms_attempt(string $bookingId, string $phone, string $message, string $status, ?string $error): void
{
    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO sms_logs (booking_id, recipient_phone, message, status, error_message)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssss', $bookingId, $phone, $message, $status, $error);
    $stmt->execute();
    $stmt->close();
}
