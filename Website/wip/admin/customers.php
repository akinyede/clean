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
    <title>Customer Management - Wasatch Cleaners</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/js/litepicker.js" defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <style>
        /* Reuse your existing styles */
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
        .table-header {
            background-color: #f8fafc;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
        }
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .page-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background-color: white;
            cursor: pointer;
        }
        .page-btn.active {
            background-color: #14b8a6;
            color: white;
            border-color: #14b8a6;
        }
        .page-btn:hover:not(.active) {
            background-color: #f8fafc;
            border-color: #94a3b8;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 10000; /* Higher z-index like staff.php */
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
            z-index: 10001;
        }
        .close {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10002;
        }
        .close:hover {
            color: #374151;
            background-color: #f3f4f6;
            border-radius: 50%;
        }
        body.modal-open {
            overflow: hidden;
        }
        #globalLoader {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 9998;
            display: none;
            align-items: center;
            justify-content: center;
        }

        /* Responsive customer list: hide table on mobile, show cards instead */
        .customers-desktop {
            display: none;
        }

        .customers-mobile {
            display: block;
        }

        /* Card styling for mobile customer view */
        .customer-card {
            background-color: #ffffff;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            border: 1px solid #e5e7eb;
        }
        .customer-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .customer-card-name {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
        }
        .customer-card-id {
            font-size: 0.75rem;
            color: #9ca3af;
        }
        .customer-card-status {
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-inactive {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        .customer-card-info {
            margin-top: 0.5rem;
            border-top: 1px solid #e5e7eb;
            padding-top: 0.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        .customer-card-field {
            font-size: 0.75rem;
        }
        .customer-card-label {
            color: #9ca3af;
            margin-bottom: 0.1rem;
        }
        .customer-card-value {
            color: #111827;
            font-weight: 500;
        }
        .customer-card-actions {
            margin-top: 0.75rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        .card-action-btn {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid #e5e7eb;
            background-color: #ffffff;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .card-action-view {
            color: #0f766e;
            border-color: #99f6e4;
        }
        .card-action-edit {
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        /* On screens >= 768px (md), show table and hide cards */
        @media (min-width: 768px) {
            .customers-desktop {
                display: block;
            }
            .customers-mobile {
                display: none;
            }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">
    <div id="globalLoader" class="flex items-center justify-center">
        <div class="text-white text-lg">Loading...</div>
    </div>

    <!-- Fixed Top Navigation -->
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
                <a href="customers.php" class="text-white font-bold border-b-2 border-teal-400 pb-0.5">Customers</a>
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
                    <a href="bookings.php" class="hover:text-teal-200 transition">Bookings</a>
                    <a href="calendar.php" class="hover:text-teal-200 transition">Calendar</a>
                    <a href="customers.php" class="text-teal-100 font-semibold">Customers</a>
                    <a href="staff.php" class="hover:text-teal-200 transition">Staff</a>
                    <a href="payments.php" class="hover:text-teal-200 transition">Billing</a>
                    <a href="reports.php" class="hover:text-teal-200 transition">Reports</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-20">
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Customer Management</h1>
                <p class="text-sm text-slate-500">Search, filter, and manage your customer base.</p>
            </div>
            <div class="flex items-center space-x-3">
                <button id="addCustomerModalBtn" class="btn-primary">Add New Customer</button>
            </div>
        </header>

        <!-- Stats Cards -->
        <section class="px-6 py-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="stat-card">
                    <p class="stat-label">Total Customers</p>
                    <p class="stat-value" id="totalCustomers">0</p>
                    <p class="stat-subtext">+2.3%</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">New (Last 30d)</p>
                    <p class="stat-value" id="newCustomers">0</p>
                    <p class="stat-subtext">+0.2%</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Active Customers</p>
                    <p class="stat-value" id="activeCustomers">0</p>
                    <p class="stat-subtext">+1.1%</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Churn Rate</p>
                    <p class="stat-value" id="churnRate">0.0%</p>
                    <p class="stat-subtext text-red-500">-0.2%</p>
                </div>
            </div>
        </section>

        <!-- Search & Actions Bar -->
        <section class="px-6 py-4 bg-white border-b border-slate-200">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-1">
                    <input type="text" id="customerSearch" placeholder="Search customers by name, email, or ID..." class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                </div>
                <div class="flex gap-2">
                    <button id="filterBtn" class="btn-secondary">Filter</button>
                    <button id="exportCsvBtn" class="btn-secondary">Export CSV</button>
                </div>
            </div>
        </section>

        <!-- Customer Table + Mobile Cards -->
        <section class="px-6 py-6">
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <!-- DESKTOP / TABLET VIEW -->
                <div class="customers-desktop">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="table-header">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                    <input type="checkbox" id="selectAll" class="mr-2">
                                    Customer Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Customer ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Registration</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Last Order</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Total Orders</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Total Spent</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody" class="bg-white divide-y divide-slate-200">
                            <tr>
                                <td colspan="9" class="px-6 py-4 text-center text-slate-500">Loading customers...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE VIEW: CARDS -->
                <div class="customers-mobile p-3">
                    <div id="customerCardsContainer">
                        <div class="text-center py-4 text-slate-500 text-sm">
                            Loading customers...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="pagination mt-4" id="paginationContainer"></div>
        </section>
    </main>

    <!-- Add/Edit Customer Modal -->
    <div id="customerModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle" class="text-xl font-bold mb-4">Add New Customer</h2>
            <form id="customerForm">
                <input type="hidden" id="customerId" name="id">
                <div class="mb-4">
                    <label for="firstName" class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" id="firstName" name="first_name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div class="mb-4">
                    <label for="lastName" class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" id="lastName" name="last_name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="email" name="email" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div class="mb-4">
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" id="phone" name="phone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div class="mb-4">
                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                    <input type="text" id="address" name="address" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div class="mb-4">
                    <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                    <input type="text" id="city" name="city" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div class="mb-4">
                    <label for="state" class="block text-sm font-medium text-gray-700">State</label>
                    <input type="text" id="state" name="state" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="cancelModalBtn" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-teal-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-teal-700">Save Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer CRM Detail Modal -->
    <div id="customerDetailModal" class="modal">
        <div class="modal-content max-w-4xl">
            <span class="close" data-action="close-detail">&times;</span>
            <h2 class="text-2xl font-bold mb-4">Customer Details</h2>
            <div id="customerDetailContent">
                <p class="text-slate-500">Loading customer data...</p>
            </div>
        </div>
    </div>

    <script>
        window.ADMIN_CONFIG = <?= json_encode([
            'user' => $user,
            'basePath' => $basePath,
            'endpoints' => [
                'customers' => $basePath . '/api/customers.php',
                'staff' => $basePath . '/api/staff.php',
                'booking' => $basePath . '/api/booking.php',
            ],
        ], JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="js/api-client.js" defer></script>
    <script src="js/customer-manager.js" defer></script>
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
