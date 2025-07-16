<?php
/**
 * BootPHP Admin Authentication Helper
 * Handles admin authentication and authorization
 */

function requireAdminAuth() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isAdminAuthenticated()) {
        // Redirect to login page
        $login_url = '../admin/login.php';
        
        // Store the current URL for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        header("Location: $login_url");
        exit;
    }
    
    // Check if user is actually an admin
    if (!isUserAdmin()) {
        http_response_code(403);
        die('Access denied. Administrator privileges required.');
    }
}

function isAdminAuthenticated() {
    return isset($_SESSION['admin_id']) || 
           (isset($_SESSION['user_id']) && isUserAdmin());
}

function isUserAdmin() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
        return false;
    }
    
    // If admin_id is set, they're definitely an admin
    if (isset($_SESSION['admin_id'])) {
        return true;
    }
    
    // Check database for admin status
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        return $user && $user['is_admin'];
    } catch (Exception $e) {
        error_log("Error checking admin status: " . $e->getMessage());
        return false;
    }
}

function getCurrentAdminId() {
    if (isset($_SESSION['admin_id'])) {
        return $_SESSION['admin_id'];
    } elseif (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    return null;
}

function getCurrentAdminInfo() {
    $admin_id = getCurrentAdminId();
    if (!$admin_id) {
        return null;
    }
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT id, name, email, is_admin, last_login_at 
            FROM users 
            WHERE id = ? AND is_admin = 1
        ");
        $stmt->execute([$admin_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching admin info: " . $e->getMessage());
        return null;
    }
}

function hasPermission($permission) {
    // Basic permission check - can be extended for more granular permissions
    if (!isAdminAuthenticated()) {
        return false;
    }
    
    // For now, all admins have all permissions
    // You can extend this to check specific permissions from database
    return true;
}

function logAdminActivity($action, $data = []) {
    $admin_id = getCurrentAdminId();
    if (!$admin_id) {
        return false;
    }
    
    try {
        $pdo = getDbConnection();
        
        // Create admin_activity_log table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                data JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_admin_id (admin_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (admin_id, action, data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $admin_id,
            $action,
            json_encode($data),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
    } catch (Exception $e) {
        error_log("Error logging admin activity: " . $e->getMessage());
        return false;
    }
}

function getRecentAdminActivity($limit = 50) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT 
                al.*,
                u.name as admin_name,
                u.email as admin_email
            FROM admin_activity_log al
            LEFT JOIN users u ON al.admin_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching admin activity: " . $e->getMessage());
        return [];
    }
}

// Only declare these functions if they don't already exist (avoid conflicts with app.php)
if (!function_exists('createSecureToken')) {
    function createSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
}

if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = createSecureToken();
        }
        return $_SESSION['csrf_token'];
    }
}

// Only declare these utility functions if they don't exist
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
}

if (!function_exists('validateInput')) {
    function validateInput($input, $type = 'string', $options = []) {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL);
            case 'int':
                $min = $options['min'] ?? null;
                $max = $options['max'] ?? null;
                $flags = [];
                if ($min !== null) $flags['min_range'] = $min;
                if ($max !== null) $flags['max_range'] = $max;
                return filter_var($input, FILTER_VALIDATE_INT, ['options' => $flags]);
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT);
            case 'json':
                json_decode($input);
                return json_last_error() === JSON_ERROR_NONE;
            case 'string':
            default:
                $min_length = $options['min_length'] ?? 0;
                $max_length = $options['max_length'] ?? PHP_INT_MAX;
                $length = strlen($input);
                return $length >= $min_length && $length <= $max_length;
        }
    }
}

if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'just now';
        if ($time < 3600) return floor($time/60) . ' min ago';
        if ($time < 86400) return floor($time/3600) . ' hrs ago';
        if ($time < 2592000) return floor($time/86400) . ' days ago';
        if ($time < 31536000) return floor($time/2592000) . ' months ago';
        return floor($time/31536000) . ' years ago';
    }
}

// Rate limiting functions
if (!function_exists('isRateLimited')) {
    function isRateLimited($action, $limit = 10, $window = 300) {
        $key = "rate_limit_{$action}_" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'start' => time()];
        }
        
        $data = $_SESSION[$key];
        
        // Reset if window expired
        if (time() - $data['start'] > $window) {
            $_SESSION[$key] = ['count' => 1, 'start' => time()];
            return false;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $limit) {
            return true;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return false;
    }
}

// Admin menu helper
function getAdminMenu() {
    return [
        'dashboard' => [
            'title' => 'Dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'url' => 'dashboard.php',
            'permission' => 'view_dashboard'
        ],
        'modules' => [
            'title' => 'Modules',
            'icon' => 'fas fa-puzzle-piece',
            'url' => 'modules.php',
            'permission' => 'manage_modules'
        ],
        'users' => [
            'title' => 'Users',
            'icon' => 'fas fa-users',
            'url' => 'users.php',
            'permission' => 'manage_users'
        ],
        'settings' => [
            'title' => 'Settings',
            'icon' => 'fas fa-cog',
            'url' => 'settings.php',
            'permission' => 'manage_settings'
        ],
        'logs' => [
            'title' => 'Activity Logs',
            'icon' => 'fas fa-list-alt',
            'url' => 'logs.php',
            'permission' => 'view_logs'
        ]
    ];
}

function renderAdminMenu($current_page = '') {
    $menu = getAdminMenu();
    $html = '';
    
    foreach ($menu as $key => $item) {
        if (!hasPermission($item['permission'])) {
            continue;
        }
        
        $active = (strpos($current_page, $key) !== false) ? 'active' : '';
        $html .= "<li class='nav-item'>";
        $html .= "<a class='nav-link text-white $active' href='{$item['url']}'>";
        $html .= "<i class='{$item['icon']} me-2'></i>{$item['title']}";
        $html .= "</a>";
        $html .= "</li>";
    }
    
    return $html;
}
?>