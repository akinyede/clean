<?php
/**
 * Server Environment Checker for Wasatch Cleaners
 * Run this file first to verify your server meets all requirements
 * Access: https://yourdomain.com/check-server.php
 * DELETE THIS FILE after successful setup for security!
 */

$checks = [];
$errors = [];
$warnings = [];

// Check PHP Version
$phpVersion = phpversion();
$checks['PHP Version'] = $phpVersion;
if (version_compare($phpVersion, '7.4.0', '>=')) {
    $checks['PHP Version Status'] = '✓ PASS';
} else {
    $checks['PHP Version Status'] = '✗ FAIL';
    $errors[] = 'PHP 7.4 or higher required';
}

// Check MySQL
if (function_exists('mysqli_connect')) {
    $checks['MySQL Extension'] = '✓ Installed';
} else {
    $checks['MySQL Extension'] = '✗ Not Installed';
    $errors[] = 'MySQL extension required';
}

// Check cURL (for OpenPhone/QuickBooks APIs)
if (function_exists('curl_version')) {
    $checks['cURL Extension'] = '✓ Installed';
    $curlVersion = curl_version();
    $checks['cURL Version'] = $curlVersion['version'];
} else {
    $checks['cURL Extension'] = '✗ Not Installed';
    $errors[] = 'cURL extension required for SMS and payments';
}

// Check OpenSSL (for HTTPS)
if (extension_loaded('openssl')) {
    $checks['OpenSSL'] = '✓ Installed';
} else {
    $checks['OpenSSL'] = '✗ Not Installed';
    $errors[] = 'OpenSSL required for secure connections';
}

// Check JSON
if (function_exists('json_encode')) {
    $checks['JSON Extension'] = '✓ Installed';
} else {
    $checks['JSON Extension'] = '✗ Not Installed';
    $errors[] = 'JSON extension required';
}

// Check file permissions
$tempDir = __DIR__ . '/temp';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0755, true);
}

if (is_writable($tempDir)) {
    $checks['temp/ Directory Writable'] = '✓ PASS';
} else {
    $checks['temp/ Directory Writable'] = '✗ FAIL';
    $errors[] = 'temp/ directory must be writable (755)';
}

// Check config.php
if (file_exists(__DIR__ . '/config.php')) {
    $checks['config.php exists'] = '✓ PASS';
    
    // Check if configured
    require_once __DIR__ . '/config.php';
    if (defined('DB_HOST') && DB_HOST !== 'localhost') {
        $checks['config.php configured'] = '✓ Configured';
    } else {
        $checks['config.php configured'] = '⚠ Needs Configuration';
        $warnings[] = 'config.php needs to be configured with your credentials';
    }
} else {
    $checks['config.php exists'] = '✗ Missing';
    $errors[] = 'config.php file not found';
}

// Check vendor directory (dependencies)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    $checks['Dependencies Installed'] = '✓ PASS';
} else {
    $checks['Dependencies Installed'] = '⚠ Missing';
    $warnings[] = 'Run composer install to install dependencies';
}

// Check PHPMailer
if (file_exists(__DIR__ . '/vendor/phpmailer/phpmailer')) {
    $checks['PHPMailer'] = '✓ Installed';
} else {
    $checks['PHPMailer'] = '✗ Not Installed';
    $warnings[] = 'PHPMailer not found - emails will not work';
}

// Check QuickBooks helper
if (file_exists(__DIR__ . '/app/quickbooks.php')) {
    $checks['QuickBooks Helper'] = '✓ Available';
} else {
    $checks['QuickBooks Helper'] = '✗ Missing';
    $warnings[] = 'QuickBooks helper file (app/quickbooks.php) is missing';
}

// Check TCPDF
if (file_exists(__DIR__ . '/vendor/tecnickcom/tcpdf')) {
    $checks['TCPDF'] = '✓ Installed';
} else {
    $checks['TCPDF'] = '✗ Not Installed';
    $warnings[] = 'TCPDF not found - PDF invoices will not work';
}

// Check PHP settings
$maxExecutionTime = ini_get('max_execution_time');
$checks['Max Execution Time'] = $maxExecutionTime . ' seconds';
if ($maxExecutionTime < 300) {
    $warnings[] = 'Consider increasing max_execution_time to 300 seconds';
}

$memoryLimit = ini_get('memory_limit');
$checks['Memory Limit'] = $memoryLimit;

$uploadMaxSize = ini_get('upload_max_filesize');
$checks['Upload Max Size'] = $uploadMaxSize;

$postMaxSize = ini_get('post_max_size');
$checks['Post Max Size'] = $postMaxSize;

// Check database connection (if config exists)
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    
    if (defined('DB_HOST')) {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            $checks['Database Connection'] = '✗ FAIL';
            $errors[] = 'Cannot connect to database: ' . $conn->connect_error;
        } else {
            $checks['Database Connection'] = '✓ Connected';
            
            // Check if tables exist
            $result = $conn->query("SHOW TABLES LIKE 'bookings'");
            if ($result && $result->num_rows > 0) {
                $checks['Database Tables'] = '✓ Imported';
            } else {
                $checks['Database Tables'] = '⚠ Not Imported';
                $warnings[] = 'Database tables not found - import database-schema.sql';
            }
            
            $conn->close();
        }
    }
}

