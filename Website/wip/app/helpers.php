<?php

/**
 * Shared helper functions.
 */

declare(strict_types=1);

/**
 * Send a JSON response and terminate script execution.
 */
function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/**
 * Normalize phone numbers by stripping non-numeric characters.
 */
function normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone);
}

/**
 * Format raw phone digits into a displayable string (e.g., (123) 456-7890).
 */
function format_phone_display(?string $phoneDigits): ?string
{
    if (!$phoneDigits) {
        return null;
    }
    // Remove all non-digit characters
    $cleanNumbers = preg_replace('/\D+/', '', $phoneDigits); 
    
    // Attempt to format only standard 10-digit US numbers
    if (strlen($cleanNumbers) === 10) {
        return sprintf('(%s) %s-%s', substr($cleanNumbers, 0, 3), substr($cleanNumbers, 3, 3), substr($cleanNumbers, 6));
    }
    
    // Return the original digits if not 10 digits
    return $phoneDigits;
}

/**
 * Estimate booking price using basic multipliers that match the frontend logic.
 */
function calculate_estimated_price(array $bookingData): float
{
    $defaults = fetch_service_defaults($bookingData['serviceType'] ?? '');

    $price = $defaults['base_price'];

    $bedroomMultiplier = [
        '1' => 1.0,
        '2' => 1.3,
        '3' => 1.6,
        '4' => 2.0,
        '5' => 2.5,
    ];

    $frequencyDiscount = [
        'weekly' => 0.15,
        'biweekly' => 0.10,
        'monthly' => 0.05,
        'onetime' => 0.0,
    ];

    $bedrooms = $bookingData['bedrooms'] ?? '1';
    $frequency = $bookingData['frequency'] ?? 'onetime';

    $price *= $bedroomMultiplier[$bedrooms] ?? 1.0;
    $price *= 1 - ($frequencyDiscount[$frequency] ?? 0.0);

    return round($price, 2);
}

/**
 * Generate a booking identifier with a timestamp prefix.
 */
function generate_booking_id(): string
{
    return 'WC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Convert an associative booking payload to the shape used for persistence.
 */
function map_booking_payload(array $data, float $estimatedPrice): array
{
    $serviceDefaults = fetch_service_defaults($data['serviceType'] ?? '');

    return [
        'booking_id' => generate_booking_id(),
        'service_type' => $data['serviceType'],
        'frequency' => $data['frequency'],
        'property_type' => $data['propertyType'],
        'bedrooms' => $data['bedrooms'],
        'bathrooms' => $data['bathrooms'],
        'appointment_date' => $data['date'],
        'appointment_time' => $data['time'],
        'first_name' => $data['firstName'],
        'last_name' => $data['lastName'],
        'email' => $data['email'],
        'phone' => normalize_phone($data['phone']),
        'address' => $data['address'],
        'city' => $data['city'],
        'state' => $data['state'],
        'zip' => $data['zip'],
        'notes' => $data['notes'] ?? null,
        'estimated_price' => $estimatedPrice,
        'duration_minutes' => $serviceDefaults['default_duration_minutes'],
        'status' => 'pending',
        'status_label' => 'scheduled',
        'payment_status' => 'unpaid',
        'customer_id' => null,
        'source' => 'website',
    ];
}

function fetch_service_defaults(?string $serviceCode): array
{
    static $cache = [];

    $defaultMap = [
        'regular' => ['name' => 'Standard Cleaning', 'price' => 129.00, 'duration' => 150],
        'deep' => ['name' => 'Deep Cleaning', 'price' => 249.00, 'duration' => 210],
        'move' => ['name' => 'Move In / Move Out', 'price' => 299.00, 'duration' => 240],
        'onetime' => ['name' => 'One-Time Cleaning', 'price' => 159.00, 'duration' => 180],
    ];

    $serviceKey = $serviceCode ?: 'regular';
    $fallbackBase = $defaultMap[$serviceKey] ?? $defaultMap['regular'];

    $fallback = [
        'service_code' => $serviceKey,
        'name' => $fallbackBase['name'],
        'base_price' => $fallbackBase['price'],
        'default_duration_minutes' => $fallbackBase['duration'],
    ];

    if ($serviceCode === null || $serviceCode === '') {
        return $fallback;
    }

    if (isset($cache[$serviceKey])) {
        return $cache[$serviceKey];
    }

    try {
        $conn = db();
        $stmt = $conn->prepare("SELECT service_code, name, base_price, default_duration_minutes FROM service_catalog WHERE service_code = ? LIMIT 1");
        $stmt->bind_param('s', $serviceKey);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $cache[$serviceKey] = [
                'service_code' => $row['service_code'],
                'name' => $row['name'],
                'base_price' => (float) $row['base_price'],
                'default_duration_minutes' => (int) $row['default_duration_minutes'],
            ];
            $stmt->close();
            return $cache[$serviceKey];
        }
        $stmt->close();
    } catch (Throwable $exception) {
        error_log('fetch_service_defaults error: ' . $exception->getMessage());
    }

    $cache[$serviceKey] = $fallback;
    return $cache[$serviceKey];
}

