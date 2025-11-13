<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin(['admin', 'manager', 'staff']);

$user = auth_user();
$basePath = dirname($_SERVER['SCRIPT_NAME']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Wasatch Cleaners</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js" defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <style>
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            background-color: #f43f5e;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #ffffff;
            box-shadow: 0 12px 25px -12px rgba(244, 63, 94, 0.7);
            transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #e11d48;
            box-shadow: 0 16px 35px -15px rgba(225, 29, 72, 0.7);
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
        /* Mobile button adjustments */
        @media (max-width: 640px) {
            .btn-primary, .btn-secondary {
                padding: 0.5rem 0.75rem;
                font-size: 0.8125rem;
            }
        }
        .nav-link {
            display: block;
            border-radius: 0.75rem;
            padding: 0.5rem 0.75rem;
            color: #cbd5f5;
            transition: background-color 0.2s ease, color 0.2s ease;
            text-decoration: none;
        }
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #f8fafc;
        }
        .section-heading {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 640px) {
            .section-heading {
                font-size: 1.25rem;
                margin-bottom: 1rem;
            }
        }
        .stat-card {
            background-color: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        @media (max-width: 640px) {
            .stat-card {
                padding: 1rem;
            }
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
        @media (max-width: 640px) {
            .stat-value {
                font-size: 1.5rem;
            }
        }
        .stat-subtext {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.5rem;
        }
        .view-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid #e5e7eb;
            background-color: #ffffff;
            color: #1f2937;
            transition: background-color 0.2s ease, border-color 0.2s ease;
            cursor: pointer;
        }
        @media (max-width: 640px) {
            .view-btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.8125rem;
            }
        }
        .view-btn.active {
            background-color: #14b8a6;
            color: #ffffff;
            border-color: #14b8a6;
        }
        .view-btn:hover {
            background-color: #f8fafc;
            border-color: #94a3b8;
        }
        .chart-container {
            height: 200px;
        }
        @media (max-width: 640px) {
            .chart-container {
                height: 180px;
            }
        }
        /* Mobile menu overlay */
        .mobile-menu-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 40;
        }
        .mobile-menu-overlay.active {
            display: block;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">
    
    <!-- Mobile Header (visible only on mobile/tablet) -->
    <header class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-teal-900 text-white shadow-md">
        <div class="flex items-center justify-between px-4 py-3">
            <div class="flex items-center space-x-3">
                <img src="../logo.png" alt="Wasatch Cleaners logo" class="h-10 w-10 rounded-full bg-white p-1 object-contain" />
                <div>
                    <p class="text-lg font-semibold">Wasatch Cleaners</p>
                    <p class="text-xs text-teal-300 uppercase tracking-wider">Operations Console</p>
                </div>
            </div>
            <button id="mobileMenuBtn" class="text-white p-2">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Menu Overlay -->
    <div id="mobileMenuOverlay" class="mobile-menu-overlay"></div>

    <!-- Mobile Menu Drawer -->
    <div id="mobileMenu" class="lg:hidden fixed top-0 left-0 bottom-0 w-64 bg-teal-900 text-white transform -translate-x-full transition-transform duration-300 ease-in-out z-50">
        <div class="flex items-center justify-between px-4 py-3 border-b border-teal-800">
            <span class="text-lg font-semibold">Menu</span>
            <button id="closeMobileMenu" class="text-white p-2">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <nav class="px-4 py-6 space-y-2 text-sm">
            <a href="#overview" class="nav-link">Dashboard Overview</a>
            <a href="bookings.php" class="nav-link">Manage Bookings</a>
            <a href="calendar.php" class="nav-link">Calendar & Scheduling</a>
            <a href="customers.php" class="nav-link">Customer Management</a>
            <a href="staff.php" class="nav-link">Staff Management</a>
            <a href="payments.php" class="nav-link">Payments & Billing</a>
            <a href="reports.php" class="nav-link">Reports</a>
            <a href="settings.php" class="nav-link">Settings</a>
        </nav>
        <div class="absolute bottom-0 left-0 right-0 px-4 py-4 border-t border-teal-800">
            <p class="text-xs text-teal-400 uppercase tracking-wide mb-1">Signed in as</p>
            <p class="text-sm font-semibold"><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-xs text-teal-500"><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></p>
            <a href="logout.php" class="mt-4 inline-flex items-center text-sm font-semibold text-rose-300 hover:text-rose-200">Sign out</a>
        </div>
    </div>

    <div class="flex">
        <!-- Desktop Sidebar (hidden on mobile) -->
        <aside class="hidden lg:flex lg:w-64 bg-teal-900 text-white flex-col min-h-screen">
            <div class="flex items-center space-x-3 px-6 py-6 border-b border-teal-800">
                <img src="../logo.png" alt="Wasatch Cleaners logo" class="h-10 w-10 rounded-full bg-white p-1 object-contain" />
                <div>
                    <p class="text-lg font-semibold">Wasatch Cleaners</p>
                    <p class="text-xs text-teal-400 uppercase tracking-wider">Operations Console</p>
                </div>
            </div>
            <nav class="flex-1 px-4 py-6 space-y-2 text-sm">
                <a href="#overview" class="nav-link">Dashboard Overview</a>
                <a href="bookings.php" class="nav-link">Manage Bookings</a>
                <a href="calendar.php" class="nav-link">Calendar & Scheduling</a>
                <a href="customers.php" class="nav-link">Customer Management</a>
                <a href="staff.php" class="nav-link">Staff Management</a>
                <a href="payments.php" class="nav-link">Payments & Billing</a>
                <a href="reports.php" class="nav-link">Reports</a>
                <a href="settings.php" class="nav-link">Settings</a>
            </nav>
            <div class="px-6 py-4 border-t border-teal-800">
                <p class="text-xs text-teal-400 uppercase tracking-wide mb-1">Signed in as</p>
                <p class="text-sm font-semibold"><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-teal-500"><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></p>
                <a href="logout.php" class="mt-4 inline-flex items-center text-sm font-semibold text-rose-300 hover:text-rose-200">Sign out</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 lg:pt-0 pt-16"> <!-- Add pt-16 for mobile header -->
            <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Dashboard</h1>
                        <p class="text-sm text-slate-500">Manage bookings, clients, staff, and operations at a glance.</p>
                    </div>
                    <div class="flex items-center space-x-2 sm:space-x-3">
                        <button id="notificationToggle" class="relative inline-flex items-center justify-center rounded-full border border-slate-200 p-2 hover:bg-slate-50 transition">
                            <span class="sr-only">View notifications</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span id="notificationBadge" class="absolute -top-1 -right-1 min-w-[1.25rem] rounded-full bg-rose-500 px-1 text-center text-xs font-semibold text-white hidden">0</span>
                        </button>
                        <a href="bookings.php" id="quickAddBooking" class="btn-primary whitespace-nowrap">
                            <span class="hidden sm:inline">Manage Bookings</span>
                            <span class="sm:hidden">Bookings</span>
                        </a>
                        <a href="customers.php" id="quickAddClient" class="btn-secondary whitespace-nowrap hidden sm:inline-flex">Add Client</a>
                    </div>
                </div>
            </header>

            <!-- Today's Overview -->
            <section id="overview" class="px-4 sm:px-6 py-6">
                <h2 class="section-heading">Today's Overview</h2>
                <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
                    <div class="stat-card" id="statScheduled">
                        <p class="stat-label">Scheduled</p>
                        <p class="stat-value">0</p>
                        <p class="stat-subtext">Bookings set for today</p>
                    </div>
                    <div class="stat-card" id="statInProgress">
                        <p class="stat-label">In Progress</p>
                        <p class="stat-value">0</p>
                        <p class="stat-subtext">Teams currently assigned</p>
                    </div>
                    <div class="stat-card" id="statCompleted">
                        <p class="stat-label">Completed</p>
                        <p class="stat-value">0</p>
                        <p class="stat-subtext">Finished jobs today</p>
                    </div>
                    <div class="stat-card" id="statCancelled">
                        <p class="stat-label">Cancelled</p>
                        <p class="stat-value">0</p>
                        <p class="stat-subtext">Bookings cancelled today</p>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3 mt-6">
                    <div class="bg-white rounded-xl p-4 sm:p-6 shadow">
                        <h3 class="font-semibold text-slate-900 mb-4 text-sm sm:text-base">Tasks by Status</h3>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-4 sm:p-6 shadow">
                        <h3 class="font-semibold text-slate-900 mb-4 text-sm sm:text-base">Upcoming Jobs</h3>
                        <div id="upcomingJobsList" class="space-y-2 text-sm">
                            <p class="text-slate-500">Loading...</p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-4 sm:p-6 shadow md:col-span-2 lg:col-span-1">
                        <h3 class="font-semibold text-slate-900 mb-4 text-sm sm:text-base">Quick Stats</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs sm:text-sm text-slate-500">Total Clients</p>
                                <p class="text-xl sm:text-2xl font-bold" id="metricClients">0</p>
                            </div>
                            <div>
                                <p class="text-xs sm:text-sm text-slate-500">Active Staff</p>
                                <p class="text-xl sm:text-2xl font-bold" id="metricStaff">0</p>
                            </div>
                            <div>
                                <p class="text-xs sm:text-sm text-slate-500">Revenue This Month</p>
                                <p class="text-xl sm:text-2xl font-bold" id="metricRevenue">$0.00</p>
                            </div>
                            <div>
                                <p class="text-xs sm:text-sm text-slate-500">New Bookings (This Week)</p>
                                <p class="text-xl sm:text-2xl font-bold" id="metricBookings">0</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Calendar Section -->
            <section id="calendar" class="px-4 sm:px-6 py-6">
                <h2 class="section-heading">Calendar & Scheduling</h2>
                <div class="bg-white rounded-xl p-4 sm:p-6 shadow">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                        <div class="flex items-center space-x-2 overflow-x-auto pb-2 sm:pb-0">
                            <button id="calendarViewDay" class="view-btn active">Day</button>
                            <button id="calendarViewWeek" class="view-btn">Week</button>
                            <button id="calendarViewMonth" class="view-btn">Month</button>
                        </div>
                        <input type="text" id="calendarDatePicker" class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full sm:w-auto" placeholder="Select Date">
                    </div>
                    <div id="calendarContainer" class="mt-4">
                        <p class="text-slate-500 text-center text-sm">Loading calendar events...</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Notification Modal -->
    <div id="notificationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 sm:px-6 py-4">
                <div>
                    <h2 class="text-base sm:text-lg font-semibold text-slate-900">Notifications</h2>
                    <p class="text-xs sm:text-sm text-slate-500">Stay updated on new bookings and changes.</p>
                </div>
                <button id="closeNotificationModal" class="text-slate-500 hover:text-slate-700">
                    <span class="sr-only">Close</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="notificationList" class="max-h-[60vh] overflow-y-auto divide-y divide-slate-100">
                <div class="px-4 sm:px-6 py-8 text-center text-slate-400 text-sm">Loading notifications…</div>
            </div>
            <div class="flex items-center justify-between border-t border-slate-200 px-4 sm:px-6 py-4">
                <button id="markAllNotifications" class="text-xs sm:text-sm font-semibold text-rose-500 hover:text-rose-600">Mark all as read</button>
                <button id="refreshNotifications" class="text-xs sm:text-sm text-slate-500 hover:text-slate-700">Refresh</button>
            </div>
        </div>
    </div>

    <script>
        window.ADMIN_CONFIG = <?= json_encode([
            'user' => $user,
            'basePath' => $basePath,
            'endpoints' => [
                'overview' => $basePath . '/api/overview.php',
                'calendar' => $basePath . '/api/bookings.php',
                'customers' => $basePath . '/api/customers.php',
                'staff' => $basePath . '/api/staff.php',
                'booking' => $basePath . '/api/booking.php',
                'invoices' => $basePath . '/api/invoices.php',
                'settings' => $basePath . '/api/settings.php',
                'notifications' => $basePath . '/api/notifications.php',
                'reports' => $basePath . '/api/reports.php',
            ],
        ], JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="js/api-client.js" defer></script>
    <script src="js/booking-manager.js" defer></script> 
    <script src="js/dashboard.js" defer></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const closeMobileMenu = document.getElementById('closeMobileMenu');
        const mobileMenu = document.getElementById('mobileMenu');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
        
        function openMobileMenu() {
            mobileMenu.classList.remove('-translate-x-full');
            mobileMenuOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMobileMenuFunc() {
            mobileMenu.classList.add('-translate-x-full');
            mobileMenuOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', openMobileMenu);
        }
        
        if (closeMobileMenu) {
            closeMobileMenu.addEventListener('click', closeMobileMenuFunc);
        }
        
        if (mobileMenuOverlay) {
            mobileMenuOverlay.addEventListener('click', closeMobileMenuFunc);
        }
        
        // Close menu when clicking a link
        const menuLinks = mobileMenu.querySelectorAll('a');
        menuLinks.forEach(link => {
            link.addEventListener('click', () => {
                closeMobileMenuFunc();
            });
        });
    });
    </script>
</body>
</html>