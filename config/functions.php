<?php
/**
 * BootPHP Additional Functions
 */

// Include database configuration and get connection
require_once __DIR__ . '/database.php';

// Get database connection using existing function
global $pdo;
try {
    $pdo = getDbConnection();
} catch(Exception $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please check configuration.");
    }
}

// Additional constants for compatibility
if (!defined('SITE_URL')) {
    define('SITE_URL', defined('BASE_URL') ? BASE_URL : 'https://bootphp.xyz.am');
}
if (!defined('THEME_COLOR')) {
    define('THEME_COLOR', '#007bff');
}
if (!defined('SECONDARY_COLOR')) {
    define('SECONDARY_COLOR', '#6c757d');
}
if (!defined('SITE_ICON')) {
    define('SITE_ICON', 'fas fa-home');
}

// Missing Helper Functions

/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
    }
}

/**
 * Get current user ID
 */
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

/**
 * Redirect to URL
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: " . $url);
        exit();
    }
}

/**
 * Get setting from database
 */
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '') {
        global $pdo;
        
        static $settings_cache = [];
        
        // Use cache if available
        if (isset($settings_cache[$key])) {
            return $settings_cache[$key];
        }
        
        try {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            $value = $result ? $result['value'] : $default;
            $settings_cache[$key] = $value;
            
            return $value;
        } catch(PDOException $e) {
            if (function_exists('logError')) {
                logError("Error getting setting {$key}: " . $e->getMessage());
            } else {
                error_log("Error getting setting {$key}: " . $e->getMessage());
            }
            return $default;
        }
    }
}

/**
 * Set setting in database
 */
if (!function_exists('setSetting')) {
    function setSetting($key, $value, $type = 'string') {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`, `type`) VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)");
            return $stmt->execute([$key, $value, $type]);
        } catch(PDOException $e) {
            if (function_exists('logError')) {
                logError("Error setting {$key}: " . $e->getMessage());
            } else {
                error_log("Error setting {$key}: " . $e->getMessage());
            }
            return false;
        }
    }
}

/**
 * Get user's IP address
 */
if (!function_exists('getUserIP')) {
    function getUserIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

/**
 * Log user activity
 */
if (!function_exists('logActivity')) {
    function logActivity($email, $ip, $user_agent, $success, $reason = '') {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO login_logs (email, ip_address, user_agent, success, reason, created_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$email, $ip, $user_agent, $success ? 1 : 0, $reason]);
        } catch(PDOException $e) {
            if (function_exists('logError')) {
                logError("Error logging activity: " . $e->getMessage());
            } else {
                error_log("Error logging activity: " . $e->getMessage());
            }
        }
    }
}

/**
 * Sanitize input
 */
if (!function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Generate CSRF token (alternative to existing function)
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (function_exists('generateCsrfToken')) {
            return generateCsrfToken(); // Use existing function if available
        }
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $token_name = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        if (!isset($_SESSION[$token_name])) {
            $_SESSION[$token_name] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[$token_name];
    }
}

/**
 * Verify CSRF token (alternative to existing function)
 */
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        if (function_exists('validateCsrfToken')) {
            return validateCsrfToken($token); // Use existing function if available
        }
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $token_name = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        return isset($_SESSION[$token_name]) && hash_equals($_SESSION[$token_name], $token);
    }
}
?>