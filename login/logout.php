<?php
// Include existing PhPstrap configuration
require_once '../config/app.php';
require_once '../config/functions.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
    try {
        // Get user IP and user agent
        if (function_exists('getUserIP')) {
            $user_ip = getUserIP();
        } else {
            $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Log the logout event in login_logs table
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (email, ip_address, user_agent, success, reason, created_at) 
            VALUES (?, ?, ?, 1, 'logout', NOW())
        ");
        $stmt->execute([$_SESSION['email'], $user_ip, $user_agent]);
        
        // Also use logActivity function if available
        if (function_exists('logActivity')) {
            logActivity($_SESSION['email'], $user_ip, $user_agent, true, 'logout');
        }
        
    } catch (PDOException $e) {
        // Log error but don't prevent logout
        if (function_exists('logError')) {
            logError("Logout logging error: " . $e->getMessage());
        } else {
            error_log("Logout logging error: " . $e->getMessage());
        }
    }
}

// Store user name for goodbye message
$user_name = $_SESSION['name'] ?? 'User';

// Clear all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start a new session for the success message
session_start();
$_SESSION['logout_success'] = "Goodbye " . htmlspecialchars($user_name) . "! You have been successfully logged out.";
session_write_close();

// Redirect to login page
if (function_exists('redirect')) {
    redirect('./');
} else {
    header('Location: ./');
    exit();
}
?>