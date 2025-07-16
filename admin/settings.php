<?php
/**
 * PhPstrap Admin Settings
 * System configuration management
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

$message = '';
$error = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['PhPstrap_csrf_token'] ?? '', $_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $error = true;
    } else {
        try {
            $pdo = getDbConnection();
            
            switch ($_POST['action']) {
                case 'update_general':
                    updateGeneralSettings($pdo, $_POST);
                    $message = 'General settings updated successfully.';
                    break;
                    
                case 'update_security':
                    updateSecuritySettings($pdo, $_POST);
                    $message = 'Security settings updated successfully.';
                    break;
                    
                case 'update_email':
                    updateEmailSettings($pdo, $_POST);
                    $message = 'Email settings updated successfully.';
                    break;
                    
                case 'update_users':
                    updateUserSettings($pdo, $_POST);
                    $message = 'User settings updated successfully.';
                    break;
                    
                default:
                    $message = 'Invalid action specified.';
                    $error = true;
            }
            
            // Log the settings update
            if (function_exists('logAdminActivity') && !$error) {
                logAdminActivity('settings_update', ['category' => $_POST['action']]);
            }
            
        } catch (Exception $e) {
            $message = 'An error occurred while updating settings. Please try again.';
            $error = true;
            error_log('Settings update error: ' . $e->getMessage());
        }
    }
}

// Get current settings
$settings = getCurrentSettings();

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

function updateSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("
        INSERT INTO settings (`key`, value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    return $stmt->execute([$key, $value]);
}

function updateGeneralSettings($pdo, $data) {
    $settings = [
        'site_name' => trim($data['site_name'] ?? ''),
        'site_description' => trim($data['site_description'] ?? ''),
        'site_url' => trim($data['site_url'] ?? ''),
        'admin_email' => trim($data['admin_email'] ?? ''),
        'timezone' => $data['timezone'] ?? 'UTC',
        'date_format' => $data['date_format'] ?? 'Y-m-d',
        'time_format' => $data['time_format'] ?? 'H:i:s',
        'maintenance_mode' => isset($data['maintenance_mode']) ? '1' : '0'
    ];
    
    foreach ($settings as $key => $value) {
        updateSetting($pdo, $key, $value);
    }
}

function updateSecuritySettings($pdo, $data) {
    $settings = [
        'password_min_length' => (int)($data['password_min_length'] ?? 8),
        'password_require_uppercase' => isset($data['password_require_uppercase']) ? '1' : '0',
        'password_require_lowercase' => isset($data['password_require_lowercase']) ? '1' : '0',
        'password_require_numbers' => isset($data['password_require_numbers']) ? '1' : '0',
        'password_require_symbols' => isset($data['password_require_symbols']) ? '1' : '0',
        'session_timeout' => (int)($data['session_timeout'] ?? 3600),
        'max_login_attempts' => (int)($data['max_login_attempts'] ?? 5),
        'lockout_duration' => (int)($data['lockout_duration'] ?? 900),
        'force_https' => isset($data['force_https']) ? '1' : '0'
    ];
    
    foreach ($settings as $key => $value) {
        updateSetting($pdo, $key, $value);
    }
}

function updateEmailSettings($pdo, $data) {
    $settings = [
        'mail_driver' => $data['mail_driver'] ?? 'php',
        'mail_from_address' => trim($data['mail_from_address'] ?? ''),
        'mail_from_name' => trim($data['mail_from_name'] ?? ''),
        'mail_reply_to' => trim($data['mail_reply_to'] ?? ''),
        'email_templates_enabled' => isset($data['email_templates_enabled']) ? '1' : '0'
    ];
    
    foreach ($settings as $key => $value) {
        updateSetting($pdo, $key, $value);
    }
}

function updateUserSettings($pdo, $data) {
    $settings = [
        'registration_enabled' => isset($data['registration_enabled']) ? '1' : '0',
        'email_verification_required' => isset($data['email_verification_required']) ? '1' : '0',
        'admin_approval_required' => isset($data['admin_approval_required']) ? '1' : '0',
        'auto_approve_users' => isset($data['auto_approve_users']) ? '1' : '0',
        'allow_username_change' => isset($data['allow_username_change']) ? '1' : '0',
        'allow_email_change' => isset($data['allow_email_change']) ? '1' : '0',
        'delete_account_enabled' => isset($data['delete_account_enabled']) ? '1' : '0'
    ];
    
    foreach ($settings as $key => $value) {
        updateSetting($pdo, $key, $value);
    }
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
    <title>Settings - <?php echo $settings['site_name'] ?? 'PhPstrap Admin'; ?></title>
    
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
        
        .settings-card {
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
        
        .settings-nav {
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
            min-height: 500px;
        }
        
        .settings-nav .nav-link {
            color: #495057;
            padding: 1rem 1.5rem;
            border-radius: 0;
            border-bottom: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .settings-nav .nav-link:hover,
        .settings-nav .nav-link.active {
            background: var(--primary-gradient);
            color: white;
            transform: none;
        }
        
        .settings-content {
            padding: 2rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .form-check-input {
            border-radius: 5px;
            border: 2px solid #e9ecef;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
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
            echo renderAdminMenu('settings'); 
        } else {
            echo renderAdminMenuFallback('settings');
        }
        ?>
    </ul>
</nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="admin-header">
            <div>
                <h2 class="mb-0">Settings</h2>
                <small class="text-muted">
                    <i class="fas fa-cog me-1"></i>
                    System Configuration
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
            
            <!-- Settings Card -->
            <div class="settings-card">
                <div class="card-header-custom">
                    <i class="fas fa-sliders-h me-2"></i>
                    System Settings
                </div>
                
                <div class="row g-0">
                    <!-- Settings Navigation -->
                    <div class="col-md-3">
                        <div class="settings-nav">
                            <div class="nav flex-column" id="settings-nav" role="tablist">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="pill" data-bs-target="#general" type="button" role="tab">
                                    <i class="fas fa-globe me-2"></i>General
                                </button>
                                <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-2"></i>Security
                                </button>
                                <button class="nav-link" id="email-tab" data-bs-toggle="pill" data-bs-target="#email" type="button" role="tab">
                                    <i class="fas fa-envelope me-2"></i>Email
                                </button>
                                <button class="nav-link" id="users-tab" data-bs-toggle="pill" data-bs-target="#users" type="button" role="tab">
                                    <i class="fas fa-users me-2"></i>Users
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Settings Content -->
                    <div class="col-md-9">
                        <div class="settings-content">
                            <div class="tab-content" id="settings-content">
                                
                                <!-- General Settings -->
                                <div class="tab-pane fade show active" id="general" role="tabpanel">
                                    <h4 class="mb-4">General Settings</h4>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                        <input type="hidden" name="action" value="update_general">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Site Name</label>
                                                <input type="text" class="form-control" name="site_name" 
                                                       value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Admin Email</label>
                                                <input type="email" class="form-control" name="admin_email" 
                                                       value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Site Description</label>
                                            <textarea class="form-control" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Site URL</label>
                                            <input type="url" class="form-control" name="site_url" 
                                                   value="<?php echo htmlspecialchars($settings['site_url'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Timezone</label>
                                                <select class="form-select" name="timezone">
                                                    <option value="UTC" <?php echo ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                                    <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                                    <option value="America/Chicago" <?php echo ($settings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                                    <option value="America/Denver" <?php echo ($settings['timezone'] ?? '') === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                                    <option value="America/Los_Angeles" <?php echo ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                                    <option value="Europe/London" <?php echo ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>London</option>
                                                    <option value="Europe/Paris" <?php echo ($settings['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : ''; ?>>Paris</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Date Format</label>
                                                <select class="form-select" name="date_format">
                                                    <option value="Y-m-d" <?php echo ($settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                                    <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                                    <option value="d/m/Y" <?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Time Format</label>
                                                <select class="form-select" name="time_format">
                                                    <option value="H:i:s" <?php echo ($settings['time_format'] ?? '') === 'H:i:s' ? 'selected' : ''; ?>>24 Hour</option>
                                                    <option value="g:i A" <?php echo ($settings['time_format'] ?? '') === 'g:i A' ? 'selected' : ''; ?>>12 Hour</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode"
                                                       <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="maintenance_mode">
                                                    <strong>Maintenance Mode</strong> - Temporarily disable public access
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save General Settings
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- Security Settings -->
                                <div class="tab-pane fade" id="security" role="tabpanel">
                                    <h4 class="mb-4">Security Settings</h4>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                        <input type="hidden" name="action" value="update_security">
                                        
                                        <h5 class="mb-3">Password Requirements</h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Minimum Password Length</label>
                                                <input type="number" class="form-control" name="password_min_length" min="6" max="50"
                                                       value="<?php echo htmlspecialchars($settings['password_min_length'] ?? '8'); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="password_require_uppercase" id="req_upper"
                                                           <?php echo ($settings['password_require_uppercase'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="req_upper">Require uppercase letters</label>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="password_require_lowercase" id="req_lower"
                                                           <?php echo ($settings['password_require_lowercase'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="req_lower">Require lowercase letters</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="password_require_numbers" id="req_numbers"
                                                           <?php echo ($settings['password_require_numbers'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="req_numbers">Require numbers</label>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="password_require_symbols" id="req_symbols"
                                                           <?php echo ($settings['password_require_symbols'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="req_symbols">Require special characters</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <h5 class="mb-3">Session & Login Security</h5>
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Session Timeout (seconds)</label>
                                                <input type="number" class="form-control" name="session_timeout" min="300"
                                                       value="<?php echo htmlspecialchars($settings['session_timeout'] ?? '3600'); ?>">
                                                <small class="text-muted">0 = no timeout</small>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Max Login Attempts</label>
                                                <input type="number" class="form-control" name="max_login_attempts" min="3" max="20"
                                                       value="<?php echo htmlspecialchars($settings['max_login_attempts'] ?? '5'); ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Lockout Duration (seconds)</label>
                                                <input type="number" class="form-control" name="lockout_duration" min="300"
                                                       value="<?php echo htmlspecialchars($settings['lockout_duration'] ?? '900'); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="force_https" id="force_https"
                                                       <?php echo ($settings['force_https'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="force_https">
                                                    <strong>Force HTTPS</strong> - Redirect all HTTP requests to HTTPS
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Security Settings
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- Email Settings -->
                                <div class="tab-pane fade" id="email" role="tabpanel">
                                    <h4 class="mb-4">Email Settings</h4>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                        <input type="hidden" name="action" value="update_email">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Mail Driver</label>
                                                <select class="form-select" name="mail_driver">
                                                    <option value="php" <?php echo ($settings['mail_driver'] ?? 'php') === 'php' ? 'selected' : ''; ?>>PHP Mail</option>
                                                    <option value="smtp" <?php echo ($settings['mail_driver'] ?? '') === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                                                    <option value="sendmail" <?php echo ($settings['mail_driver'] ?? '') === 'sendmail' ? 'selected' : ''; ?>>Sendmail</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">From Email Address</label>
                                                <input type="email" class="form-control" name="mail_from_address" 
                                                       value="<?php echo htmlspecialchars($settings['mail_from_address'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">From Name</label>
                                                <input type="text" class="form-control" name="mail_from_name" 
                                                       value="<?php echo htmlspecialchars($settings['mail_from_name'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Reply-To Email</label>
                                            <input type="email" class="form-control" name="mail_reply_to" 
                                                   value="<?php echo htmlspecialchars($settings['mail_reply_to'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="email_templates_enabled" id="email_templates"
                                                       <?php echo ($settings['email_templates_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="email_templates">
                                                    <strong>Enable Email Templates</strong> - Use HTML templates for emails
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Email Settings
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- User Settings -->
                                <div class="tab-pane fade" id="users" role="tabpanel">
                                    <h4 class="mb-4">User Settings</h4>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                        <input type="hidden" name="action" value="update_users">
                                        
                                        <h5 class="mb-3">Registration Settings</h5>
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="registration_enabled" id="reg_enabled"
                                                           <?php echo ($settings['registration_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="reg_enabled">Enable user registration</label>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="email_verification_required" id="email_verify"
                                                           <?php echo ($settings['email_verification_required'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="email_verify">Require email verification</label>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="admin_approval_required" id="admin_approve"
                                                           <?php echo ($settings['admin_approval_required'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="admin_approve">Require admin approval</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="auto_approve_users" id="auto_approve"
                                                           <?php echo ($settings['auto_approve_users'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="auto_approve">Auto-approve new users</label>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="allow_username_change" id="username_change"
                                                           <?php echo ($settings['allow_username_change'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="username_change">Allow username changes</label>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="allow_email_change" id="email_change"
                                                           <?php echo ($settings['allow_email_change'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="email_change">Allow email changes</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <h5 class="mb-3">Account Management</h5>
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="delete_account_enabled" id="delete_account"
                                                       <?php echo ($settings['delete_account_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="delete_account">
                                                    <strong>Allow account deletion</strong> - Users can delete their own accounts
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save User Settings
                                        </button>
                                    </form>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
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
    </script>
</body>
</html>