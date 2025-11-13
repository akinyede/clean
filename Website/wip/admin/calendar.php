<?php
// admin/calendar.php - Calendar Management
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
     <meta http-equiv="Content-Security-Policy"
          content="
            default-src 'self';
            script-src 'self' https://cdn.jsdelivr.net https://cdn.tailwindcss.com 'unsafe-inline' 'unsafe-eval';
            style-src 'self' https://cdn.jsdelivr.net https://cdn.tailwindcss.com 'unsafe-inline';
            font-src 'self' https://cdn.jsdelivr.net data:;
            img-src 'self' data: https:;
            connect-src 'self';
          ">
    <title>Calendar - Wasatch Cleaners</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js" defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <script src="js/api-client.js" defer></script>
    <script src="js/calendar.js" defer></script>

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
        .fc .fc-toolbar.fc-header-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between; /* title in middle, buttons on sides */
            gap: 0.75rem;
            padding-bottom: 0.5rem;
        }
        .fc .fc-toolbar-chunk {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .fc .fc-button {
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            color: #1f2937;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.35rem 0.6rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: #14b8a6;
            border-color: #0d9488;
            color: #ffffff;
        }
        .fc .fc-button-primary:disabled {
            background-color: #e5e7eb;
            border-color: #d1d5db;
            color: #9ca3af;
            box-shadow: none;
        }
        .fc .fc-toolbar-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0f172a;
            text-align: center;
            margin: 0 0.5rem;
            white-space: nowrap;
        }
        @media (max-width: 640px) {
            .fc .fc-toolbar.fc-header-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .fc .fc-toolbar-chunk {
                justify-content: center;
                flex-wrap: wrap;
            }
            .fc .fc-toolbar-chunk:first-child,
            .fc .fc-toolbar-chunk:last-child {
                order: 1; /* prev/next + views on top/bottom */
            }
            .fc .fc-toolbar-title {
                order: 0;
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen">
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
                <a href="calendar.php" class="text-white font-bold border-b-2 border-teal-400 pb-0.5">Calendar</a>
                <a href="customers.php" class="hover:text-teal-200 transition">Customers</a>
                <a href="staff.php" class="hover:text-teal-200 transition">Staff</a>
                <a href="billing.php" class="hover:text-teal-200 transition">Billing</a>
                <a href="reports.php" class="hover:text-teal-200 transition">Reports</a>
                <div class="flex items-center space-x-3 ml-4">
                    <span class="text-teal-300 truncate max-w-xs">
                        <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>)
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
                    <a href="calendar.php" class="text-teal-100 font-semibold">Calendar</a>
                    <a href="customers.php" class="hover:text-teal-200 transition">Customers</a>
                    <a href="staff.php" class="hover:text-teal-200 transition">Staff</a>
                    <a href="payments.php" class="hover:text-teal-200 transition">Billing</a>
                    <a href="reports.php" class="hover:text-teal-200 transition">Reports</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-20 sm:pt-24">
        <!-- Page header -->
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Scheduling Calendar</h1>
                    <p class="text-sm text-slate-500">
                        View bookings, assign teams, and add manual tasks.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <button id="openManualTask" class="btn-primary w-full sm:w-auto justify-center">
                        Add Manual Task
                    </button>
                </div>
            </div>
        </header>

        <!-- Calendar section -->
        <section class="py-4 sm:py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    id="calendar"
                    class="bg-white rounded-2xl shadow border border-slate-200 p-2 sm:p-4 min-h-[500px] overflow-hidden"
                ></div>
            </div>
        </section>
    </main>

    <!-- Manual Task Modal -->
    <div
        id="manualTaskModal"
        class="fixed inset-0 bg-black/40 hidden items-start md:items-center justify-center p-4 sm:p-6 z-50"
    >
        <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl max-h-[90vh] flex flex-col">
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="text-base sm:text-lg font-semibold text-slate-900">Add Manual Booking</h2>
                    <p class="text-xs sm:text-sm text-slate-500">Create a calendar block or internal task.</p>
                </div>
                <button
                    type="button"
                    data-action="close-modal"
                    class="text-slate-500 hover:text-slate-700 text-xl leading-none"
                    aria-label="Close"
                >
                    &times;
                </button>
            </div>

            <form
                id="manualTaskForm"
                class="px-4 sm:px-6 py-4 space-y-4 overflow-y-auto"
            >
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block text-sm font-medium text-slate-700">
                        Service Type
                        <select
                            name="service_type"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="regular">Regular Cleaning</option>
                            <option value="deep">Deep Cleaning</option>
                            <option value="move">Move In/Out</option>
                            <option value="onetime">One-Time</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">
                        Frequency
                        <select
                            name="frequency"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="onetime">One-Time</option>
                            <option value="weekly">Weekly</option>
                            <option value="biweekly">Bi-Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block text-sm font-medium text-slate-700">
                        Date
                        <input
                            type="date"
                            name="appointment_date"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            required
                        >
                    </label>
                    <label class="block text-sm font-medium text-slate-700">
                        Time
                        <input
                            type="time"
                            name="appointment_time"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            required
                        >
                    </label>
                </div>

                <label class="block text-sm font-medium text-slate-700">
                    Client Name
                    <input
                        type="text"
                        name="first_name"
                        placeholder="First Name"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        required
                    >
                </label>

                <label class="block text-sm font-medium text-slate-700">
                    Client Email
                    <input
                        type="email"
                        name="email"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        required
                    >
                </label>

                <label class="block text-sm font-medium text-slate-700">
                    Notes
                    <textarea
                        name="notes"
                        rows="3"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    ></textarea>
                </label>

                <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-end gap-2 sm:gap-3 pt-1">
                    <button
                        type="button"
                        data-action="close-modal"
                        class="btn-secondary w-full sm:w-auto justify-center"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="btn-primary w-full sm:w-auto justify-center"
                    >
                        Save Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.ADMIN_CONFIG = <?= json_encode([
            'user' => $user,
            'basePath' => $basePath,
            'endpoints' => [
                'calendar' => $basePath . '/api/bookings.php',
                'booking' => $basePath . '/api/booking.php',
                'notifications' => $basePath . '/api/notifications.php',
            ],
        ], JSON_UNESCAPED_SLASHES) ?>;
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');

            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    mobileMenu.classList.toggle('hidden');
                });
                // Close menu when clicking outside
                document.addEventListener('click', function (e) {
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
