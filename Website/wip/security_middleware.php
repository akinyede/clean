<?php

/**
 * Security Middleware for Wasatch Cleaners
 * Include this at the top of all public-facing PHP files
 */

// Don't require database.php here - it's already loaded in bootstrap.php

// Security configuration
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// Start secure session
function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    // Session security settings
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_lifetime', '0'); // Session cookie
    ini_set('session.gc_maxlifetime', '1800'); // 30 minutes

    session_start();

    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Generate CSRF token with expiry
function generateCSRFToken(): string
{
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_expiry']) || $_SESSION['csrf_token_expiry'] < time()) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expiry'] = time() + CSRF_TOKEN_EXPIRY;
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_expiry'])) {
        return false;
    }

    if ($_SESSION['csrf_token_expiry'] < time()) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expiry']);
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

// Validate and sanitize input
function sanitizeInput($input, $type = 'string'): mixed
{
    if ($input === null) {
        return null;
    }

    switch ($type) {
        case 'email':
            $input = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            return filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : null;

        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT);

        case 'url':
            $input = filter_var(trim($input), FILTER_SANITIZE_URL);
            return filter_var($input, FILTER_VALIDATE_URL) ? $input : null;

        case 'phone':
            $input = preg_replace('/[^\d]/', '', $input);
            return (strlen($input) >= 10 && strlen($input) <= 15) ? $input : null;

        case 'alphanumeric':
            return preg_replace('/[^a-zA-Z0-9]/', '', $input);

        case 'string':
        default:
            $input = trim($input);
            // stripslashes() removed - use prepared statements for SQL, htmlspecialchars for output
            return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// Advanced rate limiting with IP validation
function checkRateLimit(string $identifier, int $maxAttempts = 5, int $timeWindow = 300): bool
{
    // Validate IP address
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        error_log("Invalid IP address: {$ip}");
        return false; // Block invalid IPs
    }

    try {
        $conn = db();
        
        // Clean old entries
        $conn->query("DELETE FROM rate_limits WHERE expires_at < NOW()");

        // Check current count
        $stmt = $conn->prepare("
            SELECT attempts, expires_at FROM rate_limits 
            WHERE identifier = ? AND expires_at > NOW()
        ");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // First attempt
            $expiresAt = date('Y-m-d H:i:s', time() + $timeWindow);
            $stmt = $conn->prepare("
                INSERT INTO rate_limits (identifier, attempts, expires_at, ip_address)
                VALUES (?, 1, ?, ?)
            ");
            $stmt->bind_param("sss", $identifier, $expiresAt, $ip);
            $stmt->execute();
            $stmt->close();
            return true;
        }

        $row = $result->fetch_assoc();
        $attempts = $row['attempts'];
        $stmt->close();

        if ($attempts >= $maxAttempts) {
            // Log excessive attempts
            error_log("Rate limit exceeded for identifier: {$identifier}, IP: {$ip}");
            return false;
        }

        // Increment attempts
        $stmt = $conn->prepare("
            UPDATE rate_limits 
            SET attempts = attempts + 1 
            WHERE identifier = ?
        ");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $stmt->close();
        return true;

    } catch (Exception $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return true; // Allow request if rate limiting fails
    }
}

// Enhanced booking input validation
function validateBookingInput(array $data): array
{
    $errors = [];
    $sanitizedData = [];

    // Required fields
    $required = [
        'serviceType', 'frequency', 'propertyType', 'bedrooms', 'bathrooms',
        'date', 'time', 'firstName', 'lastName', 'email', 'phone',
        'address', 'city', 'state', 'zip'
    ];

    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Missing required field: {$field}";
        }
    }

    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors, 'data' => $data];
    }

    // Sanitize and validate individual fields
    $sanitizedData['firstName'] = sanitizeInput($data['firstName']);
    $sanitizedData['lastName'] = sanitizeInput($data['lastName']);
    $sanitizedData['email'] = sanitizeInput($data['email'], 'email');
    $sanitizedData['phone'] = sanitizeInput($data['phone'], 'phone');
    $sanitizedData['address'] = sanitizeInput($data['address']);
    $sanitizedData['city'] = sanitizeInput($data['city']);
    $sanitizedData['state'] = sanitizeInput($data['state']);
    $sanitizedData['zip'] = sanitizeInput($data['zip']);
    $sanitizedData['serviceType'] = sanitizeInput($data['serviceType']);
    $sanitizedData['frequency'] = sanitizeInput($data['frequency']);
    $sanitizedData['propertyType'] = sanitizeInput($data['propertyType']);
    $sanitizedData['bedrooms'] = sanitizeInput($data['bedrooms'], 'int');
    $sanitizedData['bathrooms'] = sanitizeInput($data['bathrooms'], 'int');
    $sanitizedData['date'] = sanitizeInput($data['date']);
    $sanitizedData['time'] = sanitizeInput($data['time']);
    $sanitizedData['notes'] = !empty($data['notes']) ? sanitizeInput($data['notes']) : null;

    // Email validation
    if (!$sanitizedData['email']) {
        $errors[] = "Invalid email address";
    }

    // Phone validation
    if (!$sanitizedData['phone']) {
        $errors[] = "Invalid phone number format";
    }

    // Date validation (must be today or future date)
    $bookingDate = strtotime($sanitizedData['date']);
    $today = strtotime('today');
    if ($bookingDate === false || $bookingDate < $today) {
        $errors[] = "Booking date must be today or in the future";
    }

    // Service type validation
    $validServices = ['regular', 'deep', 'move', 'onetime'];
    if (!in_array($sanitizedData['serviceType'], $validServices)) {
        $errors[] = "Invalid service type";
    }

    // Frequency validation
    $validFrequencies = ['weekly', 'biweekly', 'monthly', 'onetime'];
    if (!in_array($sanitizedData['frequency'], $validFrequencies)) {
        $errors[] = "Invalid frequency";
    }

    // Bedroom/bathroom validation
    if (!in_array((string)$sanitizedData['bedrooms'], ['1', '2', '3', '4', '5'])) {
        $errors[] = "Invalid bedroom count";
    }
    if (!in_array((string)$sanitizedData['bathrooms'], ['1', '2', '3', '4'])) {
        $errors[] = "Invalid bathroom count";
    }

    // ZIP code validation
    if (!preg_match('/^\d{5}(-\d{4})?$/', $sanitizedData['zip'])) {
        $errors[] = "Invalid ZIP code";
    }

    // Name length validation
    if (strlen($sanitizedData['firstName']) > 100 || strlen($sanitizedData['lastName']) > 100) {
        $errors[] = "Name too long";
    }

    // Address length validation
    if (strlen($sanitizedData['address']) > 255) {
        $errors[] = "Address too long";
    }

    // Notes length validation
    if (!empty($sanitizedData['notes']) && strlen($sanitizedData['notes']) > 1000) {
        $errors[] = "Notes too long (max 1000 characters)";
    }

    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors, 'data' => $sanitizedData];
    }

    return ['valid' => true, 'data' => $sanitizedData, 'errors' => []];
}

