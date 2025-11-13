<?php
/**
 * admin/business_settings.php - Business Settings Management
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

require_admin(['admin', 'manager']);

$user = auth_user();
$basePath = dirname($_SERVER['SCRIPT_NAME']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Business Settings - Wasatch Cleaners</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        .settings-card {
            background-color: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }
        .settings-section {
            margin-bottom: 2rem;
        }
        .settings-section:last-child {
            margin-bottom: 0;
        }
        .section-heading {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f3f4f6;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #14b8a6;
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .checkbox-group input[type="checkbox"] {
            width: 1rem;
            height: 1rem;
            border-radius: 0.25rem;
            border: 1px solid #d1d5db;
        }
        #globalLoader {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 9998;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .success-message {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: none;
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
                <a href="business_settings.php" class="text-white font-bold border-b-2 border-teal-400 pb-0.5">Settings</a>
                <div class="flex items-center space-x-3 ml-6">
                    <span class="text-teal-300"><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>)</span>
                    <a href="logout.php" class="text-rose-300 hover:text-rose-200 text-sm font-semibold">Sign out</a>
                </div>
            </div>

            <!-- Mobile menu button (placeholder) -->
            <button class="md:hidden text-white">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="pt-20"> <!-- pt-20 to clear fixed header (~5rem = 80px) -->
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Business Settings</h1>
                <p class="text-sm text-slate-500">Configure your business preferences and notification settings</p>
            </div>
            <div class="flex items-center space-x-3">
                <button id="saveSettingsBtn" class="btn-primary">Save Changes</button>
            </div>
        </header>

        <section class="px-6 py-6">
            <div id="successMessage" class="success-message">
                Settings saved successfully!
            </div>

            <div class="settings-card">
                <div class="settings-section">
                    <h2 class="section-heading">Business Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label for="businessName" class="form-label">Business Name</label>
                            <input type="text" id="businessName" name="business_name" class="form-control" 
                                placeholder="Wasatch Cleaners">
                        </div>
                        <div class="form-group">
                            <label for="supportEmail" class="form-label">Support Email</label>
                            <input type="email" id="supportEmail" name="email" class="form-control" 
                                placeholder="support@wasatchcleaners.com">
                        </div>
                        <div class="form-group">
                            <label for="phoneNumber" class="form-label">Phone Number</label>
                            <input type="text" id="phoneNumber" name="phone" class="form-control" 
                                placeholder="(385) 213-8900">
                        </div>
                        <div class="form-group">
                            <label for="defaultDuration" class="form-label">Default Service Duration (minutes)</label>
                            <input type="number" id="defaultDuration" name="default_duration" class="form-control" 
                                min="60" step="15" value="180">
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <h2 class="section-heading">Notification Preferences</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="form-label">Email Notifications</label>
                            <div class="space-y-2">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="notifyEmailBookings" name="notify_email_bookings" value="1">
                                    <label for="notifyEmailBookings" class="text-sm text-gray-700">New booking notifications</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="notifyEmailPayments" name="notify_email_payments" value="1">
                                    <label for="notifyEmailPayments" class="text-sm text-gray-700">Payment received notifications</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="notifyEmailStaff" name="notify_email_staff" value="1">
                                    <label for="notifyEmailStaff" class="text-sm text-gray-700">Staff assignment notifications</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">SMS Notifications</label>
                            <div class="space-y-2">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="notifySMSBookings" name="notify_sms_bookings" value="1">
                                    <label for="notifySMSBookings" class="text-sm text-gray-700">Booking confirmations</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="notifySMSReminders" name="notify_sms_reminders" value="1">
                                    <label for="notifySMSReminders" class="text-sm text-gray-700">Appointment reminders</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="notifySMSUpdates" name="notify_sms_updates" value="1">
                                    <label for="notifySMSUpdates" class="text-sm text-gray-700">Service updates</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <h2 class="section-heading">Business Hours</h2>
                    <div class="space-y-4" id="businessHoursContainer">
                        <!-- Business hours will be populated by JavaScript -->
                    </div>
                </div>

                <div class="settings-section">
                    <h2 class="section-heading">Holiday Schedule</h2>
                    <div class="space-y-4" id="holidaysContainer">
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div>
                                <p class="font-medium">New Year's Day</p>
                                <p class="text-sm text-gray-500">January 1</p>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="holidayNewYear" name="holidays[]" value="new_year" checked>
                                <label for="holidayNewYear" class="text-sm text-gray-700">Observe</label>
                            </div>
                        </div>
                        <!-- More holidays will be populated -->
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
                'settings' => $basePath . '/api/settings.php',
            ],
        ], JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="js/api-client.js" defer></script>
    <script src="js/settings-manager.js" defer></script>
</body>
</html>