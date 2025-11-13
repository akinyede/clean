<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');
ensure_api_authenticated(['admin', 'manager', 'staff']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$bookingId = $_GET['id'] ?? null;

if (!$bookingId) {
    json_response(['success' => false, 'message' => 'Booking ID required'], 400);
}

$detail = fetch_booking_detail($bookingId);

if (!$detail) {
    json_response(['success' => false, 'message' => 'Booking not found'], 404);
}
$responseData = array_merge(['success' => true], $detail);

json_response($responseData);