// Check .htaccess
if (file_exists(__DIR__ . '/.htaccess')) {
    $checks['.htaccess exists'] = '✓ PASS';
} else {
    $checks['.htaccess exists'] = '⚠ Missing';
    $warnings[] = '.htaccess file recommended for security';
}

// Test email configuration
if (defined('SMTP_USERNAME') && SMTP_USERNAME !== 'your-email@gmail.com') {
    $checks['Email Configured'] = '✓ Configured';
} else {
    $checks['Email Configured'] = '⚠ Not Configured';
    $warnings[] = 'Email settings need to be configured in config.php';
}

// Test SMS configuration
if (
    defined('OPENPHONE_API_KEY') && trim((string)OPENPHONE_API_KEY) !== '' &&
    defined('OPENPHONE_NUMBER') && trim((string)OPENPHONE_NUMBER) !== ''
) {
    $checks['SMS Configured'] = '? Configured';
} else {
    $checks['SMS Configured'] = '? Not Configured';
    $warnings[] = 'OpenPhone SMS settings need to be configured in config.php';
}
// Test QuickBooks configuration
$quickBooksReady = false;
if (file_exists(__DIR__ . '/app/quickbooks.php')) {
    require_once __DIR__ . '/app/quickbooks.php';
    try {
        $quickBooksReady = quickbooks_client()->isConfigured();
    } catch (Throwable $exception) {
        $warnings[] = 'QuickBooks client not available: ' . $exception->getMessage();
    }
}
if ($quickBooksReady) {
    $checks['QuickBooks Configured'] = '? Configured';
} else {
    $checks['QuickBooks Configured'] = '? Not Configured';
    $warnings[] = 'QuickBooks settings need to be configured in .env / config.php';
}
// Timezone check
$timezone = date_default_timezone_get();
$checks['Timezone'] = $timezone;

// Server software
$checks['Server Software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';

// Current time
$checks['Server Time'] = date('Y-m-d H:i:s');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wasatch Cleaners - Server Check</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .content {
            padding: 30px;
        }
        
        .status-section {
            margin-bottom: 30px;
        }
        
        .status-section h2 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #14b8a6;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .status-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .status-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #14b8a6;
        }
        
        .status-item.error {
            border-left-color: #ef4444;
            background: #fee;
        }
        
        .status-item.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        
        .status-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .status-value {
            color: #333;
            font-size: 16px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        
        .alert h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .alert ul {
            margin-left: 20px;
        }
        
        .alert li {
            margin-bottom: 5px;
        }
        
        .next-steps {
            background: #f0f9ff;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        
        .next-steps h3 {
            color: #1e40af;
            margin-bottom: 15px;
        }
        
        .next-steps ol {
            margin-left: 20px;
        }
        
        .next-steps li {
            margin-bottom: 10px;
            color: #1e3a8a;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #e5e7eb;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Server Environment Check</h1>
            <p>Wasatch Cleaners Booking System</p>
        </div>
        
        <div class="content">
            <?php if (empty($errors)): ?>
                <div class="alert alert-success">
                    <h3>✓ All Critical Requirements Met!</h3>
                    <p>Your server meets all the requirements to run the booking system.</p>
                </div>
            <?php else: ?>
                <div class="alert alert-error">
                    <h3>✗ Critical Errors Found</h3>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin-top: 15px;"><strong>Fix these issues before proceeding.</strong></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($warnings)): ?>
                <div class="alert alert-warning">
                    <h3>⚠ Warnings</h3>
                    <ul>
                        <?php foreach ($warnings as $warning): ?>
                            <li><?php echo htmlspecialchars($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="status-section">
                <h2>System Information</h2>
                <div class="status-grid">
                    <?php foreach ($checks as $label => $value): ?>
                        <div class="status-item <?php 
                            if (strpos($value, '✗') !== false) echo 'error';
                            elseif (strpos($value, '⚠') !== false) echo 'warning';
                        ?>">
                            <div class="status-label"><?php echo htmlspecialchars($label); ?></div>
                            <div class="status-value"><?php echo htmlspecialchars($value); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="next-steps">
                <h3>📋 Next Steps</h3>
                <ol>
                    <li>If you see errors above, fix them first</li>
                    <li>Configure <code>config.php</code> with your credentials</li>
                    <li>Install dependencies using composer or manually</li>
                    <li>Import <code>database-schema.sql</code> into your database</li>
                    <li>Test the booking form at <code>/booking.html</code></li>
                    <li><strong>Delete this file (check-server.php) for security!</strong></li>
                </ol>
            </div>
            
            <div class="alert alert-warning" style="margin-top: 20px;">
                <h3>🔒 Security Warning</h3>
                <p><strong>DELETE this file immediately after checking your setup!</strong></p>
                <p>This file exposes sensitive server information and should not be accessible in production.</p>
            </div>
        </div>
        
        <div class="footer">
            <p>Wasatch Cleaners Server Check v1.0</p>
            <p>Run this check after uploading files to verify your server environment</p>
        </div>
    </div>
</body>
</html>
