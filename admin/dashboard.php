<?php
/**
 * PhPstrap Admin Dashboard - Robust Version with Error Handling
 * Handles missing dependencies and provides detailed error reporting
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Initialize variables with defaults
$admin = null;
$system_info = array();
$recent_logs = array();
$system_alerts = array();

// Helper function to safely include files
function safeInclude($file, $required = true) {
    if (file_exists($file)) {
        try {
            require_once $file;
            return true;
        } catch (Exception $e) {
            error_log("Error including $file: " . $e->getMessage());
            return false;
        } catch (ParseError $e) {
            error_log("Parse error in $file: " . $e->getMessage());
            return false;
        }
    } else {
        if ($required) {
            error_log("Required file missing: $file");
        }
        return false;
    }
}

// Try to include required files
$config_loaded = safeInclude('../config/database.php');
$app_loaded = safeInclude('../config/app.php');

// Try different possible locations for admin-auth.php
$auth_loaded = false;
$auth_paths = [
    'admin-auth.php',           // Same directory
    'includes/admin-auth.php',  // In includes folder
    '../includes/admin-auth.php', // Parent includes folder
    './admin-auth.php'          // Current directory explicit
];

foreach ($auth_paths as $path) {
    if (file_exists($path)) {
        $auth_loaded = safeInclude($path, false);
        if ($auth_loaded) break;
    }
}

if (!$auth_loaded) {
    error_log("admin-auth.php not found in any of these locations: " . implode(', ', $auth_paths));
}

// Check if essential constants are defined
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'PhPstrap Admin');
}

if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}

// Try to initialize app first (this will start session with proper config)
if (function_exists('initializeApp')) {
    try {
        initializeApp();
    } catch (Exception $e) {
        error_log("App initialization error: " . $e->getMessage());
        // Fallback: start session manually if initialization failed
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        } catch (Exception $e2) {
            error_log("Session fallback error: " . $e2->getMessage());
        }
    }
} else {
    // If initializeApp doesn't exist, start session manually
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    } catch (Exception $e) {
        error_log("Session error: " . $e->getMessage());
    }
}

// STRICT AUTHENTICATION CHECK - Must be authenticated to proceed
$is_authenticated = false;
$redirect_needed = false;
$critical_errors = [];  // Only for actual critical errors

// Check 1: Basic session variables
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    $is_authenticated = true;
} elseif (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    $is_authenticated = true;
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    $is_authenticated = true;
}

// Check 2: Use requireAdminAuth if available (additional security)
if ($is_authenticated && function_exists('requireAdminAuth')) {
    try {
        requireAdminAuth();
    } catch (Exception $e) {
        $critical_errors[] = "Authentication validation failed: " . $e->getMessage();
        $is_authenticated = false;
        $redirect_needed = true;
    }
}

// Check 3: Verify admin exists in database if DB is available
if ($is_authenticated && function_exists('getDbConnection')) {
    try {
        $pdo = getDbConnection();
        $admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
        if ($admin_id) {
            $stmt = $pdo->prepare("SELECT id, is_admin FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$admin_id]);
            $user = $stmt->fetch();
            
            if (!$user || !$user['is_admin']) {
                $critical_errors[] = "Admin user not found in database or not admin";
                $is_authenticated = false;
                $redirect_needed = true;
            }
        }
    } catch (Exception $e) {
        // Don't fail auth just for DB issues, but log it silently
        error_log("Dashboard DB verification error: " . $e->getMessage());
    }
}

// FINAL AUTHENTICATION DECISION
if (!$is_authenticated || $redirect_needed) {
    // NOT AUTHENTICATED - Force redirect to login
    $current_url = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
    $login_url = 'login.php';
    
    // If this is an AJAX request, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Authentication required', 'redirect' => $login_url]);
        exit;
    }
    
    // Add redirect parameter
    if (strpos($current_url, 'login.php') === false) {
        $login_url .= '?redirect=' . urlencode($current_url);
    }
    
    // Multiple redirect methods to ensure it works
    header("Location: $login_url");
    echo "<script>window.location.href='$login_url';</script>";
    echo "<meta http-equiv='refresh' content='0;url=$login_url'>";
    
    // Show authentication required page if redirects fail
    echo "<!DOCTYPE html><html><head><title>Authentication Required</title></head><body>";
    echo "<h1>🔒 Authentication Required</h1>";
    echo "<p><strong>You must be logged in to access the admin dashboard.</strong></p>";
    echo "<p><a href='$login_url'>Click here to login</a></p>";
    echo "<script>setTimeout(function(){ window.location.href='$login_url'; }, 2000);</script>";
    echo "</body></html>";
    exit;
}

// Try to get admin info
if (function_exists('getCurrentAdminInfo')) {
    try {
        $admin = getCurrentAdminInfo();
    } catch (Exception $e) {
        error_log("Error getting admin info: " . $e->getMessage());
    }
}

// If no admin info, create a fallback
if (!$admin) {
    $admin = array(
        'id' => $_SESSION['admin_id'] ?? 1,
        'name' => $_SESSION['admin_name'] ?? ($_SESSION['admin_logged_in'] ? 'Administrator' : 'Unknown'),
        'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
    );
}

// Try to log dashboard access
if (function_exists('logAdminActivity')) {
    try {
        logAdminActivity('dashboard_access', ['page' => 'dashboard']);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

// Try to get system info from database
if (function_exists('getDbConnection')) {
    try {
        $pdo = getDbConnection();
        
        // Get user counts
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
        $system_info['user_count'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1 AND is_active = 1");
        $system_info['admin_count'] = $stmt->fetchColumn();
        
        // Get recent logins (today)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_logs WHERE success = 1 AND created_at >= CURDATE()");
        $stmt->execute();
        $system_info['recent_logins'] = $stmt->fetchColumn();
        
        // Get MySQL version
        try {
            $version = $pdo->query("SELECT VERSION()")->fetchColumn();
            $system_info['mysql_version'] = $version;
        } catch (Exception $e) {
            $system_info['mysql_version'] = 'Unknown';
        }
        
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        // Use defaults if database fails
        $system_info['user_count'] = 'N/A';
        $system_info['admin_count'] = 'N/A';
        $system_info['recent_logins'] = 'N/A';
        $system_info['mysql_version'] = 'N/A';
    }
} else {
    $system_info['user_count'] = 'N/A';
    $system_info['admin_count'] = 'N/A';
    $system_info['recent_logins'] = 'N/A';
    $system_info['mysql_version'] = 'N/A';
}

// Set basic system info
$system_info['php_version'] = PHP_VERSION;
$system_info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$system_info['memory_usage'] = memory_get_usage(true);
$system_info['disk_free_space'] = disk_free_space('.') ?: 0;
$system_info['disk_total_space'] = disk_total_space('.') ?: 0;

// Try to get recent activity logs
if (function_exists('getRecentAdminActivity')) {
    try {
        $recent_logs = getRecentAdminActivity(5);
    } catch (Exception $e) {
        error_log("Error getting activity logs: " . $e->getMessage());
        $recent_logs = array();
    }
}

// Helper functions
function formatBytes($size, $precision = 2) {
    if ($size === 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $factor = floor(log($size, 1024));
    $pow = min($factor, count($units) - 1);
    return round($size / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

function getActionIcon($action) {
    $icons = array(
        'dashboard_access' => 'tachometer-alt',
        'user_create' => 'user-plus',
        'user_update' => 'user-edit',
        'user_delete' => 'user-times',
        'settings_update' => 'cog',
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'module_enable' => 'power-off',
        'module_disable' => 'power-off',
        'password_change' => 'key'
    );
    return isset($icons[$action]) ? $icons[$action] : 'info-circle';
}

function formatActivityAction($action) {
    $actions = array(
        'dashboard_access' => 'accessed the dashboard',
        'user_create' => 'created a user account',
        'user_update' => 'updated a user account',
        'user_delete' => 'deleted a user account',
        'settings_update' => 'updated system settings',
        'login' => 'logged in',
        'logout' => 'logged out',
        'module_enable' => 'enabled a module',
        'module_disable' => 'disabled a module',
        'password_change' => 'changed password'
    );
    return isset($actions[$action]) ? $actions[$action] : str_replace('_', ' ', $action);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    return date('M j, Y', strtotime($datetime));
}

// Create admin menu fallback
function renderAdminMenuFallback($current_page = '') {
    $menu = [
        'dashboard' => ['title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => 'dashboard.php'],
        'users' => ['title' => 'Users', 'icon' => 'fas fa-users', 'url' => 'users.php'],
        'settings' => ['title' => 'Settings', 'icon' => 'fas fa-cog', 'url' => 'settings.php'],
        'logout' => ['title' => 'Logout', 'icon' => 'fas fa-sign-out-alt', 'url' => 'logout.php']
    ];
    
    $html = '';
    foreach ($menu as $key => $item) {
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sidebar-width: 250px;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--primary-gradient);
            color: white;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s ease;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            border: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .admin-header {
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 0 0 20px 20px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            border: none;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card {
            position: relative;
            overflow: hidden;
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--primary-gradient);
            opacity: 0.1;
            z-index: 1;
        }
        
        .stat-content {
            position: relative;
            z-index: 2;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #495057;
            margin: 0;
            line-height: 1;
        }
        
        .stat-label {
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
            margin-top: 0.5rem;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-text {
            margin: 0;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .card-header-custom {
            background: var(--primary-gradient);
            color: white;
            padding: 1rem 1.5rem;
            border: none;
            font-weight: 600;
        }
        
        .alert-system {
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4 class="mb-1">
                <i class="fas fa-shield-alt me-2"></i>
                Admin Panel
            </h4>
            <small class="opacity-75"><?php echo SITE_NAME; ?></small>
        </div>
        
        <ul class="sidebar-nav list-unstyled">
            <?php 
            if (function_exists('renderAdminMenu')) {
                echo renderAdminMenu('dashboard'); 
            } else {
                echo renderAdminMenuFallback('dashboard');
            }
            ?>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="admin-header">
            <div>
                <h2 class="mb-0">Dashboard</h2>
                <small class="text-muted">
                    <i class="fas fa-clock me-1"></i>
                    <?php echo date('l, F j, Y g:i A'); ?>
                </small>
            </div>
            <div class="d-flex align-items-center">
                <span class="text-muted me-3">
                    Welcome, <strong><?php echo htmlspecialchars($admin['name']); ?></strong>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </header>
        
        <!-- Content -->
        <div class="container-fluid px-4">
            
            <!-- Critical System Alerts (only show if there are actual critical issues) -->
            <?php if (!empty($critical_errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Critical System Issues:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($critical_errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Row -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="dashboard-card stat-card">
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $system_info['user_count']; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="dashboard-card stat-card">
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $system_info['recent_logins']; ?></div>
                            <div class="stat-label">Active Today</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="dashboard-card stat-card">
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $system_info['admin_count']; ?></div>
                            <div class="stat-label">Administrators</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="dashboard-card stat-card">
                        <div class="stat-content">
                            <div class="stat-number"><?php echo formatBytes($system_info['memory_usage'], 1); ?></div>
                            <div class="stat-label">Memory Usage</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Info and Activity Row -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="dashboard-card">
                        <div class="card-header-custom">
                            <i class="fas fa-info-circle me-2"></i>
                            System Information
                        </div>
                        <div class="p-4">
                            <div class="row">
                                <div class="col-6">
                                    <strong>PHP Version:</strong><br>
                                    <span class="text-muted"><?php echo $system_info['php_version']; ?></span>
                                </div>
                                <div class="col-6">
                                    <strong>Server:</strong><br>
                                    <span class="text-muted"><?php echo explode('/', $system_info['server_software'])[0]; ?></span>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-6">
                                    <strong>Database:</strong><br>
                                    <span class="text-muted">MySQL <?php echo explode('-', $system_info['mysql_version'])[0]; ?></span>
                                </div>
                                <div class="col-6">
                                    <strong>Disk Space:</strong><br>
                                    <span class="text-muted"><?php echo formatBytes($system_info['disk_free_space']); ?> free</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="dashboard-card">
                        <div class="card-header-custom">
                            <i class="fas fa-history me-2"></i>
                            Recent Activity
                        </div>
                        
                        <div class="activity-feed">
                            <?php if (!empty($recent_logs)): ?>
                                <?php foreach ($recent_logs as $log): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-<?php echo getActionIcon($log['action']); ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p class="activity-text">
                                                <strong><?php echo htmlspecialchars($log['admin_name'] ?? 'System'); ?></strong>
                                                <?php echo formatActivityAction($log['action']); ?>
                                            </p>
                                            <div class="activity-time">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo timeAgo($log['created_at']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p class="activity-text text-muted">No recent activity found.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Simple sidebar toggle for mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }
        
        // Add mobile toggle button functionality if needed
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile responsiveness
            if (window.innerWidth <= 768) {
                const header = document.querySelector('.admin-header');
                const toggleBtn = document.createElement('button');
                toggleBtn.className = 'btn btn-link d-md-none p-0 me-3';
                toggleBtn.innerHTML = '<i class="fas fa-bars fa-lg text-dark"></i>';
                toggleBtn.onclick = toggleSidebar;
                header.firstElementChild.insertBefore(toggleBtn, header.firstElementChild.firstChild);
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('show');
            }
        });
    </script>
</body>
</html>