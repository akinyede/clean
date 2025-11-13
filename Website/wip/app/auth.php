<?php
/**
 * Admin authentication helpers.
 */
declare(strict_types=1);

function auth_login(string $username, string $password): bool
{
    startSecureSession();
    $conn = db();
    $stmt = $conn->prepare("SELECT id, password_hash, role, is_active, full_name FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        return false;
    }
    
    if (!(bool) $user['is_active']) {
        return false;
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }
    
    $_SESSION['admin_user'] = [
        'id' => (int) $user['id'],
        'username' => $username,
        'role' => $user['role'],
        'full_name' => $user['full_name'],
        'logged_in_at' => time(),
    ];
    
    $update = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
    $update->bind_param('i', $user['id']);
    $update->execute();
    $update->close();
    
    return true;
}

function auth_logout(): void
{
    startSecureSession();
    unset($_SESSION['admin_user']);
}

function auth_user(): ?array
{
    startSecureSession();
    return $_SESSION['admin_user'] ?? null;
}

function require_admin(array $roles = ['admin', 'manager']): void
{
    $user = auth_user();
    
    if (!$user || !in_array($user['role'], $roles, true)) {
        $scriptPath = $_SERVER['SCRIPT_NAME']; // e.g., /booking/admin/dashboard.php
        $adminDir = dirname($scriptPath);       // e.g., /booking/admin
        
        http_response_code(302);
        header('Location: ' . $adminDir . '/index.php?redirect=1');
        exit;
    }
}

function ensure_api_authenticated(array $roles = ['admin', 'manager']): ?array
{
    $user = auth_user();
    
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    return $user;
}