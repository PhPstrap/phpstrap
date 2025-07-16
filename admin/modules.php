<?php
/**
 * PhPstrap Admin Modules Management
 * Module/Plugin management system with auto-discovery
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

// Include module scanner
$scanner_paths = ['includes/scan_modules.php', '../includes/scan_modules.php', 'scan_modules.php'];
$scanner_included = false;
foreach ($scanner_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $scanner_included = true;
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
$current_tab = $_GET['tab'] ?? 'installed';
$scan_results = null;

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
                case 'scan_modules':
                    if ($scanner_included && function_exists('scanAndRegisterModules')) {
                        $scan_results = scanAndRegisterModules();
                        if ($scan_results['success']) {
                            if ($scan_results['registered'] > 0) {
                                $message = "Module scan completed! {$scan_results['registered']} new module(s) registered.";
                                $error = false;
                            } else {
                                $message = "Module scan completed. No new modules found.";
                                $error = false;
                            }
                        } else {
                            $message = $scan_results['message'] ?? 'Module scan failed.';
                            $error = true;
                        }
                    } else {
                        $message = 'Module scanner not available.';
                        $error = true;
                    }
                    break;
                    
                case 'toggle_module':
                    $result = toggleModule($pdo, $_POST['module_id']);
                    $message = $result['message'];
                    $error = !$result['success'];
                    break;
                    
                case 'install_module':
                    $result = installModule($pdo, $_POST);
                    $message = $result['message'];
                    $error = !$result['success'];
                    break;
                    
                case 'uninstall_module':
                    $result = uninstallModule($pdo, $_POST['module_id']);
                    $message = $result['message'];
                    $error = !$result['success'];
                    break;
                    
                case 'update_module':
                    $result = updateModule($pdo, $_POST['module_id']);
                    $message = $result['message'];
                    $error = !$result['success'];
                    break;
                    
                case 'update_settings':
                    $result = updateModuleSettings($pdo, $_POST);
                    $message = $result['message'];
                    $error = !$result['success'];
                    break;
                    
                default:
                    $message = 'Invalid action specified.';
                    $error = true;
            }
            
            // Log the action
            if (function_exists('logAdminActivity') && !$error) {
                logAdminActivity('module_' . $_POST['action'], ['module_id' => $_POST['module_id'] ?? null]);
            }
            
        } catch (Exception $e) {
            $message = 'An error occurred. Please try again.';
            $error = true;
            error_log('Modules management error: ' . $e->getMessage());
        }
    }
}

// Get modules data
$modules = getModules();
$stats = getModuleStats();

/**
 * Helper Functions
 */
function getModules($status = 'all') {
    try {
        $pdo = getDbConnection();
        
        $where = '';
        $params = [];
        
        if ($status !== 'all') {
            $where = 'WHERE status = ?';
            $params[] = $status;
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM modules 
            $where
            ORDER BY priority ASC, name ASC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log('Error fetching modules: ' . $e->getMessage());
        return [];
    }
}

function getModuleStats() {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM modules");
        $total = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM modules WHERE enabled = 1");
        $enabled = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM modules WHERE status = 'active'");
        $active = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM modules WHERE is_core = 1");
        $core = $stmt->fetchColumn();
        
        return [
            'total' => $total,
            'enabled' => $enabled,
            'active' => $active,
            'core' => $core
        ];
    } catch (Exception $e) {
        error_log('Error fetching module stats: ' . $e->getMessage());
        return ['total' => 0, 'enabled' => 0, 'active' => 0, 'core' => 0];
    }
}

