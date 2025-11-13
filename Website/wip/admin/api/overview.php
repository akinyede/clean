<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');
$user = ensure_api_authenticated(['admin', 'manager', 'staff']);

$todaySummary = summarize_today_bookings();
$quickStats = quick_stats_summary();

json_response([
    'success' => true,
    'user' => [
        'id' => $user['id'],
        'name' => $user['full_name'],
        'role' => $user['role'],
    ],
    'today' => [
        'scheduled' => $todaySummary['scheduled'] ?? 0,
        'in_progress' => $todaySummary['in_progress'] ?? 0,
        'completed' => $todaySummary['completed'] ?? 0,
        'cancelled' => $todaySummary['cancelled'] ?? 0,
    ],
    'quickStats' => $quickStats,
]);
