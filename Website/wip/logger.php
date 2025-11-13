<?php
/**
 * Centralized Logging System for Wasatch Cleaners
 * PSR-3 compatible logger with enhanced security and performance
 */

class Logger {
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';
    
    private $logFile;
    private $dbLogging = true;
    private $minLevel;
    private $logBuffer = [];
    private $bufferSize = 10;
    private $maxFileSize = 10485760; // 10MB
    
    public function __construct($logFile = 'logs/app.log', $minLevel = self::INFO) {
        $this->logFile = $this->validateLogPath($logFile);
        $this->minLevel = $minLevel;
        
        // Ensure log directory exists
        $this->ensureLogDirectory();
        
        // Check for log rotation
        $this->rotateLogsIfNeeded();
        
        // Register shutdown function to flush buffer
        register_shutdown_function([$this, 'flushLogBuffer']);
    }
    
    public function debug($message, array $context = []) {
        $this->log(self::DEBUG, $message, $context);
    }
    
    public function info($message, array $context = []) {
        $this->log(self::INFO, $message, $context);
    }
    
    public function warning($message, array $context = []) {
        $this->log(self::WARNING, $message, $context);
    }
    
    public function error($message, array $context = []) {
        $this->log(self::ERROR, $message, $context);
    }
    
    public function critical($message, array $context = []) {
        $this->log(self::CRITICAL, $message, $context);
        
        // Send alert for critical errors
        $this->sendCriticalAlert($message, $context);
    }
    
    private function log($level, $message, array $context = []) {
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $requestId = $this->getRequestId();
        $userId = $this->getUserId();
        
        // Format log entry
        $logEntry = sprintf(
            "[%s] [%s] [ReqID: %s] [User: %s] %s %s\n",
            $timestamp,
            $level,
            $requestId,
            $userId,
            $message,
            !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );
        
        // Add to buffer
        $this->logBuffer[] = $logEntry;
        
        // Flush buffer if critical or buffer full
        if (count($this->logBuffer) >= $this->bufferSize || $level === self::CRITICAL) {
            $this->flushLogBuffer();
        }
        
        // Write to database for important logs
        if ($this->dbLogging && in_array($level, [self::ERROR, self::CRITICAL, self::WARNING])) {
            $this->logToDatabase($level, $message, $context, $requestId);
        }
    }
    
    public function flushLogBuffer() {
        if (empty($this->logBuffer)) {
            return;
        }
        
        $content = implode('', $this->logBuffer);
        @file_put_contents($this->logFile, $content, FILE_APPEND | LOCK_EX);
        $this->logBuffer = [];
    }
    
    private function shouldLog($level) {
        $levels = [
            self::DEBUG => 0,
            self::INFO => 1,
            self::WARNING => 2,
            self::ERROR => 3,
            self::CRITICAL => 4
        ];
        
        return $levels[$level] >= $levels[$this->minLevel];
    }
    