function toggleModule($pdo, $module_id) {
    try {
        $module_id = (int)$module_id;
        
        if ($module_id <= 0) {
            return ['success' => false, 'message' => 'Invalid module ID.'];
        }
        
        // Get current module data
        $stmt = $pdo->prepare("SELECT name, enabled, is_core, dependencies FROM modules WHERE id = ?");
        $stmt->execute([$module_id]);
        $module = $stmt->fetch();
        
        if (!$module) {
            return ['success' => false, 'message' => 'Module not found.'];
        }
        
        // Don't allow disabling core modules
        if ($module['is_core'] && $module['enabled']) {
            return ['success' => false, 'message' => 'Core modules cannot be disabled.'];
        }
        
        $new_status = $module['enabled'] ? 0 : 1;
        $new_module_status = $new_status ? 'active' : 'inactive';
        
        // Check dependencies if enabling
        if ($new_status) {
            $deps = json_decode($module['dependencies'], true);
            
            // ========== DEBUGGING CODE - START ==========
            // Simplified debug output to avoid truncation
            error_log("SMTP DEBUG - Module: " . $module['name']);
            error_log("SMTP DEBUG - Dependencies raw: " . var_export($module['dependencies'], true));
            error_log("SMTP DEBUG - Dependencies type: " . gettype($module['dependencies']));
            
            $deps = json_decode($module['dependencies'], true);
            error_log("SMTP DEBUG - After JSON decode: " . var_export($deps, true));
            error_log("SMTP DEBUG - JSON error: " . json_last_error_msg());
            error_log("SMTP DEBUG - Is array: " . (is_array($deps) ? 'YES' : 'NO'));
            error_log("SMTP DEBUG - Dependencies null/empty: deps=" . ($deps ? 'NOT NULL' : 'NULL') . ", empty=" . (empty($deps) ? 'YES' : 'NO'));
            // ========== DEBUGGING CODE - END ==========
            
            if ($deps && is_array($deps)) {
                error_log("SMTP DEBUG - Entering dependency check loop, count: " . count($deps));
                foreach ($deps as $dep) {
                    error_log("SMTP DEBUG - Checking dependency: " . var_export($dep, true) . " (type: " . gettype($dep) . ")");
                    
                    $stmt = $pdo->prepare("SELECT enabled FROM modules WHERE name = ?");
                    $stmt->execute([$dep]);
                    $dep_enabled = $stmt->fetchColumn();
                    
                    error_log("SMTP DEBUG - Dependency '$dep' enabled status: " . ($dep_enabled ? 'YES' : 'NO'));
                    
                    if (!$dep_enabled) {
                        error_log("SMTP DEBUG - DEPENDENCY CHECK FAILED for: " . var_export($dep, true));
                        return ['success' => false, 'message' => "Dependency '$dep' is not enabled."];
                    }
                }
                error_log("SMTP DEBUG - All dependencies checked successfully");
            } else {
                error_log("SMTP DEBUG - Skipping dependency check - deps is empty or not array");
            }
        }
        
        // ========== DEBUG - Module Update Section ==========
        error_log("SMTP DEBUG - About to update module status to: enabled=$new_status, status=$new_module_status");
        // ========== END DEBUG ==========
        
        // Update module status
        $stmt = $pdo->prepare("UPDATE modules SET enabled = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $success = $stmt->execute([$new_status, $new_module_status, $module_id]);
        
        if ($success) {
            $action = $new_status ? 'enabled' : 'disabled';
            return ['success' => true, 'message' => "Module '{$module['name']}' $action successfully."];
        } else {
            return ['success' => false, 'message' => 'Failed to update module status.'];
        }
        
    } catch (Exception $e) {
        error_log('Error toggling module: ' . $e->getMessage());
        error_log('Error stack trace: ' . $e->getTraceAsString());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

function installModule($pdo, $data) {
    try {
        // Validate input
        $name = trim($data['name'] ?? '');
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $version = trim($data['version'] ?? '1.0.0');
        $author = trim($data['author'] ?? '');
        
        if (empty($name) || empty($title)) {
            return ['success' => false, 'message' => 'Module name and title are required.'];
        }
        
        // Check if module already exists
        $stmt = $pdo->prepare("SELECT id FROM modules WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Module already exists.'];
        }
        
        // Install module
        $stmt = $pdo->prepare("
            INSERT INTO modules (name, title, description, version, author, enabled, status, installed_at) 
            VALUES (?, ?, ?, ?, ?, 0, 'inactive', NOW())
        ");
        
        $success = $stmt->execute([$name, $title, $description, $version, $author]);
        
        if ($success) {
            return ['success' => true, 'message' => 'Module installed successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to install module.'];
        }
        
    } catch (Exception $e) {
        error_log('Error installing module: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

function uninstallModule($pdo, $module_id) {
    try {
        $module_id = (int)$module_id;
        
        if ($module_id <= 0) {
            return ['success' => false, 'message' => 'Invalid module ID.'];
        }
        
        // Get module data
        $stmt = $pdo->prepare("SELECT name, is_core FROM modules WHERE id = ?");
        $stmt->execute([$module_id]);
        $module = $stmt->fetch();
        
        if (!$module) {
            return ['success' => false, 'message' => 'Module not found.'];
        }
        
        // Don't allow uninstalling core modules
        if ($module['is_core']) {
            return ['success' => false, 'message' => 'Core modules cannot be uninstalled.'];
        }
        
        // Delete module
        $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
        $success = $stmt->execute([$module_id]);
        
        if ($success) {
            return ['success' => true, 'message' => "Module '{$module['name']}' uninstalled successfully."];
        } else {
            return ['success' => false, 'message' => 'Failed to uninstall module.'];
        }
        
    } catch (Exception $e) {
        error_log('Error uninstalling module: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

function updateModule($pdo, $module_id) {
    try {
        $module_id = (int)$module_id;
        
        if ($module_id <= 0) {
            return ['success' => false, 'message' => 'Invalid module ID.'];
        }
        
        // Update last check time
        $stmt = $pdo->prepare("UPDATE modules SET last_check = NOW() WHERE id = ?");
        $stmt->execute([$module_id]);
        
        // In a real implementation, this would check for updates from update_urls
        return ['success' => true, 'message' => 'Module update check completed.'];
        
    } catch (Exception $e) {
        error_log('Error updating module: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
    }
}

function updateModuleSettings($pdo, $data) {
    try {
        $module_id = (int)($data['module_id'] ?? 0);
        
        if ($module_id <= 0) {
            return ['success' => false, 'message' => 'Invalid module ID.'];
        }
        
        // Get current settings
        $stmt = $pdo->prepare("SELECT settings FROM modules WHERE id = ?");
        $stmt->execute([$module_id]);
        $current_settings = $stmt->fetchColumn();
        
        $settings = json_decode($current_settings, true) ?: [];
        
        // Update settings from form data
        foreach ($data as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $setting_key = substr($key, 8); // Remove 'setting_' prefix
                $settings[$setting_key] = $value;
            }
        }
        
        // Update module settings
        $stmt = $pdo->prepare("UPDATE modules SET settings = ?, updated_at = NOW() WHERE id = ?");
        $success = $stmt->execute([json_encode($settings), $module_id]);
        
        if ($success) {
            return ['success' => true, 'message' => 'Module settings updated successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update module settings.'];
        }
        
    } catch (Exception $e) {
        error_log('Error updating module settings: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred.'];
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
    <title>Modules - PhPstrap Admin</title>
    
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
        
        .modules-card {
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
        
        .module-card {
            background: white;
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .module-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .module-card.enabled {
            border-color: #28a745;
            background: linear-gradient(145deg, #ffffff 0%, #f8fff9 100%);
        }
        
        .module-card.core {
            border-color: #ffc107;
            background: linear-gradient(145deg, #ffffff 0%, #fffdf5 100%);
        }
        
        .module-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .module-info {
            flex: 1;
        }
        
        .module-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #495057;
            margin: 0;
        }
        
        .module-version {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .module-description {
            color: #6c757d;
            margin: 0.5rem 0;
            line-height: 1.5;
        }
        
        .module-meta {
            font-size: 0.875rem;
            color: #adb5bd;
        }
        
        .module-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
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
        
        .tabs-nav {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 2rem;
        }
        
        .tabs-nav .nav-link {
            color: #6c757d;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 0;
            transition: all 0.3s ease;
            background: none;
            transform: none;
        }
        
        .tabs-nav .nav-link:hover,
        .tabs-nav .nav-link.active {
            color: #667eea;
            background: none;
            border-bottom: 2px solid #667eea;
            transform: none;
        }
        
        .scan-results {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #28a745;
            position: relative;
        }
        
        .scan-results.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-color: #f5c6cb;
            border-left-color: #dc3545;
        }
        
        .scan-results .btn-close {
            font-size: 0.75rem;
            opacity: 0.6;
        }
        
        .scan-results .btn-close:hover {
            opacity: 1;
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
            
            .module-header {
                flex-direction: column;
            }
            
            .module-actions {
                justify-content: flex-start;
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
            echo renderAdminMenu('modules'); 
        } else {
            echo renderAdminMenuFallback('modules');
        }
        ?>
    </ul>
</nav>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="admin-header">
            <div>
                <h2 class="mb-0">Modules</h2>
                <small class="text-muted">
                    <i class="fas fa-puzzle-piece me-1"></i>
                    Module Management
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
            
            <!-- Scan Results -->
            <?php if ($scan_results): ?>
                <div class="scan-results <?php echo $scan_results['success'] ? '' : 'error'; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6><i class="fas fa-search me-2"></i>Module Scan Results</h6>
                            <p class="mb-2">
                                <strong>📦 Modules Registered:</strong> <?php echo $scan_results['registered'] ?? 0; ?>
                                <?php if (!empty($scan_results['skipped'])): ?>
                                    | <strong>⏭️ Skipped:</strong> <?php echo count($scan_results['skipped']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <button type="button" class="btn-close" onclick="this.parentElement.parentElement.style.display='none'"></button>
                    </div>
                    
                    <?php if (!empty($scan_results['skipped'])): ?>
                        <div class="mt-2">
                            <small class="text-muted">
                                <strong>Skipped modules:</strong> 
                                <?php echo implode(', ', array_map('htmlspecialchars', $scan_results['skipped'])); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($scan_results['errors'])): ?>
                        <div class="mt-3">
                            <h6>⚠️ Issues Found:</h6>
                            <ul class="mb-0 small">
                                <?php foreach ($scan_results['errors'] as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($scan_results['registered'] > 0): ?>
                        <div class="mt-3">
                            <small class="text-success">
                                <i class="fas fa-check me-1"></i>
                                New modules have been added to the list below. You can now enable and configure them.
                            </small>
                        </div>
                    <?php elseif ($scan_results['registered'] === 0 && !empty($scan_results['skipped']) && empty($scan_results['errors'])): ?>
                        <div class="mt-3">
                            <small class="text-info">
                                <i class="fas fa-info-circle me-1"></i>
                                All found modules are already registered. No action needed.
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Module Statistics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Modules</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['enabled']; ?></div>
                        <div class="stat-label">Enabled</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['active']; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['core']; ?></div>
                        <div class="stat-label">Core Modules</div>
                    </div>
                </div>
            </div>
            
            <!-- Modules Management -->
            <div class="modules-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-puzzle-piece me-2"></i>
                        Module Library
                    </div>
                    <div class="d-flex gap-2">
                        <!-- Scan Modules Button -->
                        <?php if ($scanner_included): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                <input type="hidden" name="action" value="scan_modules">
                                <button type="submit" class="btn btn-outline-light btn-sm" title="Scan for new modules">
                                    <i class="fas fa-search me-2"></i>Scan Modules
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Install Module Button -->
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#installModuleModal">
                            <i class="fas fa-plus me-2"></i>Install Module
                        </button>
                    </div>
                </div>
                
                <div class="p-3">
                    <!-- Tabs Navigation -->
                    <ul class="nav nav-tabs tabs-nav">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_tab === 'installed' ? 'active' : ''; ?>" 
                               href="?tab=installed">
                                <i class="fas fa-list me-2"></i>Installed Modules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_tab === 'available' ? 'active' : ''; ?>" 
                               href="?tab=available">
                                <i class="fas fa-download me-2"></i>Available Modules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_tab === 'marketplace' ? 'active' : ''; ?>" 
                               href="?tab=marketplace">
                                <i class="fas fa-store me-2"></i>Marketplace
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Installed Modules Tab -->
                    <?php if ($current_tab === 'installed'): ?>
                        <div class="row">
                            <?php foreach ($modules as $module): ?>
                                <div class="col-lg-6 col-xl-4">
                                    <div class="module-card <?php echo $module['enabled'] ? 'enabled' : ''; ?> <?php echo $module['is_core'] ? 'core' : ''; ?>">
                                        <div class="module-header">
                                            <div class="module-info">
                                                <h5 class="module-title"><?php echo htmlspecialchars($module['title']); ?></h5>
                                                <div class="module-version">
                                                    v<?php echo htmlspecialchars($module['version']); ?>
                                                    <?php if ($module['is_core']): ?>
                                                        <span class="badge bg-warning ms-2">Core</span>
                                                    <?php endif; ?>
                                                    <?php if ($module['enabled']): ?>
                                                        <span class="badge bg-success ms-1">Enabled</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary ms-1">Disabled</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <p class="module-description">
                                            <?php echo htmlspecialchars($module['description'] ?: 'No description available.'); ?>
                                        </p>
                                        
                                        <div class="module-meta">
                                            <?php if ($module['author']): ?>
                                                <i class="fas fa-user me-1"></i>
                                                By <?php echo htmlspecialchars($module['author']); ?>
                                            <?php endif; ?>
                                            
                                            <?php if ($module['installed_at']): ?>
                                                <br><i class="fas fa-calendar me-1"></i>
                                                Installed <?php echo date('M j, Y', strtotime($module['installed_at'])); ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="module-actions">
                                            <!-- Toggle Enable/Disable -->
                                            <?php if (!$module['is_core'] || !$module['enabled']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="toggle_module">
                                                    <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                                    <button type="submit" class="btn btn-<?php echo $module['enabled'] ? 'warning' : 'success'; ?> btn-sm">
                                                        <i class="fas fa-<?php echo $module['enabled'] ? 'pause' : 'play'; ?> me-1"></i>
                                                        <?php echo $module['enabled'] ? 'Disable' : 'Enable'; ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Settings -->
                                            <?php if ($module['enabled'] && $module['settings']): ?>
                                                <button class="btn btn-outline-primary btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#settingsModal"
                                                        onclick="loadModuleSettings(<?php echo $module['id']; ?>, <?php echo htmlspecialchars(json_encode($module)); ?>)">
                                                    <i class="fas fa-cog me-1"></i>Settings
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Update -->
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                                <input type="hidden" name="action" value="update_module">
                                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                                <button type="submit" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-sync me-1"></i>Update
                                                </button>
                                            </form>
                                            
                                            <!-- Uninstall -->
                                            <?php if (!$module['is_core']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="uninstall_module">
                                                    <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm"
                                                            onclick="return confirm('Are you sure you want to uninstall this module?')">
                                                        <i class="fas fa-trash me-1"></i>Uninstall
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($modules)): ?>
                                <div class="col-12">
                                    <div class="text-center py-5">
                                        <i class="fas fa-puzzle-piece fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No modules installed</h5>
                                        <p class="text-muted">Get started by scanning for modules or installing manually.</p>
                                        <div class="d-flex justify-content-center gap-2">
                                            <?php if ($scanner_included): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="scan_modules">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-search me-2"></i>Scan for Modules
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#installModuleModal">
                                                <i class="fas fa-plus me-2"></i>Install Module
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($current_tab === 'available'): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-download fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Available Modules</h5>
                            <p class="text-muted">Browse and install modules from the PhPstrap repository.</p>
                            <button class="btn btn-primary">
                                <i class="fas fa-sync me-2"></i>Refresh Repository
                            </button>
                        </div>
                        
                    <?php elseif ($current_tab === 'marketplace'): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-store fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Module Marketplace</h5>
                            <p class="text-muted">Discover premium modules and extensions.</p>
                            <button class="btn btn-primary">
                                <i class="fas fa-external-link-alt me-2"></i>Visit Marketplace
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Install Module Modal -->
    <div class="modal fade" id="installModuleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Install New Module</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                        <input type="hidden" name="action" value="install_module">
                        
                        <div class="mb-3">
                            <label class="form-label">Module Name</label>
                            <input type="text" class="form-control" name="name" required>
                            <small class="text-muted">Unique identifier for the module (lowercase, no spaces)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Display Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Version</label>
                                <input type="text" class="form-control" name="version" value="1.0.0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Author</label>
                                <input type="text" class="form-control" name="author">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Install Module</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Module Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Module Settings</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['PhPstrap_csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_settings">
                        <input type="hidden" name="module_id" id="settingsModuleId">
                        
                        <div id="settingsContent">
                            <!-- Settings will be loaded here dynamically -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
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
        
        // Load module settings into modal
        function loadModuleSettings(moduleId, moduleData) {
            document.getElementById('settingsModuleId').value = moduleId;
            
            const settingsContent = document.getElementById('settingsContent');
            const settings = JSON.parse(moduleData.settings || '{}');
            
            let html = '<h6 class="mb-3">Configure ' + moduleData.title + '</h6>';
            
            if (Object.keys(settings).length === 0) {
                html += '<p class="text-muted">This module has no configurable settings.</p>';
            } else {
                for (const [key, value] of Object.entries(settings)) {
                    html += '<div class="mb-3">';
                    html += '<label class="form-label">' + key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</label>';
                    
                    if (typeof value === 'boolean') {
                        html += '<div class="form-check">';
                        html += '<input class="form-check-input" type="checkbox" name="setting_' + key + '" id="setting_' + key + '"' + (value ? ' checked' : '') + '>';
                        html += '<label class="form-check-label" for="setting_' + key + '">Enable this option</label>';
                        html += '</div>';
                    } else {
                        html += '<input type="text" class="form-control" name="setting_' + key + '" value="' + value + '">';
                    }
                    
                    html += '</div>';
                }
            }
            
            settingsContent.innerHTML = html;
        }
    </script>
</body>
</html>