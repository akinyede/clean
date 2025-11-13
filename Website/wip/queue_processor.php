<?php
/**
 * Simple Queue System for Async Email/SMS Processing
 * Run as background process: php queue-processor.php &
 * Or via supervisor/systemd for production
 */

require_once 'config.php';
require_once __DIR__ . '/app/notify.php';

// Queue table schema (add to database-schema.sql):
/*
CREATE TABLE IF NOT EXISTS job_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_type ENUM('email', 'sms') NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;
*/

echo "Queue processor started at " . date('Y-m-d H:i:s') . "\n";

while (true) {
    try {
        $conn = getDatabaseConnection();
        
        // Get pending jobs
        $stmt = $conn->prepare("
            SELECT id, job_type, payload, attempts 
            FROM job_queue 
            WHERE status = 'pending' 
            AND attempts < max_attempts
            ORDER BY created_at ASC 
            LIMIT 10
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->close();
            sleep(5); // Wait 5 seconds before checking again
            continue;
        }
        
        while ($job = $result->fetch_assoc()) {
            echo "Processing job #{$job['id']} ({$job['job_type']})...\n";
            
            // Mark as processing
            updateJobStatus($job['id'], 'processing');
            
            $payload = json_decode($job['payload'], true);
            $success = false;
            $errorMsg = null;
            
            try {
                if ($job['job_type'] === 'email') {
                    $success = processEmailJob($payload);
                } elseif ($job['job_type'] === 'sms') {
                    $success = processSMSJob($payload);
                }
                
                if ($success) {
                    updateJobStatus($job['id'], 'completed');
                    echo "✓ Job #{$job['id']} completed successfully\n";
                } else {
                    throw new Exception('Job failed without error message');
                }
                
            } catch (Exception $e) {
                $errorMsg = $e->getMessage();
                $attempts = $job['attempts'] + 1;
                
                if ($attempts >= 3) {
                    updateJobStatus($job['id'], 'failed', $errorMsg);
                    echo "✗ Job #{$job['id']} failed permanently: $errorMsg\n";
                } else {
                    // Retry with exponential backoff
                    $retryDelay = pow(2, $attempts) * 60; // 2min, 4min, 8min
                    updateJobForRetry($job['id'], $attempts, $errorMsg, $retryDelay);
                    echo "⟳ Job #{$job['id']} will retry in {$retryDelay}s: $errorMsg\n";
                }
            }
            
            sleep(1); // Prevent hammering
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        echo "Queue processor error: " . $e->getMessage() . "\n";
        sleep(10);
    }
}

// ==================== HELPER FUNCTIONS ====================

function processEmailJob($payload) {
    require_once 'vendor/autoload.php';
    use PHPMailer\PHPMailer\PHPMailer;
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SMTP_FROM_EMAIL, 'Wasatch Cleaners');
        $mail->addAddress($payload['to_email'], $payload['to_name']);
        
        if (!empty($payload['attachment'])) {
            $mail->addAttachment($payload['attachment']);
        }
        
        $mail->isHTML(true);
        $mail->Subject = $payload['subject'];
        $mail->Body = $payload['html_body'];
        $mail->AltBody = $payload['text_body'] ?? '';
        
        $mail->send();
        
        // Clean up attachment
        if (!empty($payload['attachment']) && file_exists($payload['attachment'])) {
            unlink($payload['attachment']);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Email job failed: {$mail->ErrorInfo}");
        return false;
    }
}

function processSMSJob($payload) {
    $hasCredentials = defined('OPENPHONE_API_KEY') && trim((string)OPENPHONE_API_KEY) !== ''
        && defined('OPENPHONE_NUMBER') && trim((string)OPENPHONE_NUMBER) !== '';

    if (!$hasCredentials) {
        error_log('Queue SMS job failed: OpenPhone credentials missing');
        return false;
    }

    $success = send_custom_sms_openphone($payload['to_phone'], $payload['message']);

    if (!$success) {
        error_log('Queue SMS job failed: OpenPhone rejected message');
    }

    return $success;
}

function updateJobStatus($jobId, $status, $errorMsg = null) {
    $conn = getDatabaseConnection();
    
    $stmt = $conn->prepare("
        UPDATE job_queue 
        SET status = ?, 
            processed_at = NOW(),
            error_message = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param("ssi", $status, $errorMsg, $jobId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function updateJobForRetry($jobId, $attempts, $errorMsg, $retryDelay) {
    $conn = getDatabaseConnection();
    
    $stmt = $conn->prepare("
        UPDATE job_queue 
        SET status = 'pending',
            attempts = ?,
            error_message = ?,
            created_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
        WHERE id = ?
    ");
    
    $stmt->bind_param("isii", $attempts, $errorMsg, $retryDelay, $jobId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function getDatabaseConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}
