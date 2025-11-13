<?php

// api/cancel-booking.php


declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');

try {
    $user = ensure_api_authenticated(['admin', 'manager']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['success' => false, 'message' => 'Method not allowed'], 405);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $bookingId = $input['booking_id'] ?? null;
    
    if (!$bookingId) {
        json_response(['success' => false, 'message' => 'Booking ID is required'], 400);
        exit;
    }
    
    $conn = db();
    
    // Get booking details before cancellation
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            GROUP_CONCAT(s.id) as staff_ids,
            GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name)) as staff_names,
            GROUP_CONCAT(s.phone) as staff_phones
        FROM bookings b
        LEFT JOIN booking_assignments ba ON b.booking_id = ba.booking_id
        LEFT JOIN staff s ON ba.staff_id = s.id
        WHERE b.booking_id = ?
        GROUP BY b.id
    ");
    
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();
    
    if (!$booking) {
        json_response(['success' => false, 'message' => 'Booking not found'], 404);
        exit;
    }
    
    // Check if already cancelled
    if ($booking['status'] === 'cancelled') {
        json_response(['success' => false, 'message' => 'Booking is already cancelled'], 400);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking status to cancelled
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'cancelled', 
                status_label = 'cancelled',
                updated_at = NOW()
            WHERE booking_id = ?
        ");
        $stmt->bind_param('s', $bookingId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update booking status');
        }
        $stmt->close();
        
        // Remove staff assignments
        $stmt = $conn->prepare("DELETE FROM booking_assignments WHERE booking_id = ?");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Send notification to customer
        $customerMessage = "Wasatch Cleaners: Your booking #{$bookingId} scheduled for {$booking['appointment_date']} at {$booking['appointment_time']} has been cancelled. If you have questions, please call us at (385)213-8900.";
        
        // Try to send SMS to customer
        if (!empty($booking['phone'])) {
            try {
                send_sms_notification($booking['phone'], $customerMessage);
            } catch (Exception $e) {
                error_log("Failed to send cancellation SMS to customer: " . $e->getMessage());
            }
        }
        
        // Send notification to assigned staff
        if (!empty($booking['staff_ids'])) {
            $staffIds = explode(',', $booking['staff_ids']);
            $staffPhones = explode(',', $booking['staff_phones']);
            
            foreach ($staffPhones as $phone) {
                if (!empty(trim($phone))) {
                    $staffMessage = "Wasatch Cleaners: Booking #{$bookingId} scheduled for {$booking['appointment_date']} at {$booking['appointment_time']} has been cancelled. You are no longer assigned to this job.";
                    
                    try {
                        send_sms_notification(trim($phone), $staffMessage);
                    } catch (Exception $e) {
                        error_log("Failed to send cancellation SMS to staff: " . $e->getMessage());
                    }
                }
            }
        }
        
        // Log the cancellation
        $logger = getLogger();
        $logger->info('Booking cancelled', [
            'booking_id' => $bookingId,
            'cancelled_by' => $user['id'],
            'customer' => $booking['first_name'] . ' ' . $booking['last_name']
        ]);
        
        json_response([
            'success' => true,
            'message' => 'Booking cancelled successfully',
            'booking_id' => $bookingId
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Cancel booking error: " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Failed to cancel booking: ' . $e->getMessage()
    ], 500);
}

// Helper function to send SMS notifications

function send_sms_notification(string $phone, string $message): bool
{
    $hasCredentials = defined('OPENPHONE_API_KEY') && trim((string)OPENPHONE_API_KEY) !== ''
        && defined('OPENPHONE_NUMBER') && trim((string)OPENPHONE_NUMBER) !== '';

    if (!$hasCredentials) {
        error_log("Failed to send SMS to {$phone}: OpenPhone credentials missing");
        return false;
    }

    $sent = send_custom_sms_openphone($phone, $message);

    if (!$sent) {
        error_log("Failed to send SMS to {$phone}: OpenPhone request rejected");
    }

    return $sent;
}
