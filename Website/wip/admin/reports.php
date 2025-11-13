<?php
/**
 * admin/reports.php - Reports and Insights (Mobile-Responsive Version)
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_admin(['admin', 'manager']);

$user = auth_user();
$basePath = dirname($_SERVER['SCRIPT_NAME']);

// Get report parameters
$reportType = $_GET['report_type'] ?? 'overview';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$period = $_GET['period'] ?? 'month';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports & Insights - Wasatch Cleaners</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/js/litepicker.js" defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
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
        /* Responsive stat value */
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
        .report-card {
            background-color: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }
        /* Responsive report card padding */
        @media (max-width: 640px) {
            .report-card {
                padding: 1rem;
            }
        }
        .chart-container {
            height: 300px;
            position: relative;
        }
        /* Smaller charts on mobile */
        @media (max-width: 640px) {
            .chart-container {
                height: 250px;
            }
        }
        .tab-button {
            padding: 0.75rem 1.5rem;
            border: none;
            background: none;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        /* Reduce padding on mobile */
        @media (max-width: 640px) {
            .tab-button {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }
        }
        .tab-button.active {
            color: #14b8a6;
            border-bottom-color: #14b8a6;
        }
        .tab-button:hover:not(.active) {
            color: #374151;
        }
        #globalLoader {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 9998;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        /* Smaller padding on mobile */
        @media (max-width: 640px) {
            .data-table th,
            .data-table td {
                padding: 0.5rem;
                font-size: 0.875rem;
            }
        }
        .data-table th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
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
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: white;
            margin: auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        /* Better mobile modal */
        @media (max-width: 640px) {
            .modal-content {
                padding: 1.5rem;
                width: 95%;
            }
        }
        .close {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 1.5rem;
            cursor: pointer;
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
                <a href="bookings.php" class="hover:text-teal-200 transition">Bookings</a>
                <a href="calendar.php" class="hover:text-teal-200 transition">Calendar</a>
                <a href="customers.php" class="hover:text-teal-200 transition">Customers</a>
                <a href="staff.php" class="hover:text-teal-200 transition">Staff</a>
                <a href="payments.php" class="hover:text-teal-200 transition">Billing</a>
                <a href="reports.php" class="text-white font-bold border-b-2 border-teal-400 pb-0.5">Reports</a>
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
                    <a href="bookings.php" class="hover:text-teal-200 transition">Bookings</a>
                    <a href="calendar.php" class="hover:text-teal-200 transition">Calendar</a>
                    <a href="customers.php" class="hover:text-teal-200 transition">Customers</a>
                    <a href="staff.php" class="hover:text-teal-200 transition">Staff</a>
                    <a href="payments.php" class="hover:text-teal-200 transition">Billing</a>
                    <a href="reports.php" class="text-teal-100 font-semibold">Reports</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-20">
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Reports & Insights</h1>
                    <p class="text-sm text-slate-500">Analyze business performance and generate reports</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button id="exportReportBtn" class="btn-secondary">
                        <span class="hidden sm:inline">Export Report</span>
                        <span class="sm:hidden">Export</span>
                    </button>
                </div>
            </div>
        </header>

        <section class="px-4 sm:px-6 py-6">
            <!-- Report Filters -->
            <div class="report-card mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Report Type</label>
                        <select id="reportType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="overview" <?php echo $reportType === 'overview' ? 'selected' : ''; ?>>Overview</option>
                            <option value="bookings" <?php echo $reportType === 'bookings' ? 'selected' : ''; ?>>Bookings</option>
                            <option value="revenue" <?php echo $reportType === 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                            <option value="staff" <?php echo $reportType === 'staff' ? 'selected' : ''; ?>>Staff Performance</option>
                            <option value="customers" <?php echo $reportType === 'customers' ? 'selected' : ''; ?>>Customer Analysis</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                        <input type="date" id="dateFrom" value="<?php echo htmlspecialchars($dateFrom); ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                        <input type="date" id="dateTo" value="<?php echo htmlspecialchars($dateTo); ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end">
                        <button id="generateReportBtn" class="btn-primary w-full">
                            <span class="hidden sm:inline">Generate Report</span>
                            <span class="sm:hidden">Generate</span>
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto border-b -mx-4 sm:mx-0">
                    <div class="flex space-x-1 min-w-max px-4 sm:px-0">
                        <button class="tab-button <?php echo $period === 'week' ? 'active' : ''; ?>" data-period="week">Week</button>
                        <button class="tab-button <?php echo $period === 'month' ? 'active' : ''; ?>" data-period="month">Month</button>
                        <button class="tab-button <?php echo $period === 'quarter' ? 'active' : ''; ?>" data-period="quarter">Quarter</button>
                        <button class="tab-button <?php echo $period === 'year' ? 'active' : ''; ?>" data-period="year">Year</button>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
                <div class="stat-card">
                    <div class="stat-value" id="totalBookings">0</div>
                    <div class="stat-label">Total Bookings</div>
                    <div class="stat-subtext">Selected period</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="totalRevenue">$0.00</div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-subtext">Selected period</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="avgBookingValue">$0.00</div>
                    <div class="stat-label">Avg Booking Value</div>
                    <div class="stat-subtext">Selected period</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="completionRate">0%</div>
                    <div class="stat-label">Completion Rate</div>
                    <div class="stat-subtext">Selected period</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="report-card">
                    <h3 class="text-base sm:text-lg font-semibold mb-4">Revenue Trend</h3>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                <div class="report-card">
                    <h3 class="text-base sm:text-lg font-semibold mb-4">Booking Status Distribution</h3>
                    <div class="chart-container">
                        <canvas id="bookingsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Data Tables -->
            <div class="report-card">
                <h3 class="text-base sm:text-lg font-semibold mb-4">Detailed Report Data</h3>
                <div id="reportDataContainer">
                    <div class="text-center py-8 text-gray-500">
                        <p class="text-sm sm:text-base">Select report parameters and generate report to view data</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        window.ADMIN_CONFIG = <?= json_encode([
            'user' => $user,
            'basePath' => $basePath,
            'endpoints' => [
                'reports' => $basePath . '/api/reports.php',
            ],
        ], JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="js/api-client.js" defer></script>
    <script src="js/reports-manager.js" defer></script>
    
    <!-- Export Modal -->
    <div id="exportModal" class="modal">
        <div class="modal-content">
            <span class="close" data-modal="export">&times;</span>
            <h2 class="text-lg sm:text-xl font-bold mb-4">Export Report</h2>
            <form id="exportForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Export Format</label>
                    <select id="exportFormat" name="format" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Select Format</option>
                        <option value="csv">CSV (Comma Separated)</option>
                        <option value="xlsx">Excel (XLSX)</option>
                        <option value="pdf">PDF Document</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Include</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="include_charts" checked class="mr-2">
                            <span class="text-sm">Charts and Graphs</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="include_summary" checked class="mr-2">
                            <span class="text-sm">Summary Statistics</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="include_details" checked class="mr-2">
                            <span class="text-sm">Detailed Data</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="cancelExportBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-teal-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-teal-700">Export Report</button>
                </div>
            </form>
        </div>
    </div>
    
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