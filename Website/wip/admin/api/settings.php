<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');
ensure_api_authenticated(['admin', 'manager']);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get();
        break;
    case 'POST':
        handle_post();
        break;
    default:
        json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

function handle_get(): void
{
    $conn = db();
    $result = $conn->query("SELECT * FROM business_settings ORDER BY id ASC LIMIT 1");
    $settings = $result ? $result->fetch_assoc() : null;

    if (!$settings) {
        $settings = [
            'business_name' => 'Wasatch Cleaners',
            'phone' => '(385) 213-8900',
            'email' => 'support@wasatchcleaners.com',
            'default_duration' => 180,
            'notify_email' => 1,
            'notify_sms' => 1,
        ];
    } else {
        $preferences = $settings['notification_preferences']
            ? json_decode($settings['notification_preferences'], true)
            : [];

        $settings = [
            'business_name' => $settings['business_name'],
            'phone' => $settings['phone'],
            'email' => $settings['email'],
            'default_duration' => $preferences['default_duration'] ?? 180,
            'notify_email' => $preferences['notify_email'] ?? 1,
            'notify_sms' => $preferences['notify_sms'] ?? 1,
        ];
    }

    json_response([
        'success' => true,
        'settings' => $settings,
    ]);
}

function handle_post(): void
{
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        json_response(['success' => false, 'message' => 'Invalid payload'], 400);
    }

    $preferences = [
        'default_duration' => (int) ($payload['default_duration'] ?? 180),
        'notify_email' => (int) ($payload['notify_email'] ?? 0),
        'notify_sms' => (int) ($payload['notify_sms'] ?? 0),
    ];

    $conn = db();
    $result = $conn->query("SELECT id FROM business_settings ORDER BY id ASC LIMIT 1");
    $existing = $result ? $result->fetch_assoc() : null;

    if ($existing) {
        $stmt = $conn->prepare("
            UPDATE business_settings
            SET business_name = ?, phone = ?, email = ?, notification_preferences = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $prefs = json_encode($preferences);
        $stmt->bind_param(
            'ssssi',
            $payload['business_name'],
            $payload['phone'],
            $payload['email'],
            $prefs,
            $existing['id']
        );
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO business_settings (business_name, phone, email, notification_preferences)
            VALUES (?, ?, ?, ?)
        ");
        $prefs = json_encode($preferences);
        $stmt->bind_param(
            'ssss',
            $payload['business_name'],
            $payload['phone'],
            $payload['email'],
            $prefs
        );
        $stmt->execute();
        $stmt->close();
    }

    json_response(['success' => true]);
}
