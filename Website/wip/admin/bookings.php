<?php

/**
 * admin/bookings.php - Bookings Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_admin(['admin', 'manager', 'staff']);

$user = auth_user();
$basePath = dirname($_SERVER['SCRIPT_NAME']);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_staff'])) {
        assignStaffToBooking($_POST['booking_id'], $_POST['staff_id']);
    } elseif (isset($_POST['update_status'])) {
        updateBookingStatus($_POST['booking_id'], $_POST['status']);
    }
}

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Get bookings with staff assignments
$bookings = getBookingsWithAssignments($status, $date, $search);
$staffMembers = getStaffMembers();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bookings Management - Wasatch Cleaners</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js" defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    
    <script src="js/api-client.js" defer></script>
    <script src="js/booking-manager.js" defer></script>
    <script src="js/bookings.js" defer></script>
    <script src="js/bookings-logic.js" defer></script>
    
    <style>
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            background-color: #14b8a6;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #ffffff;
            box-shadow: 0 12px 25px -12px rgba(20, 184, 166, 0.7);
            transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #0d9488;
            box-shadow: 0 16px 35px -15px rgba(13, 148, 136, 0.7);
            transform: translateY(-1px);
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            border: 1px solid #cbd5f5;
            background-color: #ffffff;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1f2937;
            transition: background-color 0.2s ease, border-color 0.2s ease;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-secondary:hover {
            background-color: #f8fafc;
            border-color: #94a3b8;
        }
        .stat-card {
            background-color: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        .stat-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            line-height: 1;
        }
        .stat-subtext {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.5rem;
        }
        .booking-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s;
        }
        .booking-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
        }
        .close {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
        }
        #globalLoader {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 9998;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        /* Enhanced Booking Styles */
        .selectable-booking {
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }
        
        .selectable-booking:hover {
            border-color: #e5e7eb;
        }
        
        .selectable-booking.selected {
            border-color: #14b8a6;
            background-color: #f0fdfa;
        }
        
        .booking-checkbox:checked {
            background-color: #14b8a6;
            border-color: #14b8a6;
        }
        
        /* Dropdown styles */
        .dropdown-menu {
            display: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid #e5e7eb;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        /* Modal enhancements */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content-enhanced {
            background: white;
            border-radius: 12px;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        #bulkActions {
            transition: all 0.3s ease;
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">
    <div id="globalLoader" class="flex items-center justify-center">
        <div class="text-white text-lg">Loading...</div>
    </div>

    <!-- Top Navigation -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-teal-900 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <!-- Logo & Brand (Left) -->
            <div class="flex items-center space-x-3">
                <img src="../logo.png" alt="Wasatch Cleaners logo" class="h-10 w-10 rounded-full bg-white p-1 object-contain" />
                <div>
                    <p class="text-lg font-semibold">Wasatch Cleaners</p>
                    <p class="text-xs text-teal-300 uppercase tracking-wider">Operations Console</p>
                </div>
            </div>

            <!-- Navigation + User Info (Right) -->
            <div class="hidden md:flex items-center space-x-6 text-sm font-medium">
                <a href="dashboard.php" class="hover:text-teal-200 transition">Admin Home</a>
                <a href="bookings.php" class="text-white font-bold border-b-2 border-teal-400 pb-0.5">Bookings</a>
                <a href="calendar.php" class="hover:text-teal-200 transition">Calendar</a>
                <a href="customers.php" class="hover:text-teal-200 transition">Customers</a>
                <a href="staff.php" class="hover:text-teal-200 transition">Staff</a>
                <a href="payments.php" class="hover:text-teal-200 transition">Billing</a>
                <a href="reports.php" class="hover:text-teal-200 transition">Reports</a>
                <div class="flex items-center space-x-3 ml-6">
                    <span class="text-teal-300"><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>)</span>
                    <a href="logout.php" class="text-rose-300 hover:text-rose-200 text-sm font-semibold">Sign out</a>
                </div>
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden">
                <button id="mobileMenuBtn" class="text-white p-2" aria-label="Toggle navigation">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile menu panel -->
        <div id="mobileMenu" class="hidden md:hidden border-t border-teal-800">
            <div class="bg-teal-900 text-white py-4 px-4 sm:px-6 space-y-4">
                <div class="flex flex-col space-y-1 text-xs text-teal-200">
                    <span class="font-semibold text-sm">
                        <?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span class="capitalize">
                        Role: <?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <a href="logout.php" class="text-rose-300 hover:text-rose-200 font-semibold w-fit mt-1">
                        Sign out
                    </a>
                </div>

                <div class="flex flex-col space-y-3 text-sm font-medium">
                    <a href="dashboard.php" class="hover:text-teal-200 transition">Admin Home</a>
                    <a href="bookings.php" class="text-teal-100 semi-bold">Bookings</a>
                    <a href="calendar.php" class="hover:text-teal-200 transition">Calendar</a>
                    <a href="customers.php" class="hover:text-teal-200 transition">Customers</a>
                    <a href="staff.php" class="hover:text-teal-200 transition">Staff</a>
                    <a href="payments.php" class="hover:text-teal-200 transition">Billing</a>
                    <a href="reports.php" class="hover:text-teal-200 transition">Reports</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-20"> <!-- pt-20 to clear fixed header (~5rem = 80px) -->
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Bookings Management</h1>
                <p class="text-sm text-slate-500">Manage and assign cleaning appointments</p>
            </div>
        </header>

        <!-- Statistics -->
        <section class="px-6 py-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="stat-card">
                    <div class="stat-value"><?php echo countBookingsByStatus('pending'); ?></div>
                    <div class="stat-label">Pending Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo countBookingsByStatus('confirmed'); ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo countBookingsByStatus('completed'); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo countBookingsToday(); ?></div>
                    <div class="stat-label">Today's Jobs</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl p-6 shadow mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" placeholder="Customer name, phone, or address"
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="bookings.php" class="btn-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Bookings List -->
            <div class="space-y-4">
                <?php if (empty($bookings)): ?>
                    <div class="booking-card text-center py-12">
                        <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-500 text-lg">No bookings found matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <!-- Bulk Actions -->
                    <div class="bg-white rounded-xl p-4 shadow mb-4" id="bulkActions" style="display: none;">
                        <div class="flex items-center justify-between">
                            <span id="selectedCount" class="text-sm font-medium text-gray-700">0 bookings selected</span>
                            <div class="flex space-x-2">
                                <select id="bulkAction" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Choose action...</option>
                                    <option value="assign_staff">Assign Staff</option>
                                    <option value="confirm">Confirm Selected</option>
                                    <option value="cancel">Cancel Selected</option>
                                    <option value="reschedule">Reschedule Selected</option>
                                </select>
                                <button onclick="BookingsManager.executeBulkAction()" class="btn-primary text-sm">Apply</button>
                                <button onclick="BookingsManager.clearSelection()" class="btn-secondary text-sm">Clear</button>
                            </div>
                        </div>
                    </div>
            
                    <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card selectable-booking" data-booking-id="<?php echo htmlspecialchars($booking['booking_id']); ?>">
                        <!-- Selection Checkbox -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <input type="checkbox" 
                                       class="booking-checkbox w-5 h-5 text-teal-600 rounded focus:ring-teal-500"
                                       value="<?php echo htmlspecialchars($booking['booking_id']); ?>">
                                <div class="flex items-center space-x-4">
                                    <div class="bg-teal-100 p-3 rounded-lg">
                                        <i class="fas fa-calendar-check text-teal-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-lg font-semibold text-gray-900">
                                            #<?php echo htmlspecialchars($booking['booking_id']); ?>
                                        </div>
                                        <div class="text-gray-600">
                                            <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-4">
                                <span class="status-badge status-<?php echo htmlspecialchars($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-clock mr-1"></i>
                                    <?php
                                    $dateTimeStr = $booking['appointment_date'] . ' ' . $booking['appointment_time'];
                                    $timestamp = strtotime($dateTimeStr);
                                    if ($timestamp !== false) {
                                        echo date('M j, Y g:i A', $timestamp);
                                    } else {
                                        echo htmlspecialchars($booking['appointment_date']) . ' at ' . htmlspecialchars($booking['appointment_time']);
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
            
                        <!-- Booking Details -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-phone text-gray-400"></i>
                                <span class="text-gray-600"><?php echo htmlspecialchars($booking['phone']); ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-envelope text-gray-400"></i>
                                <span class="text-gray-600"><?php echo htmlspecialchars($booking['email']); ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-map-marker-alt text-gray-400"></i>
                                <span class="text-gray-600"><?php echo htmlspecialchars($booking['address'] . ', ' . $booking['city']); ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-broom text-gray-400"></i>
                                <span class="text-gray-600"><?php echo htmlspecialchars(ucfirst($booking['service_type'])); ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-users text-gray-400"></i>
                                <span class="text-gray-600"><?php echo htmlspecialchars($booking['assigned_staff'] ?? 'Unassigned'); ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-clock text-gray-400"></i>
                                <span class="text-gray-600"><?php echo htmlspecialchars((string)($booking['duration_minutes'] ?? 180)); ?> mins</span>
                            </div>
                        </div>
            
                        <!-- Enhanced Action Buttons -->
                        <div class="flex flex-wrap gap-2 pt-4 border-t border-gray-200">
                            <!-- Quick Actions Dropdown -->
                            <div class="relative inline-block">
                                <button class="btn-primary flex items-center space-x-2 dropdown-toggle">
                                    <i class="fas fa-cog"></i>
                                    <span>Actions</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 hidden">
                                    <button onclick="BookingsManager.showEditModal('<?php echo $booking['booking_id']; ?>')" 
                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-edit mr-2"></i>Edit Details
                                    </button>
                                    <button onclick="BookingsManager.showRescheduleModal('<?php echo $booking['booking_id']; ?>')" 
                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-calendar-alt mr-2"></i>Reschedule
                                    </button>
                                    <button onclick="BookingsManager.showCancelModal('<?php echo $booking['booking_id']; ?>')" 
                                            class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        <i class="fas fa-times mr-2"></i>Cancel Booking
                                    </button>
                                </div>
                            </div>

                            <button onclick="BookingsManager.showAssignStaffModal('<?php echo $booking['booking_id']; ?>')"
                                    class="btn-primary flex items-center space-x-2">
                                <i class="fas fa-user-plus"></i>
                                <span>Assign Staff</span>
                            </button>

                            <button onclick="BookingsManager.showBookingDetail('<?php echo $booking['booking_id']; ?>')"
                                    class="btn-secondary flex items-center space-x-2">
                                <i class="fas fa-eye"></i>
                                <span>View Details</span>
                            </button>

                            <button onclick="BookingsManager.sendReminder('<?php echo $booking['booking_id']; ?>')"
                                    class="btn-primary flex items-center space-x-2" style="background-color: #8b5cf6;">
                                <i class="fas fa-bell"></i>
                                <span>Send Reminder</span>
                            </button>

                            <!-- Status-specific actions -->
                            <?php if ($booking['status'] === 'pending'): ?>
                                <button onclick="BookingsManager.updateBookingStatus('<?php echo $booking['booking_id']; ?>', 'confirmed')"
                                        class="btn-primary flex items-center space-x-2" style="background-color: #059669;">
                                    <i class="fas fa-check"></i>
                                    <span>Confirm</span>
                                </button>
                            <?php endif; ?>

                            <?php if ($booking['status'] === 'confirmed'): ?>
                                <button onclick="BookingsManager.updateBookingStatus('<?php echo $booking['booking_id']; ?>', 'completed')"
                                        class="btn-primary flex items-center space-x-2" style="background-color: #047857;">
                                    <i class="fas fa-flag-checkered"></i>
                                    <span>Complete</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Placeholder functions to prevent errors before bookings-enhanced.js loads -->
    <script>
        // These will be overridden by bookings-enhanced.js
        if (typeof BookingsManager === 'undefined') {
            var BookingsManager = {
                showEditModal: function(bookingId) { console.log('Edit:', bookingId); },
                showRescheduleModal: function(bookingId) { console.log('Reschedule:', bookingId); },
                showCancelModal: function(bookingId) { console.log('Cancel:', bookingId); },
                showAssignStaffModal: function(bookingId) { console.log('Assign Staff:', bookingId); },
                showBookingDetail: function(bookingId) { console.log('View Details:', bookingId); },
                updateBookingStatus: function(bookingId, status) { console.log('Update Status:', bookingId, status); },
                sendReminder: function(bookingId) { console.log('Send Reminder:', bookingId); },
                executeBulkAction: function() { console.log('Execute Bulk Action'); },
                clearSelection: function() { console.log('Clear Selection'); }
            };
        }

        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('dropdown-toggle')) {
                    const dropdown = e.target.closest('.relative').querySelector('.dropdown-menu');
                    dropdown.classList.toggle('hidden');
                } else {
                    // Close all dropdowns when clicking outside
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        if (!menu.classList.contains('hidden')) {
                            menu.classList.add('hidden');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>

<?php

// Database Functions

function getBookingsWithAssignments($status = 'all', $date = '', $search = '') {
    $conn = db();
    
    $sql = "SELECT b.*,
            GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') as assigned_staff
            FROM bookings b
            LEFT JOIN booking_assignments ba ON b.booking_id = ba.booking_id
            LEFT JOIN staff s ON ba.staff_id = s.id
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if ($status !== 'all') {
        $sql .= " AND b.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if (!empty($date)) {
        $sql .= " AND b.appointment_date = ?";
        $params[] = $date;
        $types .= 's';
    }
    
    if (!empty($search)) {
        $sql .= " AND (b.first_name LIKE ? OR b.last_name LIKE ? OR b.phone LIKE ? OR b.address LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }
    
    $sql .= " GROUP BY b.id ORDER BY b.appointment_date DESC, b.created_at DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $bookings;
}

function getStaffMembers() {
    $conn = db();
    $result = $conn->query("SELECT id, first_name, last_name, phone, email FROM staff WHERE is_active = 1 ORDER BY first_name, last_name");
    $staff = $result->fetch_all(MYSQLI_ASSOC);
    return $staff;
}

function countBookingsByStatus($status) {
    $conn = db();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = ?");
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    return $count;
}

function countBookingsToday() {
    $conn = db();
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE appointment_date = ?");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    return $count;
}

function assignStaffToBooking($bookingId, $staffId) {
    $conn = db();
    
    // Get staff details
    $stmt = $conn->prepare("SELECT first_name, last_name, phone FROM staff WHERE id = ?");
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$staff) {
        $_SESSION['error'] = "Staff member not found";
        header('Location: bookings.php');
        exit;
    }
    
    // Check if assignment already exists
    $stmt = $conn->prepare("SELECT id FROM booking_assignments WHERE booking_id = ?");
    $stmt->bind_param('s', $bookingId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        // Update existing assignment
        $stmt = $conn->prepare("UPDATE booking_assignments SET staff_id = ? WHERE booking_id = ?");
        $stmt->bind_param('is', $staffId, $bookingId);
    } else {
        // Create new assignment
        $stmt = $conn->prepare("INSERT INTO booking_assignments (booking_id, staff_id, assignment_role) VALUES (?, ?, 'lead')");
        $stmt->bind_param('si', $bookingId, $staffId);
    }
    
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Update booking status to confirmed
        $stmt = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $stmt->close();
        
        // Get booking details for notification
        $stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ?");
        $stmt->bind_param('s', $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Send notification to customer
        $staffName = $staff['first_name'] . ' ' . $staff['last_name'];
        $message = "Wasatch Cleaners: Your cleaning has been assigned to {$staffName} ({$staff['phone']}). They'll see you on {$booking['appointment_date']} at {$booking['appointment_time']}!";
        send_sms($booking['phone'], $message);
        
        $_SESSION['success'] = "Staff assigned successfully!";
    } else {
        $_SESSION['error'] = "Failed to assign staff";
    }
    
    header('Location: bookings.php');
    exit;
}

function updateBookingStatus($bookingId, $status) {
    $conn = db();
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
    $stmt->bind_param('ss', $status, $bookingId);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        $_SESSION['success'] = "Booking status updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update booking status";
    }
    
    header('Location: bookings.php');
    exit;
}

// Helper function for SMS (placeholder - implement with your SMS provider)
function send_sms($phone, $message) {
    // Log the SMS for now - implement with OpenPhone later
    error_log("SMS to {$phone}: {$message}");
    // You would implement actual SMS sending here:
    // return send_booking_sms_confirmation([], $bookingId); // Use your existing SMS function
    return true;
}

?>
    <script>
        window.ADMIN_CONFIG = <?= json_encode([
            'basePath' => $basePath,
            'endpoints' => [
                'booking' => $basePath . '/api/booking.php',
                'assignments' => $basePath . '/api/assignments.php',
                'staff' => $basePath . '/api/staff.php',
                'notifications' => $basePath . '/api/notifications.php',
            ],
        ], JSON_UNESCAPED_SLASHES) ?>;
        window.BOOKINGS_PAGE = {
            staff: <?= json_encode($staffMembers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                mobileMenu.classList.toggle('hidden');
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!mobileMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    mobileMenu.classList.add('hidden');
                }
            });
            
            // Close menu when clicking a link
            const menuLinks = mobileMenu.querySelectorAll('a');
            menuLinks.forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.add('hidden');
                });
            });
        }
    });
    </script>
</body>
</html>