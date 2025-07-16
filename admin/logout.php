<?php
/**
 * PhPstrap Admin Logout - Clean Production Version
 * Secure session termination without debug output
 */

// Start session
session_start();

// Get info before destroying (for logging)
$was_logged_in = isset($_SESSION['admin_id']) || isset($_SESSION['admin_name']);
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$logout_reason = $_GET['reason'] ?? 'manual';

// Include required files for cleanup (silently)
try {
    if (file_exists('../config/database.php')) {
        require_once '../config/database.php';
    }
    if (file_exists('../config/app.php')) {
        require_once '../config/app.php';
    }
} catch (Exception $e) {
    // Continue silently even if includes fail
}

// Log the logout activity (if functions are available)
try {
    if (function_exists('logAdminActivity') && isset($_SESSION['admin_id'])) {
        logAdminActivity('logout', [
            'reason' => $logout_reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
} catch (Exception $e) {
    // Continue silently
}

// Clear database session tokens (if possible)
try {
    if (function_exists('getDbConnection') && isset($_SESSION['admin_id'])) {
        $pdo = getDbConnection();
        $admin_id = $_SESSION['admin_id'];
        $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ? AND token_type IN ('auth', 'remember')");
        $stmt->execute([$admin_id]);
    }
} catch (Exception $e) {
    // Continue silently
}

// Clear PHP session
$_SESSION = array();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Clear BootPHP session cookies
$bootphp_cookies = ['bootphp_session', 'BOOTPHP_SESSION', 'bootphp_admin_session'];
foreach ($bootphp_cookies as $cookie_name) {
    if (isset($_COOKIE[$cookie_name])) {
        setcookie($cookie_name, '', time() - 3600, '/');
        setcookie($cookie_name, '', time() - 3600, '/admin/');
        setcookie($cookie_name, '', time() - 3600, '/', '');
    }
}

// Clear all possible admin/remember cookies
$all_cookies = [
    'remember_admin_token', 'admin_remember_token', 'remember_me', 
    'admin_remember', 'admin_auth_token', 'bootphp_remember',
    'admin_session', 'user_session', 'bootphp_admin', 'bootphp_user'
];

foreach ($all_cookies as $cookie_name) {
    if (isset($_COOKIE[$cookie_name])) {
        setcookie($cookie_name, '', time() - 3600, '/');
        setcookie($cookie_name, '', time() - 3600, '/admin/');
    }
}

// Start fresh session for logout message
session_start();
session_regenerate_id(true);

// Set logout message based on reason
switch ($logout_reason) {
    case 'timeout':
        $message = "Your session has expired for security reasons. Please log in again.";
        break;
    case 'security':
        $message = "You have been logged out due to security concerns. Please log in again.";
        break;
    case 'inactive':
        $message = "You have been logged out due to inactivity. Please log in again.";
        break;
    case 'force':
        $message = "You have been logged out by an administrator.";
        break;
    default:
        $message = $was_logged_in ? "You have been successfully logged out." : "You are not currently logged in.";
}

$_SESSION['logout_message'] = $message;
$_SESSION['logout_type'] = $logout_reason;

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle redirect parameter
$login_url = 'login.php';
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $redirect = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
    if (strpos($redirect, '/') === 0 && strpos($redirect, '//') !== 0) {
        $login_url = $redirect;
    }
}

// Redirect to login
header("Location: $login_url");
exit;
?>