<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Rate limiting: max 5 login attempts per 15 minutes per IP
    $rateLimitIdentifier = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkRateLimit($rateLimitIdentifier, 5, 900)) {
        $error = 'Too many login attempts. Please try again in 15 minutes.';
        error_log("Login rate limit exceeded for IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    } elseif ($username === '' || $password === '') {
        $error = 'Please provide both username and password.';
    } elseif (!auth_login($username, $password)) {
        $error = 'Invalid credentials or inactive account.';
        // Log failed login attempt
        error_log("Failed login attempt for username: {$username} from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    } else {
        // Successful login - regenerate session ID
        session_regenerate_id(true);
        $_SESSION['created'] = time();
        header('Location: dashboard.php');
        exit;
    }
}

$redirected = isset($_GET['redirect']);
$loggedOut = isset($_GET['logged_out']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login Wasatch Cleaners</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full">
        <div class="bg-white shadow-xl rounded-3xl p-8">
            <div class="flex items-center justify-center mb-6">
                <div class="flex items-center space-x-2">
                    <img src="../logo.png" alt="Wasatch Cleaners logo" class="h-10 w-10 rounded-full bg-white p-1 object-contain" />
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Wasatch Cleaners</h1>
                        <p class="text-sm text-gray-500">Operations Console</p>
                    </div>
                </div>
            </div>

            <?php if ($redirected): ?>
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Please sign in to continue.
                </div>
            <?php endif; ?>

            <?php if ($loggedOut): ?>
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    You have been signed out successfully.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                    <input type="text" id="username" name="username" autocomplete="username" required class="w-full rounded-xl border border-gray-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-transparent" value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required class="w-full rounded-xl border border-gray-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-transparent">
                </div>
                <button type="submit" class="w-full rounded-xl bg-rose-500 py-3 text-white font-semibold shadow-lg hover:bg-rose-600 transition">
                    Sign In
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-gray-400">
                Need help? Contact your system administrator.
            </p>
        </div>
    </div>
</body>
</html>
