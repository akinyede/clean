<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoloader

// Include core modules
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../security_middleware.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/quickbooks.php';


// Set security headers
setSecurityHeaders();
