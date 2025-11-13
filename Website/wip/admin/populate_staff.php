<?php
// populate_staff.php
require_once __DIR__ . '/app/bootstrap.php';

$conn = db();

$staffMembers = [
    [
        'first_name' => 'John',
        'last_name' => 'Smith',
        'email' => 'john.smith@wasatchcleaners.com',
        'phone' => '3852138901',
        'role' => 'cleaner',
        'color_tag' => '#14b8a6',
        'hourly_rate' => 25.00
    ],
    [
        'first_name' => 'Maria',
        'last_name' => 'Garcia',
        'email' => 'maria.garcia@wasatchcleaners.com',
        'phone' => '3852138902',
        'role' => 'team_lead',
        'color_tag' => '#8b5cf6',
        'hourly_rate' => 30.00
    ],
    [
        'first_name' => 'David',
        'last_name' => 'Johnson',
        'email' => 'david.johnson@wasatchcleaners.com',
        'phone' => '3852138903',
        'role' => 'cleaner',
        'color_tag' => '#f59e0b',
        'hourly_rate' => 22.00
    ]
];

foreach ($staffMembers as $staff) {
    $stmt = $conn->prepare("
        INSERT INTO staff (first_name, last_name, email, phone, role, color_tag, hourly_rate, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->bind_param(
        'ssssssd',
        $staff['first_name'],
        $staff['last_name'],
        $staff['email'],
        $staff['phone'],
        $staff['role'],
        $staff['color_tag'],
        $staff['hourly_rate']
    );
    
    if ($stmt->execute()) {
        echo "Added staff: {$staff['first_name']} {$staff['last_name']}\n";
    } else {
        echo "Failed to add staff: {$staff['first_name']} {$staff['last_name']} - " . $stmt->error . "\n";
    }
    $stmt->close();
}

echo "Staff population completed!\n";