<?php
/**
 * get_booking.php
 *
 * Simple endpoint that retrieves a booking by ID.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/database.php';
require_once __DIR__ . '/security_middleware.php';

setSecurityHeaders();

$bookingId = $_GET['id'] ?? '';

if ($bookingId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Booking ID required']);
    exit;
}

$conn = db();
$stmt = $conn->prepare(
    'SELECT booking_id, service_type, frequency, appointment_date, appointment_time,
            first_name, last_name, email, phone, address, city, state, zip,
            status, payment_status, estimated_price, final_price, notes, created_at
     FROM bookings
     WHERE booking_id = ?'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare query']);
    exit;
}

$stmt->bind_param('s', $bookingId);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

echo json_encode(['success' => true, 'booking' => $booking]);
