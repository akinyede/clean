<?php
/**
 * Lightweight database helper for the Wasatch Cleaners application.
 * Provides a shared mysqli instance with UTF-8 configuration.
 */

require_once __DIR__ . '/../config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Get (or create) the shared mysqli connection.
 */
function db(): mysqli {
    static $connection = null;
    
    if ($connection instanceof mysqli) {
        // Check if connection is still alive
        try {
            if ($connection->ping()) {
                return $connection;
            }
        } catch (Exception $e) {
            // Connection is dead, we'll create a new one
            $connection = null;
        }
    }

    try {
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $connection->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $exception) {
        throw new RuntimeException('Database connection failed: ' . $exception->getMessage(), 0, $exception);
    }

    return $connection;
}