<?php

// api/bookings.php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0'); 
ini_set('log_errors', '1');

try {
    // Authenticate user
    $user = ensure_api_authenticated(['admin', 'manager', 'staff']);
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handle_get();
            break;
        
        case 'POST':
        case 'PUT':
        case 'DELETE':
            json_response([
                'success' => false,
                'message' => 'Booking mutations will be enabled once staff workflows ship.',
            ], 501);
            break;
        
        default:
            json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    error_log("Bookings API Exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    json_response([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ], 500);
}

function handle_get(): void
{
    try {
        $view = $_GET['view'] ?? 'day';
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // Validate view parameter
        $validViews = ['day', 'week', 'month'];
        if (!in_array($view, $validViews)) {
            json_response([
                'success' => false,
                'message' => 'Invalid view parameter. Must be one of: ' . implode(', ', $validViews)
            ], 400);
            return;
        }
        
        // Validate and sanitize date
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            json_response([
                'success' => false,
                'message' => 'Invalid date format. Expected Y-m-d (e.g., 2025-10-27)'
            ], 400);
            return;
        }
        
        $start = new DateTimeImmutable($date);
        $end = $start;
        
        // FIX #2: Proper date range calculation for week/month views
        switch ($view) {
            case 'week':
                // Find the Monday of the week containing the given date
                $start = $start->modify('monday this week');
                $end = $start->modify('+6 days');
                break;
            
            case 'month':
                // First and last day of the month
                $start = $start->modify('first day of this month');
                $end = $start->modify('last day of this month');
                break;
            
            default:
                // day view: keep same day
                break;
        }
        
        $conn = db();
        
        // Optimized query with proper error handling
        $stmt = $conn->prepare("
            SELECT 
                b.booking_id,
                b.appointment_date,
                b.appointment_time,
                b.duration_minutes,
                b.service_type,
                b.status_label,
                CONCAT(b.first_name, ' ', b.last_name) AS customer_name,
                CONCAT_WS(', ', b.address, b.city, b.state, b.zip) AS address,
                GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') AS staff_assigned
            FROM bookings b
            LEFT JOIN booking_assignments ba ON ba.booking_id = b.booking_id
            LEFT JOIN staff s ON s.id = ba.staff_id
            WHERE b.appointment_date BETWEEN ? AND ?
            GROUP BY b.booking_id, b.appointment_date, b.appointment_time, b.duration_minutes, 
                     b.service_type, b.status_label, b.first_name, b.last_name, 
                     b.address, b.city, b.state, b.zip
            ORDER BY b.appointment_date ASC, b.appointment_time ASC
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        
        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');
        
        $stmt->bind_param('ss', $startStr, $endStr);
        
        if (!$stmt->execute()) {
            throw new Exception("Query execution failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $events = [];
        
        while ($row = $result->fetch_assoc()) {
            try {
                $serviceDefaults = fetch_service_defaults($row['service_type']);
                
                $startDateTime = $row['appointment_date'] . ' ' . $row['appointment_time'];
                $durationMinutes = (int) ($row['duration_minutes'] ?? $serviceDefaults['default_duration_minutes']);
                
                // Calculate end time safely
                $startTimestamp = strtotime($startDateTime);
                if ($startTimestamp === false) {
                    error_log("Invalid datetime: " . $startDateTime);
                    continue;
                }
                
                $endTimestamp = $startTimestamp + ($durationMinutes * 60);
                $endDateTime = date('Y-m-d H:i:s', $endTimestamp);
                
                // Parse staff names
                $staffArray = [];
                if (!empty($row['staff_assigned'])) {
                    $staffArray = explode(', ', $row['staff_assigned']);
                }
                
                $events[] = [
                    'id' => $row['booking_id'],
                    'date' => $row['appointment_date'],
                    'start_time' => date('g:i A', strtotime($row['appointment_time'])),
                    'end_time' => date('g:i A', $endTimestamp),
                    'duration_minutes' => $durationMinutes,
                    'service_name' => $serviceDefaults['name'],
                    'status_label' => ucwords(str_replace('_', ' ', $row['status_label'])),
                    'status_color' => status_color($row['status_label']),
                    'status_text_color' => status_text_color($row['status_label']),
                    'customer_name' => $row['customer_name'],
                    'address' => $row['address'],
                    'staff' => $staffArray,
                ];
                
            } catch (Exception $e) {
                error_log("Error processing booking row: " . $e->getMessage());
                continue;
            }
        }
        
        $stmt->close();
        
        json_response([
            'success' => true,
            'events' => $events,
            'range' => [
                'start' => $startStr,
                'end' => $endStr,
                'view' => $view,
            ],
        ]);
        
    } catch (Exception $e) {
        error_log("handle_get Exception: " . $e->getMessage());
        throw $e;
    }
}