// Sanitize output for HTML
function sanitizeOutput($value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Enhanced security headers
function setSecurityHeaders(): void
{
    // Remove server identification
    header_remove('X-Powered-By');
    header_remove('Server');

    // Security headers
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // HSTS - only on HTTPS
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Content Security Policy - Improved without unsafe-inline for scripts
    // Removed data: from img-src to prevent data exfiltration
    // Note: Tailwind CSS from CDN may require style-src 'unsafe-inline' temporarily
    header("Content-Security-Policy: default-src 'self' https:; script-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; img-src 'self' https:; font-src 'self' https:; connect-src 'self' https:; frame-src 'self';");
    
    // Permissions Policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// Secure file upload validation
function validateFileUpload(array $file): array
{
    $errors = [];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed with error code: {$file['error']}";
        return ['valid' => false, 'errors' => $errors];
    }

    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $errors[] = "File size exceeds maximum allowed size";
    }

    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_FILE_TYPES)) {
        $errors[] = "File type not allowed";
    }

    // Check file extension
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        $errors[] = "File extension not allowed";
    }

    // Check for double extensions
    if (preg_match('/\.(php|phtml|php3|php4|php5|phar|html|htm)/i', $file['name'])) {
        $errors[] = "Potentially dangerous file name";
    }

    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }

    return ['valid' => true, 'errors' => []];
}

// IP-based rate limiting identifier with additional security
function getRateLimitIdentifier(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Additional factors for better identification
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    
    return hash('sha256', $ip . $userAgent . $acceptLanguage . $acceptEncoding);
}

// SQL injection prevention helper
function escapeSqlValue($value, string $type = 'string'): string
{
    static $conn = null;
    if ($conn === null) {
        $conn = db();
    }

    if ($value === null) {
        return 'NULL';
    }

    switch ($type) {
        case 'int':
            return (string)intval($value);
        case 'float':
            return (string)floatval($value);
        case 'bool':
            return $value ? '1' : '0';
        case 'string':
        default:
            return "'" . $conn->real_escape_string((string)$value) . "'";
    }
}

// Prevent directory traversal
function safePath(string $path): string
{
    // Normalize path
    $path = str_replace('\\', '/', $path);
    
    // Remove directory traversal attempts
    $path = preg_replace('#/\.\.?#', '/', $path);
    
    // Remove multiple slashes
    $path = preg_replace('#/+#', '/', $path);
    
    return $path;
}
