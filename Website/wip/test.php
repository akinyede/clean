<?php
/**
 * test.php
 *
 * Simple OpenPhone SMS connectivity test.
 * Run from CLI: php test.php [+1XXXXXXXXXX]
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$targetPhone = $argv[1] ?? '+19133465680';
$apiKey = defined('OPENPHONE_API_KEY') ? trim((string)OPENPHONE_API_KEY) : '';
$fromNumber = defined('OPENPHONE_NUMBER') ? trim((string)OPENPHONE_NUMBER) : '';
$baseUrl = defined('OPENPHONE_BASE_URL') && OPENPHONE_BASE_URL
    ? rtrim(OPENPHONE_BASE_URL, '/')
    : 'https://api.openphone.com/v1';

echo "==============================\n";
echo " OpenPhone SMS Diagnostics\n";
echo "==============================\n\n";

if ($apiKey === '' || $fromNumber === '') {
    echo "⚠ OpenPhone credentials are missing in config.php/.env\n";
    echo "   - OPENPHONE_API_KEY\n";
    echo "   - OPENPHONE_NUMBER\n";
    exit(1);
}

printf("Using sender: %s\n", $fromNumber);
printf("Target phone: %s\n\n", $targetPhone);

$message = sprintf(
    "Wasatch Cleaners test @ %s. This confirms OpenPhone SMS delivery is working.",
    date('Y-m-d H:i:s')
);

$response = sendOpenPhoneMessage($baseUrl, $apiKey, $fromNumber, $targetPhone, $message);

echo "Request payload:\n";
echo json_encode($response['payload'], JSON_PRETTY_PRINT) . "\n\n";

echo "HTTP status: {$response['status']}\n";

if ($response['error']) {
    echo "Error sending SMS via OpenPhone:\n";
    echo $response['error'] . "\n";
    exit(1);
}

echo "Response body:\n";
echo $response['body'] . "\n\n";

if ($response['status'] >= 200 && $response['status'] < 300) {
    echo "✅ OPENPHONE SMS SENT SUCCESSFULLY!\n";
    echo "   Check {$targetPhone} for the test message.\n";
    exit(0);
}

echo "❌ OpenPhone returned an unexpected status. Review credentials and payload.\n";
exit(1);

function sendOpenPhoneMessage(string $baseUrl, string $apiKey, string $from, string $to, string $message): array
{
    $normalized = normalize_phone_number($to);
    $payload = [
        'to' => [$normalized],
        'from' => $from,
        'content' => $message,
    ];

    $endpoint = $baseUrl . '/messages';
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . $apiKey,
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $status ?: 0,
        'body' => $body ?: '',
        'error' => $error ?: null,
        'payload' => $payload,
    ];
}

function normalize_phone_number(string $value): string
{
    $digits = preg_replace('/[^0-9]/', '', $value);

    if (strlen($digits) === 10) {
        return '+1' . $digits;
    }

    if (strlen($digits) === 11 && substr($digits, 0, 1) === '1') {
        return '+' . $digits;
    }

    return '+' . ltrim($digits, '+');
}
