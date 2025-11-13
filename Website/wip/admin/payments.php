<?php
/**
 * admin/payments.php - Payments & Billing Management
 */

// If your environment is older PHP, avoid strict_types and newer syntax
// declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_admin(array('admin', 'manager'));

$user = auth_user();
$basePath = dirname($_SERVER['SCRIPT_NAME']);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_invoice_status'])) {
        // Make sure this function exists in bootstrap or another included file
        updateInvoiceStatus($_POST['invoice_id'], $_POST['status']);
    }
}

// Get filter parameters (avoid null coalesce for older PHP)
$status   = isset($_GET['status'])     ? $_GET['status']     : '';
$dateFrom = isset($_GET['date_from'])  ? $_GET['date_from']  : '';
$dateTo   = isset($_GET['date_to'])    ? $_GET['date_to']    : '';
$search   = isset($_GET['search'])     ? $_GET['search']     : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payments &amp; Billing - Wasatch Cleaners</title>
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
        .stat-subtext {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.5rem;
        }
        .invoice-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s;
        }
        .invoice-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-draft { background: #f3f4f6; color: #374151; }
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
                <a href="payments.php" class="text-white font-bold border-b-2 border-teal-400 pb-0.5">Billing</a>
                <a href="reports.php" class="hover:text-teal-200 transition">Reports</a>
                <div class="flex items-center space-x-3 ml-6">
                    <span class="text-teal-300">
                        <?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                        (<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>)
                    </span>
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
                    <a href="payments.php" class="text-teal-100 font-semibold">Billing</a>
                    <a href="reports.php" class="hover:text-teal-200 transition">Reports</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-20">
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Payments &amp; Billing</h1>
                <p class="text-sm text-slate-500">Manage invoices, payments, and billing operations</p>
            </div>
            <div class="flex items-center space-x-3">
                <button id="createInvoiceBtn" class="btn-primary">Create Invoice</button>
            </div>
        </header>

        <!-- Statistics -->
        <section class="px-6 py-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="stat-card">
                    <div class="stat-value" id="totalRevenue">$0.00</div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-subtext">This month</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="pendingInvoices">0</div>
                    <div class="stat-label">Pending Invoices</div>
                    <div class="stat-subtext">Awaiting payment</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="overdueInvoices">0</div>
                    <div class="stat-label">Overdue</div>
                    <div class="stat-subtext">Past due date</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="paidThisMonth">0</div>
                    <div class="stat-label">Paid This Month</div>
                    <div class="stat-subtext">Successful payments</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl p-6 shadow mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" id="statusFilter" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="">All Statuses</option>
                            <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input
                            type="date"
                            name="date_from"
                            value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input
                            type="date"
                            name="date_to"
                            value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2"
                        >
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="btn-primary">Apply Filters</button>
                        <a href="payments.php" class="btn-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Invoices List -->
            <div class="space-y-4" id="invoicesContainer">
                <div class="invoice-card text-center py-12">
                    <i class="fas fa-receipt text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500 text-lg">Loading invoices...</p>
                </div>
            </div>
        </section>
    </main>

    <!-- Create Invoice Modal -->
    <div id="invoiceModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-bold mb-4">Create New Invoice</h2>
            <form id="invoiceForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Booking</label>
                    <select id="bookingSelect" name="booking_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Select a booking</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Number</label>
                    <input
                        type="text"
                        id="invoiceNumber"
                        name="invoice_number"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50"
                        placeholder="Auto-generated"
                        readonly
                    >
                    <p class="text-xs text-gray-500 mt-1">Invoice number will be auto-generated</p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                    <input
                        type="date"
                        id="dueDate"
                        name="due_date"
                        required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2"
                    >
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        id="cancelInvoiceBtn"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-teal-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-teal-700"
                    >
                        Create Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.ADMIN_CONFIG = <?php echo json_encode(array(
            'user' => $user,
            'basePath' => $basePath,
            'endpoints' => array(
                'invoices'  => $basePath . '/api/invoices.php',
                'customers' => $basePath . '/api/customers.php',
                'reports'   => $basePath . '/api/reports.php',
                'bookings'  => $basePath . '/api/bookings.php',
            ),
        ), JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="js/api-client.js" defer></script>
    <script src="js/payments-handler.js" defer></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var mobileMenuBtn = document.getElementById('mobileMenuBtn');
        var mobileMenu = document.getElementById('mobileMenu');
        
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
            var menuLinks = mobileMenu.querySelectorAll('a');
            for (var i = 0; i < menuLinks.length; i++) {
                menuLinks[i].addEventListener('click', function() {
                    mobileMenu.classList.add('hidden');
                });
            }
        }
    });
    </script>
</body>
</html>