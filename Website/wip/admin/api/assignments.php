<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');

try {
    $user = ensure_api_authenticated(['admin', 'manager', 'staff']);

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            handle_get_assignments();
            break;
        case 'POST':
            handle_assign_staff();
            break;
        case 'DELETE':
            handle_unassign_staff();
            break;
        default:
            json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Assignments API error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

function handle_get_assignments(): void
{
    $staffId = isset($_GET['staff_id']) ? (int) $_GET['staff_id'] : null;
    if ($staffId) {
        $assignments = fetch_staff_assignments($staffId);
        json_response([
            'success' => true,
            'assignments' => $assignments,
        ]);
    }

    $date = $_GET['date'] ?? date('Y-m-d');
    $time = $_GET['time'] ?? '00:00:00';
    $bookingId = $_GET['booking_id'] ?? '';

    $conn = db();
    
    // Get all active staff with basic availability info
    $stmt = $conn->prepare("
        SELECT 
            s.id, 
            s.first_name, 
            s.last_name, 
            s.role, 
            s.color_tag,
            COALESCE(ba_count.bookings_count, 0) as bookings_count,
            CASE WHEN COALESCE(ba_count.bookings_count, 0) < 5 THEN 'available' ELSE 'busy' END as availability_status
        FROM staff s
        LEFT JOIN (
            SELECT staff_id, COUNT(*) as bookings_count 
            FROM booking_assignments ba 
            JOIN bookings b ON ba.booking_id = b.booking_id 
            WHERE b.appointment_date = ? 
            GROUP BY staff_id
        ) ba_count ON s.id = ba_count.staff_id
        WHERE s.is_active = 1 
        ORDER BY s.first_name, s.last_name
    ");
    
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get currently assigned staff for this booking
    $assigned = [];
    if (!empty($bookingId)) {
        $stmt = $conn->prepare("
            SELECT 
                s.id, 
                CONCAT(s.first_name, ' ', s.last_name) as name,
                s.role, 
                s.color_tag,
                ba.assignment_role
            FROM booking_assignments ba
            JOIN staff s ON ba.staff_id = s.id
            WHERE ba.booking_id = ?
        ");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $assignedResult = $stmt->get_result();
        $assigned = $assignedResult->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

        foreach ($staff as &$staffMember) {
        $staffMember['name'] = $staffMember['first_name'] . ' ' . $staffMember['last_name'];
        $staffMember['color_tag'] = $staffMember['color_tag'] ?? '#6b7280';
        $staffMember['bookings_count'] = (int)($staffMember['bookings_count'] ?? 0);
    }

    json_response([
        'success' => true,
        'staff' => $staff,
        'assigned' => $assigned
    ]);
}

function handle_assign_staff(): void
{
    // Fix: Properly retrieve booking_id from JSON input or POST data
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $bookingId = $input['booking_id'] ?? $_POST['booking_id'] ?? null;
    
    if (!$bookingId) {
        json_response(['success' => false, 'message' => 'Booking ID required'], 400);
        return;
    }
    
    validate_booking_access($bookingId);
    
    $staffIds = $input['staff_ids'] ?? $_POST['staff_ids'] ?? [];
    $staffIds = array_values(array_unique(array_map('intval', (array) $staffIds)));
    $staffIds = array_filter($staffIds);
    $assignmentRole = $input['assignment_role'] ?? $_POST['assignment_role'] ?? 'assistant';

    if (empty($staffIds)) {
        json_response(['success' => false, 'message' => 'No staff IDs provided'], 400);
        return;
    }

    $conn = db();
    
    // Remove existing assignments
    $stmt = $conn->prepare("DELETE FROM booking_assignments WHERE booking_id = ?");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $stmt->close();

    // Add new assignments
    $success = true;
    foreach ($staffIds as $staffId) {
        $stmt = $conn->prepare("
            INSERT INTO booking_assignments (booking_id, staff_id, assignment_role) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('sis', $bookingId, $staffId, $assignmentRole);
        if (!$stmt->execute()) {
            $success = false;
        }
        $stmt->close();
    }

    if ($success) {
        if (($input['send_notification'] ?? true) === true) {
            $booking = fetch_booking_contact_summary($bookingId);
            $staffContacts = get_staff_contacts($staffIds);
            foreach ($staffIds as $staffId) {
                if (isset($staffContacts[$staffId])) {
                    notify_staff_assignment(
                        (string) $staffId,
                        $bookingId,
                        [
                            'appointment_date' => $booking['appointment_date'] ?? null,
                            'appointment_time' => $booking['appointment_time'] ?? null,
                            'address' => $booking['address'] ?? '',
                        ]
                    );
                }
            }
            if ($booking) {
                send_client_assignment_notice($booking, $staffContacts);
                insert_notification('staff_assignment', $bookingId, $booking['customer_id'] ?? null, [
                    'message' => 'Staff assigned to booking',
                    'staff' => array_map(fn ($staff) => $staff['name'], $staffContacts),
                ]);
            }
        }
        json_response(['success' => true, 'message' => 'Staff assigned successfully']);
    }

    json_response(['success' => false, 'message' => 'Failed to assign some staff members'], 500);
}

function handle_unassign_staff(): void
{
    // Fix: Properly retrieve parameters
    $bookingId = $_GET['booking_id'] ?? null;
    $staffId = $_GET['staff_id'] ?? null;
    
    if (!$bookingId || !$staffId) {
        json_response(['success' => false, 'message' => 'Booking ID and Staff ID required'], 400);
        return;
    }
    
    validate_booking_access($bookingId);

    $conn = db();
    $stmt = $conn->prepare("
        DELETE FROM booking_assignments 
        WHERE booking_id = ? AND staff_id = ?
    ");
    $stmt->bind_param('si', $bookingId, $staffId);
    
    if ($stmt->execute()) {
        $booking = fetch_booking_contact_summary($bookingId);
        if ($booking) {
            $staffContacts = get_staff_contacts([$staffId]);
            if (isset($staffContacts[$staffId])) {
                $message = sprintf(
                    'Update: you have been unassigned from booking %s scheduled %s %s.',
                    $bookingId,
                    $booking['appointment_date'],
                    $booking['appointment_time']
                );
                send_assignment_sms($staffContacts[$staffId]['phone'], $message);
            }
            $clientMessage = sprintf(
                'Staff update: %s will no longer attend your cleaning on %s.',
                $staffContacts[$staffId]['name'] ?? 'A team member',
                date('M j, Y', strtotime($booking['appointment_date']))
            );
            send_assignment_sms($booking['phone'], $clientMessage);
            insert_notification('staff_assignment', $bookingId, $booking['customer_id'] ?? null, [
                'message' => 'Staff unassigned from booking',
                'staff' => [$staffContacts[$staffId]['name'] ?? 'Staff'],
            ]);
        }
        json_response(['success' => true, 'message' => 'Staff unassigned successfully']);
    }

    json_response(['success' => false, 'message' => 'Failed to unassign staff'], 500);
    
    $stmt->close();
}

function validate_booking_access(string $bookingId): void
{
    if (empty($bookingId)) {
        json_response(['success' => false, 'message' => 'Booking ID cannot be empty'], 400);
        exit;
    }
    
    $user = auth_user();
    $conn = db();
    
    // Check if booking exists and user has access
    $stmt = $conn->prepare("
        SELECT b.booking_id 
        FROM bookings b
        WHERE b.booking_id = ?
    ");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();
    
    if (!$booking) {
        json_response(['success' => false, 'message' => 'Booking not found'], 404);
        exit;
    }
    
    // Add any additional access validation here based on user role
}

function fetch_staff_assignments(int $staffId): array
{
    $conn = db();
    $stmt = $conn->prepare("
        SELECT 
            b.booking_id,
            b.appointment_date,
            b.appointment_time,
            b.address,
            b.city,
            b.status_label
        FROM booking_assignments ba
        JOIN bookings b ON b.booking_id = ba.booking_id
        WHERE ba.staff_id = ?
        ORDER BY b.appointment_date DESC, b.appointment_time DESC
        LIMIT 100
    ");
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $result = $stmt->get_result();

    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = [
            'booking_id' => $row['booking_id'],
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'address' => $row['address'],
            'city' => $row['city'],
            'status_label' => $row['status_label'],
            'status_color' => status_color($row['status_label']),
        ];
    }
    $stmt->close();

    return $assignments;
}

function fetch_booking_contact_summary(string $bookingId): ?array
{
    $conn = db();
    $stmt = $conn->prepare("
        SELECT booking_id, customer_id, first_name, last_name, phone, appointment_date, appointment_time, address, city
        FROM bookings
        WHERE booking_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $row['phone'] = $row['phone'] ?? '';
    return $row;
}

function get_staff_contacts(array $staffIds): array
{
    if (empty($staffIds)) {
        return [];
    }
    $ids = array_map('intval', $staffIds);
    $ids = array_filter($ids);
    if (empty($ids)) {
        return [];
    }

    $conn = db();
    $in = implode(',', $ids);
    $result = $conn->query("
        SELECT id, CONCAT(first_name, ' ', last_name) AS name, phone
        FROM staff
        WHERE id IN ($in)
    ");

    $contacts = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $contacts[(int) $row['id']] = [
                'name' => $row['name'],
                'phone' => $row['phone'],
            ];
        }
        $result->free();
    }
    return $contacts;
}

function send_client_assignment_notice(array $booking, array $staffContacts): void
{
    if (empty($booking['phone']) || empty($staffContacts)) {
        return;
    }
    $names = array_map(fn ($staff) => $staff['name'], $staffContacts);
    $message = sprintf(
        'Team update: %s will clean your home on %s at %s.',
        implode(', ', $names),
        date('M j, Y', strtotime($booking['appointment_date'])),
        date('g:i A', strtotime($booking['appointment_time']))
    );
    send_assignment_sms($booking['phone'], $message);
}

function send_assignment_sms(?string $phone, string $message): void
{
    if (!$phone) {
        return;
    }
    $normalized = normalize_phone($phone);
    if ($normalized === '') {
        return;
    }
    send_custom_sms_openphone($normalized, $message);
}

function insert_notification(string $type, string $bookingId, ?int $customerId, array $payload = []): void
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
