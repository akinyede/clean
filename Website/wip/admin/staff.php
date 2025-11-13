<?php
// admin/staff.php - Staff Management

declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_admin(array('admin', 'manager', 'staff'));
$user = auth_user();
$basePath = dirname($_SERVER['SCRIPT_NAME']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Management - Wasatch Cleaners</title>
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
            z-index: 10000;
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
        .staff-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            transition: box-shadow 0.2s;
        }
        .staff-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .staff-initials {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.25rem;
            color: white;
            margin-bottom: 1rem;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Responsive staff list: hide table on mobile, show cards instead (like customers.php) */
        .staff-desktop {
            display: none;
        }
        .staff-mobile {
            display: block;
        }
        @media (min-width: 768px) {
            .staff-desktop {
                display: block;
            }
            .staff-mobile {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">
    <div id="globalLoader" class="flex items-center justify-center">
        <div class="text-white text-lg">Loading...</div>
    </div>

    <!-- Top Navigation -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-teal-900 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
            <!-- Logo & Brand (Left) -->
            <div class="flex items-center space-x-3 min-w-0">
                <img src="../logo.png" alt="Wasatch Cleaners logo" class="h-10 w-10 rounded-full bg-white p-1 object-contain" />
                <div class="truncate">
                    <p class="text-lg font-semibold leading-tight truncate">Wasatch Cleaners</p>
                    <p class="text-xs text-teal-300 uppercase tracking-wider">Operations Console</p>
                </div>
            </div>

            <!-- Desktop Navigation + User Info (Right) -->
            <div class="hidden md:flex items-center space-x-6 text-sm font-medium">
                <a href="dashboard.php" class="hover:text-teal-200 transition">Admin Home</a>
                <a href="bookings.php" class="hover:text-teal-200 transition">Bookings</a>
                <a href="calendar.php" class="hover:text-teal-200 transition">Calendar</a>
                <a href="customers.php" class="hover:text-teal-200 transition">Customers</a>
                <a href="staff.php" class="text-white font-bold border-b-2 border-teal-400 pb-0.5">Staff</a>
                <a href="payments.php" class="hover:text-teal-200 transition">Billing</a>
                <a href="reports.php" class="hover:text-teal-200 transition">Reports</a>
                <div class="flex items-center space-x-3 ml-4">
                    <span class="text-teal-300 truncate max-w-xs">
                        <?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                        (<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>)
                    </span>
                    <a href="logout.php" class="text-rose-300 hover:text-rose-200 text-sm font-semibold whitespace-nowrap">
                        Sign out
                    </a>
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
                    <a href="staff.php" class="text-teal-100 font-semibold">Staff</a>
                    <a href="payments.php" class="hover:text-teal-200 transition">Billing</a>
                    <a href="reports.php" class="hover:text-teal-200 transition">Reports</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-20 sm:pt-24">
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Staff Management</h1>
                <p class="text-sm text-slate-500">Track availability, assignments, and documents.</p>
            </div>
            <div class="flex items-center space-x-3">
                <button id="addStaffModalBtn" class="btn-primary">Add New Staff</button>
            </div>
        </header>

        <section class="px-6 py-4 bg-white border-b border-slate-200">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-1">
                    <input
                        type="text"
                        id="staffSearch"
                        placeholder="Search staff by name, email, or phone..."
                        class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500"
                    >
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                </div>
                <div class="flex gap-2">
                    <button id="filterBtn" class="btn-secondary">Filter</button>
                    <!-- HIDDEN ON MOBILE, SHOWN ON MD+ -->
                    <button id="switchViewBtn" class="btn-secondary hidden md:inline-flex">
                        Switch to Card View
                    </button>
                </div>
            </div>
        </section>

        <!-- Staff Container -->
        <section class="px-6 py-6">
            <div id="staffTableContainer" class="staff-desktop bg-white rounded-xl shadow overflow-x-auto">
                <table class="min-w-full table-auto divide-y divide-slate-200 text-sm">
                    <thead class="table-header">
                        <tr>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden md:table-cell">
                                Assigned Cleans
                            </th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden lg:table-cell">
                                Email
                            </th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden lg:table-cell">
                                Phone
                            </th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden md:table-cell">
                                Last Active
                            </th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody" class="bg-white divide-y divide-slate-200">
                        <tr>
                            <td colspan="8" class="px-4 sm:px-6 py-4 text-center text-slate-500">
                                Loading staff...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- MOBILE VIEW: STAFF CARDS -->
            <div id="staffCardContainer" class="staff-mobile grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mt-6">
                <div class="col-span-full text-center text-slate-500 text-sm py-4" id="staffCardPlaceholder">
                    Loading staff...
                </div>
            </div>

            <div class="pagination mt-4" id="paginationContainer"></div>
        </section>
    </main>

    <!-- Add/Edit Staff Modal -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <button type="button" class="close" aria-label="Close modal">&times;</button>
            <h2 id="modalTitle" class="text-xl font-bold mb-4">Add New Staff</h2>
            <form id="staffForm">
                <input type="hidden" id="staffId" name="id">

                <div class="mb-4">
                    <label for="firstName" class="block text-sm font-medium text-gray-700">First Name</label>
                    <input
                        type="text"
                        id="firstName"
                        name="first_name"
                        required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500"
                    >
                </div>

                <div class="mb-4">
                    <label for="lastName" class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input
                        type="text"
                        id="lastName"
                        name="last_name"
                        required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500"
                    >
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500"
                    >
                </div>

                <div class="mb-4">
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500"
                    >
                </div>

                <div class="mb-4">
                    <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                    <select
                        id="role"
                        name="role"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500"
                    >
                        <option value="cleaner">Cleaner</option>
                        <option value="team_lead">Team Lead</option>
                        <option value="inspector">Inspector</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="colorTag" class="block text-sm font-medium text-gray-700">Color Tag</label>
                    <input
                        type="color"
                        id="colorTag"
                        name="color_tag"
                        value="#6b7280"
                        class="mt-1 block w-full h-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-teal-500 focus:border-teal-500"
                    >
                </div>

                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-teal-500 focus:border-teal-500"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        id="cancelModalBtn"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-teal-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-teal-700"
                    >
                        Save Staff
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
                'staff'       => $basePath . '/api/staff.php',
                'assignments' => $basePath . '/api/assignments.php',
                'booking'     => $basePath . '/api/booking.php',
            ),
        ), JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="js/api-client.js" defer></script>
    <script src="js/staff-manager.js" defer></script>

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
