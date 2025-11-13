<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');
ensure_api_authenticated(['admin', 'manager', 'staff']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        json_response(['success' => false, 'message' => 'Invalid payload'], 400);
    }
    handle_post($payload);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    exit;
}

$limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 50)) : 10;

$conn = db();
$unreadResult = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0");
$unreadCount = 0;
if ($unreadResult && $row = $unreadResult->fetch_assoc()) {
    $unreadCount = (int) $row['total'];
    $unreadResult->free();
}

// Use parameterized WHERE clause instead of string interpolation
$unreadOnly = !empty($_GET['unread_only']) ? 1 : 0;

if ($unreadOnly) {
    $stmt = $conn->prepare("
        SELECT id, type, booking_id, customer_id, staff_id, payload, is_read, created_at
        FROM notifications
        WHERE is_read = 0
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
} else {
    $stmt = $conn->prepare("
        SELECT id, type, booking_id, customer_id, staff_id, payload, is_read, created_at
        FROM notifications
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
}
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $payload = $row['payload'] ? json_decode($row['payload'], true) : [];
    $notifications[] = [
        'id' => (int) $row['id'],
        'type' => $row['type'],
        'title' => notification_title($row['type']),
        'message' => $payload['message'] ?? notification_message($row),
        'is_read' => (bool) $row['is_read'],
        'created_at' => date('M j, g:i A', strtotime($row['created_at'])),
    ];
}
$stmt->close();

json_response([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unreadCount,
]);

function notification_title(string $type): string
{
    return [
        'booking_created' => 'New Booking',
        'booking_updated' => 'Booking Updated',
        'booking_cancelled' => 'Booking Cancelled',
        'payment_received' => 'Payment Received',
        'staff_assignment' => 'Staff Assigned',
    ][$type] ?? 'Activity';
}

function notification_message(array $row): string
{
    switch ($row['type']) {
        case 'booking_created':
            return sprintf('Booking %s was created.', $row['booking_id']);
        case 'booking_updated':
            return sprintf('Booking %s was updated.', $row['booking_id']);
        case 'booking_cancelled':
            return sprintf('Booking %s was cancelled.', $row['booking_id']);
        case 'payment_received':
            return 'A new payment has been recorded.';
        case 'staff_assignment':
            return 'A staff assignment has been updated.';
        default:
            return 'There has been recent account activity.';
    }
}

function handle_post(array $payload): void
{
    $action = $payload['action'] ?? null;
    if (!$action) {
        json_response(['success' => false, 'message' => 'Action is required'], 400);
    }

    $conn = db();
    switch ($action) {
        case 'mark_read':
            $ids = $payload['ids'] ?? [];
            if (isset($payload['id'])) {
                $ids[] = $payload['id'];
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
            if (empty($ids)) {
                json_response(['success' => false, 'message' => 'Notification IDs required'], 400);
            }
            // Use prepared statement with placeholders instead of string interpolation
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $stmt->close();
            json_response(['success' => true, 'message' => 'Notifications marked as read']);
        case 'mark_all_read':
            $conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
            json_response(['success' => true, 'message' => 'All notifications marked as read']);
        default:
            json_response(['success' => false, 'message' => 'Unsupported action'], 400);
    }
}
