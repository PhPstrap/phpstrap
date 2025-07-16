<?php
/**
 * PhPstrap Admin Logs
 * Activity and login log viewer
 */

// Disable error display for production
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include required files
require_once '../config/database.php';
require_once '../config/app.php';

// Include authentication
$auth_paths = ['admin-auth.php', 'includes/admin-auth.php', '../includes/admin-auth.php'];
foreach ($auth_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Initialize app and check authentication
initializeApp();
if (function_exists('requireAdminAuth')) {
    requireAdminAuth();
}

// Check authentication manually if function doesn't exist
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get admin info
$admin = array(
    'id' => $_SESSION['admin_id'] ?? 1,
    'name' => $_SESSION['admin_name'] ?? 'Administrator',
    'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
);

// Log this page access
if (function_exists('logAdminActivity')) {
    try {
        logAdminActivity('logs_access', ['page' => 'logs']);
    } catch (Exception $e) {
        error_log('Error logging activity: ' . $e->getMessage());
    }
}

// Get current settings for site name
$settings = getCurrentSettings();

// Pagination settings
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Filter settings
$log_type = $_GET['type'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$search = trim($_GET['search'] ?? '');

// Get logs based on type
$login_logs = [];
$activity_logs = [];
$total_records = 0;

if ($log_type === 'all' || $log_type === 'login') {
    $login_logs = getLoginLogs($limit, $offset, $date_filter, $search);
}

if ($log_type === 'all' || $log_type === 'activity') {
    $activity_logs = getActivityLogs($limit, $offset, $date_filter, $search);
}

// Get total count for pagination
$total_records = getTotalLogCount($log_type, $date_filter, $search);
$total_pages = ceil($total_records / $limit);

/**
 * Helper Functions
 */
function getCurrentSettings() {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT `key`, value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    } catch (Exception $e) {
        error_log('Error fetching settings: ' . $e->getMessage());
        return [];
    }
}

function getLoginLogs($limit, $offset, $date_filter = '', $search = '') {
    try {
        $pdo = getDbConnection();
        
        $sql = "SELECT * FROM login_logs WHERE 1=1";
        $params = [];
        
        if ($date_filter) {
            $sql .= " AND DATE(created_at) = ?";
            $params[] = $date_filter;
        }
        
        if ($search) {
            $sql .= " AND (email LIKE ? OR ip_address LIKE ? OR reason LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error fetching login logs: ' . $e->getMessage());
        return [];
    }
}

function getActivityLogs($limit, $offset, $date_filter = '', $search = '') {
    try {
        $pdo = getDbConnection();
        
        // Check if admin_activity_log table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_activity_log'");
        if (!$stmt->rowCount()) {
            return [];
        }
        
        $sql = "SELECT al.*, u.name as admin_name, u.email as admin_email 
                FROM admin_activity_log al 
                LEFT JOIN users u ON al.admin_id = u.id 
                WHERE 1=1";
        $params = [];
        
        if ($date_filter) {
            $sql .= " AND DATE(al.created_at) = ?";
            $params[] = $date_filter;
        }
        
        if ($search) {
            $sql .= " AND (al.action LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR al.ip_address LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error fetching activity logs: ' . $e->getMessage());
        return [];
    }
}

function getTotalLogCount($log_type, $date_filter = '', $search = '') {
    try {
        $pdo = getDbConnection();
        $total = 0;
        
        if ($log_type === 'all' || $log_type === 'login') {
            $sql = "SELECT COUNT(*) FROM login_logs WHERE 1=1";
            $params = [];
            
            if ($date_filter) {
                $sql .= " AND DATE(created_at) = ?";
                $params[] = $date_filter;
            }
            
            if ($search) {
                $sql .= " AND (email LIKE ? OR ip_address LIKE ? OR reason LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $total += (int)$stmt->fetchColumn();
        }
        
        if ($log_type === 'all' || $log_type === 'activity') {
            // Check if table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'admin_activity_log'");
            if ($stmt->rowCount()) {
                $sql = "SELECT COUNT(*) FROM admin_activity_log al 
                        LEFT JOIN users u ON al.admin_id = u.id 
                        WHERE 1=1";
                $params = [];
                
                if ($date_filter) {
                    $sql .= " AND DATE(al.created_at) = ?";
                    $params[] = $date_filter;
                }
                
                if ($search) {
                    $sql .= " AND (al.action LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR al.ip_address LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $total += (int)$stmt->fetchColumn();
            }
        }
        
        return $total;
    } catch (Exception $e) {
        error_log('Error counting logs: ' . $e->getMessage());
        return 0;
    }
}

function formatLoginReason($reason) {
    $reasons = [
        'successful_login' => 'Successful Login',
        'successful_admin_login' => 'Admin Login Success',
        'invalid_password' => 'Invalid Password',
        'user_not_found' => 'User Not Found',
        'account_locked' => 'Account Locked',
        'account_disabled' => 'Account Disabled',
        'too_many_attempts' => 'Too Many Attempts',
        'email_not_verified' => 'Email Not Verified'
    ];
    return $reasons[$reason] ?? ucwords(str_replace('_', ' ', $reason));
}

function formatActivityAction($action) {
    $actions = [
        'dashboard_access' => 'accessed the dashboard',
        'logs_access' => 'viewed activity logs',
        'user_create' => 'created a user account',
        'user_update' => 'updated a user account',
        'user_delete' => 'deleted a user account',
        'settings_update' => 'updated system settings',
        'login' => 'logged in',
        'logout' => 'logged out',
        'module_enable' => 'enabled a module',
        'module_disable' => 'disabled a module',
        'password_change' => 'changed password'
    ];
    return $actions[$action] ?? str_replace('_', ' ', $action);
}

function getActionIcon($action) {
    $icons = [
        'dashboard_access' => 'tachometer-alt',
        'logs_access' => 'list-alt',
        'user_create' => 'user-plus',
        'user_update' => 'user-edit',
        'user_delete' => 'user-times',
        'settings_update' => 'cog',
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'module_enable' => 'power-off',
        'module_disable' => 'power-off',
        'password_change' => 'key'
    ];
    return $icons[$action] ?? 'info-circle';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hrs ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    return date('M j, Y g:i A', strtotime($datetime));
}

// Fallback menu function
function renderAdminMenuFallback($current_page = '') {
    $menu = [
        'dashboard' => ['title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => 'dashboard.php'],
        'users' => ['title' => 'Users', 'icon' => 'fas fa-users', 'url' => 'users.php'],
        'settings' => ['title' => 'Settings', 'icon' => 'fas fa-cog', 'url' => 'settings.php'],
        'logs' => ['title' => 'Activity Logs', 'icon' => 'fas fa-list-alt', 'url' => 'logs.php'],
    ];
    
    $html = '';
    foreach ($menu as $key => $item) {
        $active = (strpos($current_page, $key) !== false) ? 'active' : '';
        $html .= "<li class='nav-item'>";
        $html .= "<a class='nav-link $active' href='{$item['url']}'>";
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
    <title>Activity Logs - <?php echo $settings['site_name'] ?? 'PhPstrap Admin'; ?></title>
    
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
        
        .logs-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
            border: none;
        }
        
        .card-header-custom {
            background: var(--primary-gradient);
            color: white;
            padding: 1rem 1.5rem;
            border: none;
            font-weight: 600;
        }
        
        .log-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .log-item:hover {
            background: #f8f9fa;
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .log-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
            font-size: 0.9rem;
        }
        
        .log-icon.success {
            background: #d4edda;
            color: #155724;
        }
        
        .log-icon.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .log-icon.info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .log-icon.admin {
            background: var(--primary-gradient);
            color: white;
        }
        
        .log-content {
            flex: 1;
        }
        
        .log-title {
            margin: 0;
            font-size: 0.95rem;
            color: #495057;
            font-weight: 500;
        }
        
        .log-details {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .log-time {
            font-size: 0.75rem;
            color: #adb5bd;
            white-space: nowrap;
            margin-left: 1rem;
        }
        
        .filters {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }
        
        .page-link {
            color: #667eea;
            border-radius: 8px;
            margin: 0 2px;
            border: 1px solid #dee2e6;
        }
        
        .page-link:hover {
            background: var(--primary-gradient);
            color: white;
            border-color: #667eea;
        }
        
        .page-item.active .page-link {
            background: var(--primary-gradient);
            border-color: #667eea;
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
            
            .log-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .log-time {
                margin-left: 0;
                margin-top: 0.5rem;
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
            <small class="opacity-75"><?php echo $settings['site_name'] ?? 'PhPstrap'; ?></small>
        </div>
        
        <ul class="sidebar-nav list-unstyled">
            <?php 
            if (function_exists('renderAdminMenu')) {
                echo renderAdminMenu('logs'); 
            } else {
                echo renderAdminMenuFallback('logs');
            }
            ?>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="admin-header">
            <div>
                <h2 class="mb-0">Activity Logs</h2>
                <small class="text-muted">
                    <i class="fas fa-list-alt me-1"></i>
                    System activity and login monitoring
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
            
            <!-- Logs Card -->
            <div class="logs-card">
                <div class="card-header-custom">
                    <i class="fas fa-history me-2"></i>
                    System Activity Logs
                    <span class="badge bg-light text-dark ms-2"><?php echo number_format($total_records); ?> records</span>
                </div>
                
                <!-- Filters -->
                <div class="filters">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Log Type</label>
                            <select name="type" class="form-select">
                                <option value="all" <?php echo $log_type === 'all' ? 'selected' : ''; ?>>All Logs</option>
                                <option value="login" <?php echo $log_type === 'login' ? 'selected' : ''; ?>>Login Attempts</option>
                                <option value="activity" <?php echo $log_type === 'activity' ? 'selected' : ''; ?>>Admin Activity</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Log Entries -->
                <div class="log-entries">
                    <?php 
                    // Combine and sort all logs by timestamp
                    $all_logs = [];
                    
                    // Add login logs
                    foreach ($login_logs as $log) {
                        $all_logs[] = [
                            'type' => 'login',
                            'timestamp' => $log['created_at'],
                            'data' => $log
                        ];
                    }
                    
                    // Add activity logs
                    foreach ($activity_logs as $log) {
                        $all_logs[] = [
                            'type' => 'activity',
                            'timestamp' => $log['created_at'],
                            'data' => $log
                        ];
                    }
                    
                    // Sort by timestamp (newest first)
                    usort($all_logs, function($a, $b) {
                        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
                    });
                    
                    if (empty($all_logs)): ?>
                        <div class="log-item">
                            <div class="log-icon info">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="log-content">
                                <p class="log-title">No logs found</p>
                                <div class="log-details">Try adjusting your filters or search criteria.</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_logs as $entry): ?>
                            <?php 
                            $log = $entry['data'];
                            $type = $entry['type'];
                            ?>
                            
                            <?php if ($type === 'login'): ?>
                                <div class="log-item">
                                    <div class="log-icon <?php echo $log['success'] ? 'success' : 'error'; ?>">
                                        <i class="fas fa-<?php echo $log['success'] ? 'check' : 'times'; ?>"></i>
                                    </div>
                                    <div class="log-content">
                                        <p class="log-title">
                                            <strong><?php echo htmlspecialchars($log['email']); ?></strong>
                                            <?php echo formatLoginReason($log['reason']); ?>
                                        </p>
                                        <div class="log-details">
                                            <i class="fas fa-globe me-1"></i>
                                            IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                                            <?php if ($log['user_agent']): ?>
                                                | <i class="fas fa-desktop me-1"></i>
                                                <?php echo htmlspecialchars(substr($log['user_agent'], 0, 60)); ?>...
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="log-time">
                                        <?php echo timeAgo($log['created_at']); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="log-item">
                                    <div class="log-icon admin">
                                        <i class="fas fa-<?php echo getActionIcon($log['action']); ?>"></i>
                                    </div>
                                    <div class="log-content">
                                        <p class="log-title">
                                            <strong><?php echo htmlspecialchars($log['admin_name'] ?? 'System'); ?></strong>
                                            <?php echo formatActivityAction($log['action']); ?>
                                        </p>
                                        <div class="log-details">
                                            <i class="fas fa-globe me-1"></i>
                                            IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                                            <?php if ($log['admin_email']): ?>
                                                | <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($log['admin_email']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="log-time">
                                        <?php echo timeAgo($log['created_at']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="p-3">
                        <nav>
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?>&type=<?php echo urlencode($log_type); ?>&date=<?php echo urlencode($date_filter); ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo urlencode($log_type); ?>&date=<?php echo urlencode($date_filter); ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?>&type=<?php echo urlencode($log_type); ?>&date=<?php echo urlencode($date_filter); ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        
                        <div class="text-center text-muted small">
                            Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                            (<?php echo number_format($total_records); ?> total records)
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }
        
        // Add mobile toggle button
        document.addEventListener('DOMContentLoaded', function() {
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
        
        // Auto-refresh every 30 seconds if on first page with no filters
        if (<?php echo ($page === 1 && empty($date_filter) && empty($search) && $log_type === 'all') ? 'true' : 'false'; ?>) {
            setTimeout(function() {
                window.location.reload();
            }, 30000);
        }
    </script>
</body>
</html>