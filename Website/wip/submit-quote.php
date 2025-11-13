<?php
/**
 * submit-quote.php
 * Handles free quote request submissions from the landing page
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security_middleware.php';
require_once __DIR__ . '/app/database.php';
require_once __DIR__ . '/app/helpers.php';

setSecurityHeaders();
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Rate limiting: max 3 quote requests per 15 minutes per IP
$rateLimitId = 'quote_request_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!checkRateLimit($rateLimitId, 3, 900)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Too many requests. Please try again in 15 minutes or call us at (385)213-8900.'
    ]);
    exit;
}

try {
    // Validate and sanitize input
    $firstName = sanitize_input($_POST['firstName'] ?? '', 'string');
    $lastName = sanitize_input($_POST['lastName'] ?? '', 'string');
    $email = sanitize_input($_POST['email'] ?? '', 'email');
    $phone = sanitize_input($_POST['phone'] ?? '', 'phone');
    $serviceType = sanitize_input($_POST['serviceType'] ?? '', 'string');
    $propertyType = sanitize_input($_POST['propertyType'] ?? '', 'string');
    $bedrooms = sanitize_input($_POST['bedrooms'] ?? '', 'string');
    $bathrooms = sanitize_input($_POST['bathrooms'] ?? '', 'string');
    $address = sanitize_input($_POST['address'] ?? '', 'string');
    $city = sanitize_input($_POST['city'] ?? '', 'string');
    $state = sanitize_input($_POST['state'] ?? '', 'string');
    $zip = sanitize_input($_POST['zip'] ?? '', 'string');
    $message = sanitize_input($_POST['message'] ?? '', 'string');

    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) ||
        empty($serviceType) || empty($propertyType)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Please fill in all required fields.'
        ]);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Please provide a valid email address.'
        ]);
        exit;
    }

    // Map service and property types to readable names
    $serviceNames = [
        'regular' => 'Regular Cleaning',
        'deep' => 'Deep Cleaning',
        'move' => 'Move In/Out Cleaning',
        'other' => 'Other Service'
    ];

    $propertyNames = [
        'house' => 'House',
        'apartment' => 'Apartment',
        'condo' => 'Condo',
        'office' => 'Office',
        'other' => 'Other'
    ];

    $serviceName = $serviceNames[$serviceType] ?? $serviceType;
    $propertyName = $propertyNames[$propertyType] ?? $propertyType;

    // Store quote request in database (optional)
    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO quote_requests
        (first_name, last_name, email, phone, service_type, property_type,
         bedrooms, bathrooms, address, city, state, zip, message, ip_address, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt->bind_param(
        'ssssssssssssss',
        $firstName,
        $lastName,
        $email,
        $phone,
        $serviceType,
        $propertyType,
        $bedrooms,
        $bathrooms,
        $address,
        $city,
        $state,
        $zip,
        $message,
        $ipAddress
    );

    $dbSuccess = $stmt->execute();
    $stmt->close();

    // Prepare email content
    $emailSubject = "New Quote Request from $firstName $lastName";
    $fullAddress = trim("$address, $city, $state $zip");

    $emailBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #0f766e; }
                .value { color: #1f2937; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 2px solid #e5e7eb; font-size: 12px; color: #6b7280; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin: 0;'>🔔 New Quote Request</h2>
                    <p style='margin: 5px 0 0 0; opacity: 0.9;'>From Wasatch Cleaners Website</p>
                </div>
                <div class='content'>
                    <h3 style='color: #0f766e; margin-top: 0;'>Contact Information</h3>

                    <div class='field'>
                        <span class='label'>Name:</span>
                        <span class='value'>$firstName $lastName</span>
                    </div>

                    <div class='field'>
                        <span class='label'>Email:</span>
                        <span class='value'><a href='mailto:$email'>$email</a></span>
                    </div>

                    <div class='field'>
                        <span class='label'>Phone:</span>
                        <span class='value'><a href='tel:$phone'>$phone</a></span>
                    </div>

                    <h3 style='color: #0f766e; margin-top: 30px;'>Service Details</h3>

                    <div class='field'>
                        <span class='label'>Service Type:</span>
                        <span class='value'>$serviceName</span>
                    </div>

                    <div class='field'>
                        <span class='label'>Property Type:</span>
                        <span class='value'>$propertyName</span>
                    </div>
    ";

    if (!empty($bedrooms)) {
        $emailBody .= "
                    <div class='field'>
                        <span class='label'>Bedrooms:</span>
                        <span class='value'>$bedrooms</span>
                    </div>
        ";
    }

    if (!empty($bathrooms)) {
        $emailBody .= "
                    <div class='field'>
                        <span class='label'>Bathrooms:</span>
                        <span class='value'>$bathrooms</span>
                    </div>
        ";
    }

    if (!empty($fullAddress)) {
        $emailBody .= "
                    <h3 style='color: #0f766e; margin-top: 30px;'>Property Address</h3>
                    <div class='field'>
                        <span class='value'>$fullAddress</span>
                    </div>
        ";
    }

    if (!empty($message)) {
        $emailBody .= "
                    <h3 style='color: #0f766e; margin-top: 30px;'>Additional Details</h3>
                    <div class='field'>
                        <span class='value'>" . nl2br(htmlspecialchars($message)) . "</span>
                    </div>
        ";
    }

    $emailBody .= "
                    <div class='footer'>
                        <p><strong>Next Steps:</strong></p>
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            <li>Review the quote request details above</li>
                            <li>Contact the customer within 24 hours</li>
                            <li>Provide a detailed quote based on their needs</li>
                        </ul>
                        <p style='margin-top: 15px;'>
                            <em>This quote request was submitted on " . date('F j, Y \a\t g:i A') . "</em>
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>
    ";

    // Send email using PHPMailer
    $emailSent = send_html_email(
        'kemilily89@gmail.com',
        $emailSubject,
        $emailBody,
        $email, // Reply-to address
        "$firstName $lastName" // Reply-to name
    );

    if ($emailSent) {
        // Also send confirmation email to customer
        $customerSubject = "Thank You for Your Quote Request - Wasatch Cleaners";
        $customerBody = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0; text-align: center; }
                    .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
                    .button { display: inline-block; background: #14b8a6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1 style='margin: 0;'>Thank You, $firstName!</h1>
                        <p style='margin: 10px 0 0 0; opacity: 0.9;'>We've received your quote request</p>
                    </div>
                    <div class='content'>
                        <p>Thank you for choosing Wasatch Cleaners! We're excited to help make your space spotless.</p>

                        <p><strong>What happens next?</strong></p>
                        <ul>
                            <li>Our team will review your request</li>
                            <li>We'll contact you within 24 hours</li>
                            <li>You'll receive a detailed, personalized quote</li>
                        </ul>

                        <p><strong>Your Request Summary:</strong></p>
                        <ul>
                            <li>Service: $serviceName</li>
                            <li>Property: $propertyName</li>
                        </ul>

                        <p>If you have any questions in the meantime, feel free to contact us:</p>
                        <ul>
                            <li>📞 Phone: <a href='tel:+13852138900'>(385) 213-8900</a></li>
                            <li>📧 Email: <a href='mailto:hello@wasatchcleaners.com'>hello@wasatchcleaners.com</a></li>
                        </ul>

                        <p style='margin-top: 30px;'>We look forward to serving you!</p>
                        <p><strong>The Wasatch Cleaners Team</strong></p>
                    </div>
                </div>
            </body>
            </html>
        ";

        send_html_email(
            $email,
            $customerSubject,
            $customerBody
        );

        echo json_encode([
            'success' => true,
            'message' => 'Thank you! We\'ve received your quote request and will contact you within 24 hours.'
        ]);
    } else {
        // Email failed but database saved
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'We received your request but had trouble sending confirmation. Please call us at (385)213-8900 to confirm.'
        ]);
    }

} catch (Exception $e) {
    error_log('Quote submission error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sorry, there was an error processing your request. Please call us at (385)213-8900.'
    ]);
}