    private function logToDatabase($level, $message, $context, $requestId) {
        try {
            // Use the db() function from database.php
            $conn = db();
            
            // Ensure log table exists
            $this->ensureLogTableExists($conn);
            
            $stmt = $conn->prepare("
                INSERT INTO application_logs (level, message, context, request_id, user_id, ip_address, user_agent, url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : null;
            $userId = $this->getUserId();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500);
            $url = substr($_SERVER['REQUEST_URI'] ?? 'unknown', 0, 500);
            
            $stmt->bind_param(
                "ssssssss",
                $level,
                $message,
                $contextJson,
                $requestId,
                $userId,
                $ip,
                $userAgent,
                $url
            );
            
            $stmt->execute();
            $stmt->close();
            
        } catch (Exception $e) {
            // Don't break application if logging fails
            error_log("Failed to log to database: " . $e->getMessage());
        }
    }
    
    private function ensureLogTableExists($conn) {
        static $tableChecked = false;
        
        if ($tableChecked) {
            return;
        }
        
        try {
            $conn->query("
                CREATE TABLE IF NOT EXISTS application_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') NOT NULL,
                    message TEXT NOT NULL,
                    context JSON,
                    request_id VARCHAR(64),
                    user_id VARCHAR(255),
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    url TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_level (level),
                    INDEX idx_created_at (created_at),
                    INDEX idx_request_id (request_id),
                    INDEX idx_user_id (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $tableChecked = true;
        } catch (Exception $e) {
            error_log("Failed to create log table: " . $e->getMessage());
        }
    }
    
    private function validateLogPath($path) {
        $baseDir = __DIR__ . '/logs/';
        $logDir = dirname($path);
        
        // Prevent directory traversal
        if (strpos(realpath($logDir) ?: $logDir, realpath($baseDir)) !== 0) {
            return $baseDir . 'app.log';
        }
        
        return $path;
    }
    
    private function ensureLogDirectory() {
        $dir = dirname($this->logFile);
        if (!file_exists($dir)) {
            @mkdir($dir, 0755, true);
            
            // Add .htaccess protection for log directory
            if (file_exists($dir)) {
                $htaccess = $dir . '/.htaccess';
                if (!file_exists($htaccess)) {
                    @file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
                }
            }
        }
    }
    
    private function rotateLogsIfNeeded() {
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
            $this->rotateLogs();
        }
    }
    
    public function rotateLogs() {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        $backupFile = $this->logFile . '.' . date('Y-m-d-His');
        
        // Flush buffer before rotation
        $this->flushLogBuffer();
        
        if (rename($this->logFile, $backupFile)) {
            // Compress old logs in background
            $this->compressOldLogsAsync();
        }
    }
    
    private function compressOldLogsAsync() {
        // This would run in background - simplified version
        $logDir = dirname($this->logFile);
        $files = glob($logDir . '/app.log.*');
        
        foreach ($files as $file) {
            // Don't compress files from today
            if (time() - filemtime($file) > 86400 && !preg_match('/\.gz$/', $file)) {
                $compressedFile = $file . '.gz';
                if ($fp = gzopen($compressedFile, 'w9')) {
                    gzwrite($fp, file_get_contents($file));
                    gzclose($fp);
                    unlink($file);
                }
            }
        }
    }
    
    private function getRequestId() {
        if (!isset($_SERVER['REQUEST_ID'])) {
            $_SERVER['REQUEST_ID'] = bin2hex(random_bytes(8));
        }
        return $_SERVER['REQUEST_ID'];
    }
    
    private function getUserId() {
        return $_SESSION['user_id'] ?? 'anonymous';
    }
    
    private function sendCriticalAlert($message, $context) {
        $this->sendEmailAlert($message, $context);
        $this->sendSlackAlert($message, $context);
    }
    
    private function sendEmailAlert($message, $context) {
        $to = defined('COMPANY_EMAIL') ? COMPANY_EMAIL : 'hello@wasatchcleaners.com';
        $subject = '[CRITICAL] Wasatch Cleaners System Alert';
        
        $body = $this->formatAlertMessage($message, $context);
        
        $headers = [
            'From: alerts@wasatchcleaners.com',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Priority: 1 (Highest)'
        ];
        
        @mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    private function sendSlackAlert($message, $context) {
        // Slack webhook integration for critical alerts
        $webhookUrl = defined('SLACK_WEBHOOK_URL') ? SLACK_WEBHOOK_URL : null;
        
        if (!$webhookUrl) {
            return;
        }
        
        $slackMessage = [
            'text' => "🚨 CRITICAL ALERT - Wasatch Cleaners",
            'attachments' => [
                [
                    'color' => 'danger',
                    'fields' => [
                        [
                            'title' => 'Message',
                            'value' => substr($message, 0, 2000),
                            'short' => false
                        ],
                        [
                            'title' => 'Time',
                            'value' => date('Y-m-d H:i:s'),
                            'short' => true
                        ],
                        [
                            'title' => 'Server',
                            'value' => gethostname(),
                            'short' => true
                        ]
                    ]
                ]
            ]
        ];
        
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackMessage));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        @curl_exec($ch);
        curl_close($ch);
    }
    
    private function formatAlertMessage($message, $context) {
        return sprintf(
            "CRITICAL SYSTEM ALERT\n\n" .
            "Message: %s\n" .
            "Time: %s\n" .
            "Server: %s\n" .
            "Request ID: %s\n" .
            "User: %s\n" .
            "URL: %s\n" .
            "IP: %s\n\n" .
            "Context:\n%s\n\n" .
            "Please investigate immediately.\n\n" .
            "---\nWasatch Cleaners Monitoring System",
            $message,
            date('Y-m-d H:i:s'),
            gethostname(),
            $this->getRequestId(),
            $this->getUserId(),
            $_SERVER['REQUEST_URI'] ?? 'unknown',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            !empty($context) ? json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'None'
        );
    }
    
    public function setBufferSize($size) {
        $this->bufferSize = max(1, min(100, $size)); // Limit between 1-100
    }
    
    public function setMaxFileSize($size) {
        $this->maxFileSize = max(1048576, $size); // Minimum 1MB
    }
    
    public function __destruct() {
        $this->flushLogBuffer();
    }
}

// Global logger instance with singleton pattern
function getLogger() {
    static $logger = null;
    if ($logger === null) {
        $logLevel = defined('APP_ENV') && APP_ENV === 'development' ? Logger::DEBUG : Logger::INFO;
        $logFile = __DIR__ . '/logs/app.log';
        $logger = new Logger($logFile, $logLevel);
    }
    return $logger;
}

// Convenience functions for common logging scenarios
function log_info($message, array $context = []) {
    getLogger()->info($message, $context);
}

function log_error($message, array $context = []) {
    getLogger()->error($message, $context);
}

function log_warning($message, array $context = []) {
    getLogger()->warning($message, $context);
}

function log_debug($message, array $context = []) {
    getLogger()->debug($message, $context);
}

function log_critical($message, array $context = []) {
    getLogger()->critical($message, $context);
}

