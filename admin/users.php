<?php
/**
 * PhPstrap Admin Users Management
 * User administration with login-as-user capability
 */

// Disable error display for production
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include required files
require_once '../config/database.php';
require_once '../config/app.php';
require_once '../config/functions.php'; // Add this line for our helper functions

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
    header('Location: /admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get admin info
$admin = array(
    'id' => $_SESSION['admin_id'] ?? 1,
    'name' => $_SESSION['admin_name'] ?? 'Administrator',
    'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
);

$message = '';
$error = false;
$action = $_GET['action'] ?? 'list';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['PhPstrap_csrf_token'] ?? '', $_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $error = true;
    } else {
        try {
            $pdo = getDbConnection();
            
            switch ($_POST['action']) {
                case 'create_user':
                    $result = createUser($pdo, $_POST);
                    $message = $result['message'];
                    $error = !$result['success'];
                    break;
                    
                case 'update_user':
                    $result = updateUser($pdo, $_POST);
                    $message = $result['message'];
                    $error = !$result['success'];
                    break;
                    
                case 'delete_user':
                    $result = deleteUser($pdo, $_POST['user_id']);
                    $message = $result['message'];
                    $error = !$result['success'];
                    break;
                    
                case 'toggle_status':
                    $result = toggleUserStatus($pdo, $_POST['user_id']);
                    $message = $result['message'];
                    $error = !$result['success'];
                    break;
                    
                default:
                    $message = 'Invalid action specified.';
                    $error = true;
            }
            
            // Log the action
            if (function_exists('logAdminActivity') && !$error) {
                logAdminActivity($_POST['action'], ['user_id' => $_POST['user_id'] ?? null]);
            }
            
        } catch (Exception $e) {
            $message = 'An error occurred. Please try again.';
            $error = true;
            error_log('Users management error: ' . $e->getMessage());
        }
    }
}

// Handle GET actions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'login_as_user':
            $result = loginAsUser($_GET['user_id'] ?? 0);
            if ($result['success']) {
                // Force session save and redirect
                session_write_close();
                session_start();
                
                // Use absolute path for redirect to avoid any issues
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $redirect_url = $protocol . '://' . $host . '/dashboard/';
                
                header('Location: ' . $redirect_url);
                exit;
            } else {
                $message = $result['message'];
                $error = true;
            }
            break;
    }
}

// Get users data
$users = getUsers();
$stats = getUserStats();

/**
 * Helper Functions
 */
function getUsers($search = '', $page = 1, $limit = 20) {
    try {
        $pdo = getDbConnection();
        $offset = ($page - 1) * $limit;
        
        $where = '';
        $params = [];
        
        if (!empty($search)) {
            $where = "WHERE name LIKE ? OR email LIKE ?";
            $params = ["%$search%", "%$search%"];
        }
        
        $stmt = $pdo->prepare("
            SELECT id, name, email, is_admin, is_active, membership_status, 
                   last_login_at, created_at, login_attempts, credits, api_token,
                   verified, stripe_customer_id, affiliate_id
            FROM users 
            $where
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error fetching users: ' . $e->getMessage());
        return [];
    }
}

function getUserStats() {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $total = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
        $active = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1");
        $admins = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login_at >= CURDATE()");
        $today = $stmt->fetchColumn();
        
        return [
            'total' => $total,
            'active' => $active,
            'admins' => $admins,
            'today' => $today
        ];
    } catch (Exception $e) {
        error_log('Error fetching user stats: ' . $e->getMessage());
        return ['total' => 0, 'active' => 0, 'admins' => 0, 'today' => 0];
    }
}