function upsert_customer(array $payload): int
{
    $conn = db();
    $email = $payload['email'] ?? null;
    $phone = isset($payload['phone']) ? normalize_phone($payload['phone']) : null;

    $customerId = null;

    if ($email || $phone) {
        $query = "SELECT id FROM customers WHERE ";
        $clauses = [];
        $params = [];
        $types = '';

        if ($email) {
            $clauses[] = "email = ?";
            $params[] = $email;
            $types .= 's';
        }

        if ($phone) {
            $clauses[] = "phone = ?";
            $params[] = $phone;
            $types .= 's';
        }

        $query .= implode(' OR ', $clauses) . " LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $customerId = (int) $row['id'];
        }
        $stmt->close();
    }

    if ($customerId) {
        $stmt = $conn->prepare("
            UPDATE customers
            SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, zip = ?, notes = ?
            WHERE id = ?
        ");
        $types = str_repeat('s', 9) . 'i';
        $stmt->bind_param(
            $types,
            $payload['firstName'],
            $payload['lastName'],
            $email,
            $phone,
            $payload['address'],
            $payload['city'],
            $payload['state'],
            $payload['zip'],
            $payload['notes'],
            $customerId
        );
        $stmt->execute();
        $stmt->close();
        return $customerId;
    }

    $stmt = $conn->prepare("
        INSERT INTO customers (first_name, last_name, email, phone, address, city, state, zip, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'sssssssss',
        $payload['firstName'],
        $payload['lastName'],
        $email,
        $phone,
        $payload['address'],
        $payload['city'],
        $payload['state'],
        $payload['zip'],
        $payload['notes']
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    return (int) $newId;
}

function summarize_today_bookings(): array
{
    $conn = db();
    $today = date('Y-m-d');
    $data = [
        'scheduled' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0,
    ];

    $stmt = $conn->prepare("
        SELECT status_label, COUNT(*) as total
        FROM bookings
        WHERE appointment_date = ?
        GROUP BY status_label
    ");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $data[$row['status_label']] = (int) $row['total'];
    }

    $stmt->close();
    return $data;
}

function quick_stats_summary(): array
{
    $conn = db();
    $counts = [
        'customers' => 0,
        'staff' => 0,
        'upcoming' => 0,
    ];

    $result = $conn->query("SELECT COUNT(*) as total FROM customers");
    if ($result && $row = $result->fetch_assoc()) {
        $counts['customers'] = (int) $row['total'];
    }

    $result = $conn->query("SELECT COUNT(*) as total FROM staff WHERE is_active = 1");
    if ($result && $row = $result->fetch_assoc()) {
        $counts['staff'] = (int) $row['total'];
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM bookings
        WHERE appointment_date >= CURDATE()
        AND status_label IN ('scheduled','in_progress')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $counts['upcoming'] = (int) $row['total'];
    }
    $stmt->close();

    return $counts;
}

/**
 * Persist a booking and related history/notification records.
 */
function persist_booking(array $record): string
{
    $required = [
        'booking_id',
        'customer_id',
        'service_type',
        'frequency',
        'property_type',
        'bedrooms',
        'bathrooms',
        'appointment_date',
        'appointment_time',
        'duration_minutes',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'estimated_price',
        'status',
        'status_label',
        'payment_status',
        'source',
    ];

    foreach ($required as $field) {
        if (!array_key_exists($field, $record)) {
            throw new InvalidArgumentException(sprintf('Missing booking field: %s', $field));
        }
    }

    $conn = db();
    $logger = function_exists('getLogger') ? getLogger() : null;

    $bookingId = (string) $record['booking_id'];
    $customerId = (int) $record['customer_id'];
    $status = strtolower((string) $record['status']);
    $statusLabel = strtolower((string) $record['status_label']);
    $paymentStatus = strtolower((string) $record['payment_status']);
    $source = (string) ($record['source'] ?? 'website');
    $notes = $record['notes'] !== null ? trim((string) $record['notes']) : null;
    $durationMinutes = (int) ($record['duration_minutes'] ?? 120);
    $estimatedPrice = (float) $record['estimated_price'];

    if ($customerId <= 0) {
        throw new InvalidArgumentException('Invalid customer reference supplied for booking persistence.');
    }

    $allowedStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'pending';
    }

    $allowedLabels = ['scheduled', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($statusLabel, $allowedLabels, true)) {
        $statusLabel = 'scheduled';
    }

    $allowedPaymentStatuses = ['unpaid', 'paid', 'refunded'];
    if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) {
        $paymentStatus = 'unpaid';
    }

    try {
        $conn->begin_transaction();

        $statement = $conn->prepare("
            INSERT INTO bookings (
                booking_id,
                customer_id,
                service_type,
                frequency,
                property_type,
                bedrooms,
                bathrooms,
                appointment_date,
                appointment_time,
                duration_minutes,
                first_name,
                last_name,
                email,
                phone,
                address,
                city,
                state,
                zip,
                notes,
                estimated_price,
                final_price,
                status,
                source,
                status_label,
                payment_status,
                payment_method,
                quickbooks_invoice_id,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, NULL, NULL, NOW(), NOW()
            )
        ");

        // Extract and type cast all parameters
        $serviceType = (string) $record['service_type'];
        $frequency = (string) $record['frequency'];
        $propertyType = (string) $record['property_type'];
        $bedrooms = (string) $record['bedrooms'];
        $bathrooms = (string) $record['bathrooms'];
        $appointmentDate = (string) $record['appointment_date'];
        $appointmentTime = (string) $record['appointment_time'];
        $firstName = (string) $record['first_name'];
        $lastName = (string) $record['last_name'];
        $email = (string) $record['email'];
        $phone = (string) $record['phone'];
        $address = (string) $record['address'];
        $city = (string) $record['city'];
        $state = (string) $record['state'];
        $zip = (string) $record['zip'];

        // Count: 24 parameters total
        $statement->bind_param(
            'sissssssisssssssssdssss', // 24 type specifiers for 24 parameters
            $bookingId, // s - 1
            $customerId, // i - 2
            $serviceType, // s - 3
            $frequency, // s - 4
            $propertyType, // s - 5
            $bedrooms, // s - 6
            $bathrooms, // s - 7
            $appointmentDate, // s - 8
            $appointmentTime, // s - 9
            $durationMinutes, // i - 10
            $firstName, // s - 11
            $lastName, // s - 12
            $email, // s - 13
            $phone, // s - 14
            $address, // s - 15
            $city, // s - 16
            $state, // s - 17
            $zip, // s - 18
            $notes, // s - 19
            $estimatedPrice, // d - 20
            $status, // s - 21
            $source, // s - 22
            $statusLabel, // s - 23
            $paymentStatus // s - 24
        );

        $result = $statement->execute();
        if (!$result) {
            throw new RuntimeException("Database insert failed: " . $statement->error);
        }

        $statement->close();

        // Record initial status history
        $historyNote = 'Booking created via public website';
        $historyStmt = $conn->prepare("
            INSERT INTO booking_status_history (booking_id, previous_status, new_status, changed_by, notes)
            VALUES (?, NULL, ?, NULL, ?)
        ");
        $historyStmt->bind_param('sss', $bookingId, $statusLabel, $historyNote);
        $historyStmt->execute();
        $historyStmt->close();

        // Create dashboard notification for admins
        $notificationPayload = json_encode([
            'message' => sprintf(
                '%s %s booked a %s service for %s.',
                $record['first_name'],
                $record['last_name'],
                ucfirst($record['service_type']),
                date('M j, Y', strtotime($record['appointment_date']))
            ),
            'appointment_time' => $record['appointment_time'],
            'estimated_price' => $estimatedPrice,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        $notificationStmt = $conn->prepare("
            INSERT INTO notifications (type, booking_id, customer_id, payload, is_read, created_at)
            VALUES ('booking_created', ?, NULLIF(?, 0), ?, 0, NOW())
        ");
        $customerIdForNotification = $customerId;
        $notificationStmt->bind_param('sis', $bookingId, $customerIdForNotification, $notificationPayload);
        $notificationStmt->execute();
        $notificationStmt->close();

        $conn->commit();

        if ($logger) {
            $logger->info('Booking persisted successfully', [
                'booking_id' => $bookingId,
                'customer_id' => $customerId,
                'status' => $status,
                'status_label' => $statusLabel,
            ]);
        }

        return $bookingId;
    } catch (Throwable $exception) {
        $conn->rollback();
        if ($logger) {
            $logger->error('Failed to persist booking', [
                'booking_id' => $record['booking_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
        throw new RuntimeException('Unable to save booking at this time. Error: ' . $exception->getMessage(), 0, $exception);
    }
}

function format_currency(float $value): string
{
    return '$' . number_format($value, 2);
}

/**
 * Returns a color code for a booking status label.
 */
function status_color(string $label): string
{
    return [
        'scheduled' => '#22d3ee',
        'in_progress' => '#f59e0b',
        'completed' => '#22c55e',
        'cancelled' => '#ef4444',
    ][$label] ?? '#a855f7';
}

/**
 * Returns a color code for text based on a booking status label.
 */
function status_text_color(string $label): string
{
    return [
        'scheduled' => '#0f172a',
        'in_progress' => '#78350f',
        'completed' => '#064e3b',
        'cancelled' => '#7f1d1d',
    ][$label] ?? '#312e81';
}

/**
 * Fetch a full booking profile (booking + staff + status history).
 */
function fetch_booking_detail(string $bookingId): ?array
{
    $conn = db();

    $stmt = $conn->prepare("
        SELECT 
            b.*,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_full_name
        FROM bookings b
        LEFT JOIN customers c ON c.id = b.customer_id
        WHERE b.booking_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookingRow = $result->fetch_assoc();
    $stmt->close();

    if (!$bookingRow) {
        return null;
    }

    $serviceDefaults = fetch_service_defaults($bookingRow['service_type'] ?? '');

    $durationMinutes = (int) ($bookingRow['duration_minutes'] ?? $serviceDefaults['default_duration_minutes']);
    if ($durationMinutes <= 0) {
        $durationMinutes = $serviceDefaults['default_duration_minutes'];
    }

    $fullName = trim(($bookingRow['first_name'] ?? '') . ' ' . ($bookingRow['last_name'] ?? ''));
    $displayPhone = format_phone_display($bookingRow['phone'] ?? '');

    $booking = [
        'id' => $bookingRow['booking_id'],
        'booking_id' => $bookingRow['booking_id'],
        'customer_id' => $bookingRow['customer_id'] ? (int) $bookingRow['customer_id'] : null,
        'customer_full_name' => $bookingRow['customer_full_name'] ?: $fullName,
        'first_name' => $bookingRow['first_name'],
        'last_name' => $bookingRow['last_name'],
        'email' => $bookingRow['email'],
        'phone' => $displayPhone,
        'raw_phone' => $bookingRow['phone'],
        'service_type' => $bookingRow['service_type'],
        'frequency' => $bookingRow['frequency'],
        'property_type' => $bookingRow['property_type'],
        'bedrooms' => $bookingRow['bedrooms'],
        'bathrooms' => $bookingRow['bathrooms'],
        'appointment_date' => $bookingRow['appointment_date'],
        'appointment_time' => $bookingRow['appointment_time'],
        'duration_minutes' => $durationMinutes,
        'address' => $bookingRow['address'],
        'city' => $bookingRow['city'],
        'state' => $bookingRow['state'],
        'zip' => $bookingRow['zip'],
        'notes' => $bookingRow['notes'],
        'estimated_price' => $bookingRow['estimated_price'] !== null ? (float) $bookingRow['estimated_price'] : null,
        'final_price' => $bookingRow['final_price'] !== null ? (float) $bookingRow['final_price'] : null,
        'status' => $bookingRow['status'],
        'status_label' => $bookingRow['status_label'],
        'payment_status' => $bookingRow['payment_status'],
        'source' => $bookingRow['source'],
        'created_at' => $bookingRow['created_at'],
        'updated_at' => $bookingRow['updated_at'] ?? null,
    ];

    $booking['service'] = [
        'type' => $bookingRow['service_type'],
        'name' => $serviceDefaults['name'] ?? ucfirst((string) $bookingRow['service_type']),
        'frequency' => ucfirst((string) $bookingRow['frequency']),
    ];

    $booking['property'] = [
        'type' => ucfirst(str_replace('_', ' ', (string) $bookingRow['property_type'])),
        'bedrooms' => $bookingRow['bedrooms'],
        'bathrooms' => $bookingRow['bathrooms'],
    ];

    $booking['appointment'] = [
        'date' => $bookingRow['appointment_date'],
        'time' => $bookingRow['appointment_time'],
        'formatted' => $bookingRow['appointment_date'] && $bookingRow['appointment_time']
            ? date('l, F j, Y \a\t g:i A', strtotime($bookingRow['appointment_date'] . ' ' . $bookingRow['appointment_time']))
            : null,
        'duration_minutes' => $durationMinutes,
    ];

    $booking['location'] = [
        'address' => $bookingRow['address'],
        'city' => $bookingRow['city'],
        'state' => $bookingRow['state'],
        'zip' => $bookingRow['zip'],
        'full' => trim(sprintf(
            '%s, %s, %s %s',
            $bookingRow['address'] ?? '',
            $bookingRow['city'] ?? '',
            $bookingRow['state'] ?? '',
            $bookingRow['zip'] ?? ''
        ), ', '),
    ];

    $booking['pricing'] = [
        'estimated' => $bookingRow['estimated_price'] !== null ? format_currency((float) $bookingRow['estimated_price']) : null,
        'final' => $bookingRow['final_price'] !== null ? format_currency((float) $bookingRow['final_price']) : null,
    ];

    $booking['status_meta'] = [
        'label' => $bookingRow['status_label'],
        'label_display' => ucwords(str_replace('_', ' ', (string) $bookingRow['status_label'])),
        'color' => status_color($bookingRow['status_label']),
        'text_color' => status_text_color($bookingRow['status_label']),
        'payment_status' => ucfirst((string) $bookingRow['payment_status']),
    ];

    // Assigned staff
    $staffStmt = $conn->prepare("
        SELECT 
            s.id,
            s.first_name,
            s.last_name,
            s.email,
            s.phone,
            s.role,
            s.color_tag,
            ba.assignment_role,
            ba.assigned_at
        FROM booking_assignments ba
        JOIN staff s ON s.id = ba.staff_id
        WHERE ba.booking_id = ?
        ORDER BY ba.assignment_role DESC, ba.assigned_at ASC
    ");
    $staffStmt->bind_param('s', $bookingId);
    $staffStmt->execute();
    $staffResult = $staffStmt->get_result();

    $assignedStaff = [];
    while ($row = $staffResult->fetch_assoc()) {
        $assignedStaff[] = [
            'id' => (int) $row['id'],
            'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            'email' => $row['email'],
            'phone' => format_phone_display($row['phone']),
            'role' => ucfirst(str_replace('_', ' ', (string) $row['role'])),
            'assignment_role' => $row['assignment_role'],
            'assigned_at' => $row['assigned_at']
                ? date('M j, Y g:i A', strtotime($row['assigned_at']))
                : null,
            'color_tag' => $row['color_tag'],
        ];
    }
    $staffStmt->close();

    // Status history
    $historyStmt = $conn->prepare("
        SELECT previous_status, new_status, notes, created_at
        FROM booking_status_history
        WHERE booking_id = ?
        ORDER BY created_at DESC
    ");
    $historyStmt->bind_param('s', $bookingId);
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();

    $statusHistory = [];
    while ($historyRow = $historyResult->fetch_assoc()) {
        $statusHistory[] = [
            'previous_status' => $historyRow['previous_status']
                ? ucwords(str_replace('_', ' ', (string) $historyRow['previous_status']))
                : null,
            'new_status' => ucwords(str_replace('_', ' ', (string) $historyRow['new_status'])),
            'notes' => $historyRow['notes'],
            'changed_at' => $historyRow['created_at']
                ? date('M j, Y g:i A', strtotime($historyRow['created_at']))
                : null,
        ];
    }
    $historyStmt->close();

    return [
        'booking' => $booking,
        'assigned_staff' => $assignedStaff,
        'status_history' => $statusHistory,
    ];
}

/**
 * Schedule (or reschedule) the client reminder to trigger before the appointment.
 */
function schedule_booking_reminder(
    string $bookingId,
    string $phone,
    string $appointmentDate,
    string $appointmentTime,
    ?string $message = null,
    int $hoursBefore = 20
): void {
    $normalizedPhone = normalize_phone($phone);
    if ($normalizedPhone === '') {
        return;
    }

    $appointmentDateTime = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        sprintf('%s %s', $appointmentDate, $appointmentTime ?: '00:00:00')
    );

    if (!$appointmentDateTime) {
        return;
    }

    $sendAt = $appointmentDateTime->modify(sprintf('-%d hours', $hoursBefore));
    $now = new DateTimeImmutable('now');
    if ($sendAt < $now) {
        // If the reminder time already passed, push it 5 minutes from now
        $sendAt = $now->modify('+5 minutes');
    }

    $message ??= sprintf(
        'Wasatch Cleaners: Reminder for your cleaning on %s at %s. Reply HELP for support.',
        $appointmentDateTime->format('M j, Y'),
        $appointmentDateTime->format('g:i A')
    );

    $conn = db();

    // Remove existing pending reminders for this booking
    $deleteStmt = $conn->prepare("
        DELETE FROM scheduled_reminders
        WHERE booking_id = ? AND status = 'pending'
    ");
    $deleteStmt->bind_param('s', $bookingId);
    $deleteStmt->execute();
    $deleteStmt->close();

    $insertStmt = $conn->prepare("
        INSERT INTO scheduled_reminders (booking_id, phone, send_at, message, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $sendAtFormatted = $sendAt->format('Y-m-d H:i:s');
    $insertStmt->bind_param('ssss', $bookingId, $normalizedPhone, $sendAtFormatted, $message);
    $insertStmt->execute();
    $insertStmt->close();
}

/**
 * Cancel any pending reminders for a booking (e.g., when the booking is cancelled).
 */
function cancel_booking_reminders(string $bookingId, string $reason = 'Cancelled'): void
{
    $conn = db();
    $stmt = $conn->prepare("
        UPDATE scheduled_reminders
        SET status = 'failed', error_message = ?
        WHERE booking_id = ? AND status = 'pending'
    ");
    $stmt->bind_param('ss', $reason, $bookingId);
    $stmt->execute();
    $stmt->close();
}
