<?php
// admin/simple_chart_test.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../app/bootstrap.php';

try {
    $db = db();
    
    // Just get booking counts
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statusCounts = [
        'pending' => 0,
        'confirmed' => 0, 
        'completed' => 0,
        'cancelled' => 0
    ];
    
    foreach ($results as $row) {
        $status = $row['status'];
        if (isset($statusCounts[$status])) {
            $statusCounts[$status] = (int)$row['count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'status_counts' => $statusCounts,
        'message' => 'Database connection successful using db() function'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}