<?php
/**
 * submit-booking.php - AJAX endpoint for booking submission.
 */
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    json_response(['success' => false, 'message' => 'Invalid request payload'], 400);
}

if (empty($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
    json_response(['success' => false, 'message' => 'Security check failed. Please refresh and try again.'], 403);
}

$rateIdentifier = getRateLimitIdentifier();
if (!checkRateLimit($rateIdentifier, 5, 300)) {
    json_response(['success' => false, 'message' => 'Too many attempts. Please wait a few minutes and try again.'], 429);
}

$validation = validateBookingInput($data);
if (!$validation['valid']) {
    json_response([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $validation['errors'],
    ], 422);
}

$bookingData = $validation['data'];
$estimatedPrice = calculate_estimated_price($bookingData);

if ($estimatedPrice <= 0) {
    json_response(['success' => false, 'message' => 'Unable to calculate pricing for the selected options.'], 422);
}

$record = map_booking_payload($bookingData, $estimatedPrice);
$record['customer_id'] = upsert_customer([
    'firstName' => $bookingData['firstName'],
    'lastName' => $bookingData['lastName'],
    'email' => $bookingData['email'],
    'phone' => $bookingData['phone'],
    'address' => $bookingData['address'],
    'city' => $bookingData['city'],
    'state' => $bookingData['state'],
    'zip' => $bookingData['zip'],
    'notes' => $bookingData['notes'] ?? null,
]);

try {
    $bookingId = persist_booking($record);

    // Send confirmation email to customer
    send_booking_confirmation_email($bookingData, $bookingId, $estimatedPrice);

    // Send SMS confirmation to customer
    send_booking_sms_confirmation($bookingData, $bookingId);

    // Notify admin about new booking
    send_admin_booking_alert($bookingId, $bookingData);

    // Schedule reminders for client and staff (this is where we connect to the reminder system)
    $reminderMessage = sprintf(
        'Wasatch Cleaners: Reminder - we are scheduled for %s at %s. Questions? Call (385)213-8900.',
        date('M j, Y', strtotime($bookingData['date'])),
        date('g:i A', strtotime($bookingData['time']))
    );
    schedule_booking_reminder(
        $bookingId,
        $bookingData['phone'],
        $bookingData['date'],
        $bookingData['time'],
        $reminderMessage,
        20
    );

    json_response([
        'success' => true,
        'bookingId' => $bookingId,
        'estimatedPrice' => $estimatedPrice,
        'message' => "Booking confirmed! Check your email for confirmation details.",
    ]);

} catch (Throwable $exception) {
    getLogger()->error('Booking persistence failed', [
        'error' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString(),
    ]);
    json_response([
        'success' => false,
        'message' => 'We hit a snag saving your booking. Please call us directly at (385) 213-8900.',
    ], 500);
}