function createUser($pdo, $data) {
    try {
        // Validate input
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $is_admin = isset($data['is_admin']) ? 1 : 0;
        $is_active = isset($data['is_active']) ? 1 : 0;
        
        if (empty($name) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Name, email, and password are required.'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Email address already exists.'];
        }
        
        // Generate affiliate ID
        $affiliate_id = strtoupper(substr(md5($email . time()), 0, 8));
        
        // Create user
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, is_admin, is_active, affiliate_id, verified, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $success = $stmt->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $is_admin,
            $is_active,
            $affiliate_id
        ]);
        
        if ($success) {
            return ['success' => true, 'message' => 'User created successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to create user.'];
        }
        
    } catch (Exception $e) {
        error_log('Error creating user: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

function updateUser($pdo, $data) {
    try {
        $user_id = (int)($data['user_id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $is_admin = isset($data['is_admin']) ? 1 : 0;
        $is_active = isset($data['is_active']) ? 1 : 0;
        
        if ($user_id <= 0 || empty($name) || empty($email)) {
            return ['success' => false, 'message' => 'Invalid user data provided.'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }
        
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Email address already exists.'];
        }
        
        // Update user
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, is_admin = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $success = $stmt->execute([$name, $email, $is_admin, $is_active, $user_id]);
        
        // Update password if provided
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters long.'];
            }
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($data['password'], PASSWORD_DEFAULT), $user_id]);
        }
        
        if ($success) {
            return ['success' => true, 'message' => 'User updated successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update user.'];
        }
        
    } catch (Exception $e) {
        error_log('Error updating user: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

function deleteUser($pdo, $user_id) {
    try {
        $user_id = (int)$user_id;
        
        if ($user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID.'];
        }
        
        // Don't allow deleting the current admin
        if ($user_id == ($_SESSION['admin_id'] ?? 0)) {
            return ['success' => false, 'message' => 'Cannot delete your own account.'];
        }
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }
        
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $success = $stmt->execute([$user_id]);
        
        if ($success) {
            return ['success' => true, 'message' => 'User deleted successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete user.'];
        }
        
    } catch (Exception $e) {
        error_log('Error deleting user: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

function toggleUserStatus($pdo, $user_id) {
    try {
        $user_id = (int)$user_id;
        
        if ($user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID.'];
        }
        
        // Get current status
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_status = $stmt->fetchColumn();
        
        if ($current_status === false) {
            return ['success' => false, 'message' => 'User not found.'];
        }
        
        // Toggle status
        $new_status = $current_status ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $success = $stmt->execute([$new_status, $user_id]);
        
        if ($success) {
            $status_text = $new_status ? 'activated' : 'deactivated';
            return ['success' => true, 'message' => "User $status_text successfully."];
        } else {
            return ['success' => false, 'message' => 'Failed to update user status.'];
        }
        
    } catch (Exception $e) {
        error_log('Error toggling user status: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

function loginAsUser($user_id) {
    try {
        $user_id = (int)$user_id;
        
        if ($user_id <= 0) {
            return ['success' => false, 'message' => 'Invalid user ID.'];
        }
        
        $pdo = getDbConnection();
        
        // Get full user data
        $stmt = $pdo->prepare("
            SELECT id, name, email, is_active, membership_status, credits, 
                   api_token, verified, is_admin, affiliate_id
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found or inactive.'];
        }
        
        // Store current admin session for returning later
        $_SESSION['impersonating_admin'] = [
            'admin_id' => $_SESSION['admin_id'] ?? null,
            'admin_name' => $_SESSION['admin_name'] ?? null,
            'admin_email' => $_SESSION['admin_email'] ?? null,
            'admin_login_time' => $_SESSION['admin_login_time'] ?? null,
            'admin_logged_in' => $_SESSION['admin_logged_in'] ?? null
        ];
        
        // Clear admin session variables
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_name']);
        unset($_SESSION['admin_email']);
        unset($_SESSION['admin_login_time']);
        unset($_SESSION['admin_logged_in']);
        
        // Set up user session (matching what the login system expects)
        $_SESSION['loggedin'] = true;  // This is key - the isLoggedIn() function checks for this
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['id'] = $user['id']; // Backward compatibility
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['membership_status'] = $user['membership_status'];
        $_SESSION['credits'] = $user['credits'];
        $_SESSION['api_token'] = $user['api_token'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['impersonating'] = true; // Flag to show we're impersonating
        
        // Force session save before redirect
        session_write_close();
        session_start();
        
        // Update last login time for the user
        $user_ip = getUserIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $update_stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?");
        $update_stmt->execute([$user_ip, $user['id']]);
        
        // Log the impersonation activity
        logActivity($user['email'], $user_ip, $user_agent, true, 'admin_impersonation');
        
        // Log admin activity if function exists
        if (function_exists('logAdminActivity')) {
            logAdminActivity('login_as_user', [
                'target_user_id' => $user_id,
                'target_user_name' => $user['name'],
                'target_user_email' => $user['email']
            ]);
        }
        
        return ['success' => true, 'message' => 'Successfully logged in as user.'];
        
    } catch (Exception $e) {
        error_log('Error in login as user: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

// Fallback admin menu function
function renderAdminMenuFallback($current_page = '') {
    $menu_items = [
        'dashboard' => ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'url' => 'index.php'],
        'users' => ['icon' => 'fas fa-users', 'label' => 'Users', 'url' => 'users.php'],
        'settings' => ['icon' => 'fas fa-cog', 'label' => 'Settings', 'url' => 'settings.php'],
        'modules' => ['icon' => 'fas fa-puzzle-piece', 'label' => 'Modules', 'url' => 'modules.php'],
        'logs' => ['icon' => 'fas fa-list-alt', 'label' => 'Logs', 'url' => 'logs.php'],
    ];
    
    $html = '';
    foreach ($menu_items as $key => $item) {
        $active_class = ($current_page === $key) ? 'active' : '';
        $html .= '<li>';
        $html .= '<a href="' . $item['url'] . '" class="nav-link ' . $active_class . '">';
        $html .= '<i class="' . $item['icon'] . '"></i>';
        $html .= $item['label'];
        $html .= '</a>';
        $html .= '</li>';
    }
    
    return $html;
}

// Generate CSRF token
if (!isset($_SESSION['PhPstrap_csrf_token'])) {
    $_SESSION['PhPstrap_csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - PhPstrap Admin</title>
    
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
        
        .users-card {
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
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #495057;
            margin: 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            font-weight: 500;
            margin-top: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            background: #f8f9fa;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
        }
        
        .action-buttons .btn {
            margin: 0 2px;
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
            
            .table-responsive {
                font-size: 0.875rem;
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
        <small class="opacity-75"><?php echo getSetting('site_name', 'PhPstrap'); ?></small>
    </div>
    
    <ul class="sidebar-nav list-unstyled">
        <?php 
        if (function_exists('renderAdminMenu')) {
            echo renderAdminMenu('users'); 
        } else {
            echo renderAdminMenuFallback('users');
        }
        ?>
    </ul>
</nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="admin-header">
            <div>
                <h2 class="mb-0">Users</h2>
                <small class="text-muted">
                    <i class="fas fa-users me-1"></i>
                    User Management
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
            
            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $error ? 'danger' : 'success'; ?> alert-dismissible fade show">
                    <i class="fas fa-<?php echo $error ? 'exclamation-triangle' : 'check-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- User Statistics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['active']; ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['admins']; ?></div>
                        <div class="stat-label">Administrators</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['today']; ?></div>
                        <div class="stat-label">Active Today</div>
                    </div>
                </div>
            </div>
            
            <!-- Users Management -->
            <div class="users-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-users me-2"></i>
                        All Users
                    </div>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-2"></i>Add User
                    </button>
                </div>
                
                <div class="p-3">
                    <!-- Search and Filters -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" class="form-control" placeholder="Search users..." id="searchUsers">
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <button class="btn btn-outline-primary btn-sm me-2">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                            <button class="btn btn-outline-success btn-sm">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                        </div>
                    </div>
                    
                    <!-- Users Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                                    <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php if ($user['is_admin']): ?>
                                                <span class="badge bg-danger">Administrator</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Inactive</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['login_attempts'] >= 5): ?>
                                                <span class="badge bg-danger ms-1">Locked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['last_login_at']): ?>
                                                <small><?php echo date('M j, Y g:i A', strtotime($user['last_login_at'])); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Never</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <!-- Login as User -->
                                                <?php if (!$user['is_admin'] && $user['is_active']): ?>
                                                    <a href="?action=login_as_user&user_id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-primary btn-sm" 
                                                       title="Login as User"
                                                       onclick="return confirm('Login as this user? You will be redirected to their dashboard.')">
                                                        <i class="fas fa-sign-in-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <!-- Edit User -->
                                                <button class="btn btn-outline-primary btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editUserModal"
                                                        onclick="loadUserData(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                        title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <!-- Toggle Status -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" 
                                                            class="btn btn-outline-<?php echo $user['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                                                            title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User">
                                                        <i class="fas fa-<?php echo $user['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <!-- Delete User -->
                                                <?php if ($user['id'] != $admin['id']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" 
                                                                class="btn btn-outline-danger btn-sm"
                                                                title="Delete User"
                                                                onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_admin" id="addIsAdmin">
                                <label class="form-check-label" for="addIsAdmin">
                                    Administrator privileges
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="addIsActive" checked>
                                <label class="form-check-label" for="addIsActive">
                                    Active account
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="editUserId">
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" id="editEmail" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                            <input type="password" class="form-control" name="password" id="editPassword">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_admin" id="editIsAdmin">
                                <label class="form-check-label" for="editIsAdmin">
                                    Administrator privileges
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
                                <label class="form-check-label" for="editIsActive">
                                    Active account
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
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
        
        // Load user data into edit modal
        function loadUserData(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editName').value = user.name;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editIsAdmin').checked = user.is_admin == 1;
            document.getElementById('editIsActive').checked = user.is_active == 1;
            document.getElementById('editPassword').value = '';
        }
        
        // Simple search functionality
        document.getElementById('searchUsers').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('td:first-child .fw-bold').textContent.toLowerCase();
                const email = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>