<?php
/**
 * cron_reminders.php
 *
 * Sends scheduled SMS reminders for upcoming bookings.
 * Intended to run from cron every hour: php cron_reminders.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/database.php';
require_once __DIR__ . '/app/notify.php';
require_once __DIR__ . '/logger.php';

$logger = getLogger();
$isCli = php_sapi_name() === 'cli';
$cronSecret = defined('CRON_SECRET') ? CRON_SECRET : null;

// Basic access control for web-triggered execution.
if (!$isCli) {
    $provided = $_GET['cron_key'] ?? '';
    if ($cronSecret === null || !hash_equals($cronSecret, $provided)) {
        http_response_code(403);
        echo 'Unauthorized';
        exit;
    }
}

$logger->info('Reminder job started', ['pid' => getmypid(), 'cli' => $isCli]);

$conn = db();

$stmt = $conn->prepare(
    "SELECT id, booking_id, phone, message
     FROM scheduled_reminders
     WHERE status = 'pending'
       AND send_at <= NOW()
     ORDER BY send_at ASC
     LIMIT 100"
);

if (!$stmt) {
    $logger->error('Failed to prepare reminder query', ['error' => $conn->error]);
    exit(1);
}

$stmt->execute();
$result = $stmt->get_result();

$sentCount = 0;
$failedCount = 0;

while ($reminder = $result->fetch_assoc()) {
    $logger->info('Sending reminder', ['booking_id' => $reminder['booking_id'], 'id' => $reminder['id']]);

    $success = sendReminderSms($reminder['phone'], $reminder['message']);
    $status = $success ? 'sent' : 'failed';
    $errorMessage = $success ? null : 'SMS provider error';

    updateReminderStatus((int) $reminder['id'], $status, $errorMessage);
    logSmsAttempt($reminder['booking_id'], $reminder['phone'], $reminder['message'], $status, $errorMessage);

    $success ? $sentCount++ : $failedCount++;

    sleep(1); // avoid provider rate limits
}

$stmt->close();

$logger->info('Reminder job finished', ['sent' => $sentCount, 'failed' => $failedCount]);

exit(0);

function sendReminderSms(string $phone, string $message): bool
{
    $hasCredentials = defined('OPENPHONE_API_KEY') && trim((string)OPENPHONE_API_KEY) !== ''
        && defined('OPENPHONE_NUMBER') && trim((string)OPENPHONE_NUMBER) !== '';

    if (!$hasCredentials) {
        getLogger()->warning('OpenPhone SMS credentials missing; reminder not sent', ['phone' => $phone]);
        return false;
    }

    $sent = send_custom_sms_openphone($phone, $message);

    if (!$sent) {
        getLogger()->warning('OpenPhone SMS failed during reminder send', ['phone' => $phone]);
    }

    return $sent;
}

function updateReminderStatus(int $id, string $status, ?string $errorMessage = null): void
{
    $conn = db();
    $stmt = $conn->prepare(
        "UPDATE scheduled_reminders
         SET status = ?, sent_at = NOW(), error_message = ?
         WHERE id = ?"
    );

    if (!$stmt) {
        getLogger()->error('Failed to prepare reminder update', ['error' => $conn->error, 'id' => $id]);
        return;
    }

    $stmt->bind_param('ssi', $status, $errorMessage, $id);
    $stmt->execute();
    $stmt->close();
}

function logSmsAttempt(string $bookingId, string $phone, string $message, string $status, ?string $errorMessage): void
{
    $conn = db();
    $stmt = $conn->prepare(
        "INSERT INTO sms_logs (booking_id, recipient_phone, message, status, error_message)
         VALUES (?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        getLogger()->error('Failed to prepare SMS log insert', ['error' => $conn->error]);
        return;
    }

    $stmt->bind_param('sssss', $bookingId, $phone, $message, $status, $errorMessage);
    $stmt->execute();
    $stmt->close();
}
