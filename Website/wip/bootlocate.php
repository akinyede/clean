<?php
/**
 * ULTIMATE DEBUG - test_booking_notifications.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "🧪 ULTIMATE DEBUG - Finding the exact 500 error\n";
echo "===============================================\n\n";

try {
    echo "Step 1: Bootstrap...\n";
    require_once __DIR__ . '/app/bootstrap.php';
    echo "✅ Bootstrap loaded\n\n";

    echo "Step 2: Check notify.php...\n";
    if (file_exists(__DIR__ . '/notify.php')) {
        require_once __DIR__ . '/notify.php';
        echo "✅ notify.php loaded\n\n";
    } else {
        echo "⚠️ notify.php not found - continuing without it\n\n";
    }

    echo "Step 3: Creating test class instance...\n";
    $testRunner = new BookingNotificationTest();
    echo "✅ Test class instance created\n\n";

    echo "Step 4: Running individual tests...\n\n";

    // Test each method individually
    $methods = [
        'testConfigConstants',
        'testDatabaseConnection', 
        'testEmailConfiguration',
        'testSMSConfiguration',
        'testSecurityFunctions',
        'testHelperFunctions',
        'testNotificationFunctions',
        'testBookingCreation',
        'testCustomerEmail',
        'testCustomerSMS',
        'testAdminEmail',
        'testDashboardNotification',
        'testFullBookingSubmission'
    ];

    foreach ($methods as $method) {
        echo "Testing: $method...\n";
        try {
            $result = $testRunner->$method();
            echo "✅ $method - " . ($result['passed'] ? 'PASS' : 'FAIL') . "\n";
            if (!$result['passed']) {
                echo "   Message: " . $result['message'] . "\n";
            }
        } catch (Exception $e) {
            echo "❌ $method - EXCEPTION: " . $e->getMessage() . "\n";
            echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
            break;
        } catch (Error $e) {
            echo "❌ $method - ERROR: " . $e->getMessage() . "\n";
            echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
            break;
        }
        echo "\n";
    }

    echo "🎉 All individual tests completed!\n";

} catch (Exception $e) {
    echo "❌ FATAL EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>