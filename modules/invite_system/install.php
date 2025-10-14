<?php
/**
 * PhPstrap Invite System Module Installer
 * File: /modules/invite_system/install.php
 */

require_once '../../config/app.php';
require_once '../../config/database.php';
require_once 'InviteSystem.php';

// Admin authentication
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: ../../admin/login.php');
    exit;
}

use PhPstrap\Modules\InviteSystem\InviteSystem;

$message = '';
$error = false;
$module = null;

try {
    $pdo = getDbConnection();
    $module = new InviteSystem($pdo);
} catch (Exception $e) {
    $error = true;
    $message = 'Database connection failed: ' . $e->getMessage();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $module) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'install':
            $result = $module->install();
            $message = $result['message'];
            $error = !$result['success'];
            break;
            
        case 'uninstall':
            if (isset($_POST['confirm_uninstall']) && $_POST['confirm_uninstall'] === 'yes') {
                $result = $module->uninstall();
                $message = $result['message'];
                $error = !$result['success'];
            } else {
                $message = 'Please confirm uninstallation by checking the checkbox.';
                $error = true;
            }
            break;
            
        case 'update':
            $from_version = $_POST['from_version'] ?? '0.0.0';
            $result = $module->update($from_version);
            $message = $result['message'];
            $error = !$result['success'];
            break;
    }
}

// Check current status
$is_installed = $module ? $module->isInstalled() : false;
$module_info = $module ? $module->getModuleInfo() : null;

// Helper function
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invite System Module Installer - PhPstrap Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root { --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .container { max-width: 800px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,.1); margin-bottom: 2rem; }
        .card-header-custom { background: var(--primary-gradient); color: white; padding: 1rem 1.5rem; border: none; font-weight: 600; border-radius: 15px 15px 0 0; }
        .btn-primary { background: var(--primary-gradient); border: none; border-radius: 10px; }
        .status-installed { color: #28a745; }
        .status-not-installed { color: #dc3545; }
        .feature-list { list-style: none; padding: 0; }
        .feature-list li { padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0; }
        .feature-list li:last-child { border-bottom: none; }
        .feature-list i { width: 20px; color: #28a745; }
    </style>
</head>
<body>
    <div class="container py-5">
        <!-- Header -->
        <div class="text-center mb-4">
            <h1 class="mb-2">
                <i class="fas fa-envelope-circle-check me-2" style="color: var(--primary-gradient);"></i>
                Invite System Module
            </h1>
            <p class="text-muted">Complete invitation system for user registration management</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $error ? 'danger' : 'success'; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $error ? 'exclamation-triangle' : 'check-circle'; ?> me-2"></i>
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Module Information -->
        <div class="card">
            <div class="card-header-custom">
                <i class="fas fa-info-circle me-2"></i>Module Information
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>
                            <?php echo e($module_info['name'] ?? 'Invite System'); ?>
                            <span class="badge <?php echo $is_installed ? 'bg-success' : 'bg-secondary'; ?> ms-2">
                                <?php echo $is_installed ? 'Installed' : 'Not Installed'; ?>
                            </span>
                        </h5>
                        <p class="text-muted"><?php echo e($module_info['description'] ?? 'Complete invitation system for user registration management'); ?></p>
                        
                        <dl class="row">
                            <dt class="col-sm-4">Version:</dt>
                            <dd class="col-sm-8"><?php echo e($module_info['version'] ?? '1.0.0'); ?></dd>
                            
                            <dt class="col-sm-4">Author:</dt>
                            <dd class="col-sm-8"><?php echo e($module_info['author'] ?? 'PhPstrap'); ?></dd>
                            
                            <dt class="col-sm-4">Status:</dt>
                            <dd class="col-sm-8">
                                <span class="<?php echo $is_installed ? 'status-installed' : 'status-not-installed'; ?>">
                                    <i class="fas fa-<?php echo $is_installed ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                    <?php echo $is_installed ? 'Installed and Ready' : 'Not Installed'; ?>
                                </span>
                            </dd>
                        </dl>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Features Included:</h6>
                        <ul class="feature-list">
                            <li><i class="fas fa-check"></i> Complete invite code system</li>
                            <li><i class="fas fa-check"></i> User quota management</li>
                            <li><i class="fas fa-check"></i> Admin management interface</li>
                            <li><i class="fas fa-check"></i> Usage tracking & analytics</li>
                            <li><i class="fas fa-check"></i> Automatic cleanup procedures</li>
                            <li><i class="fas fa-check"></i> Integration with registration</li>
                            <li><i class="fas fa-check"></i> Email verification bonuses</li>
                            <li><i class="fas fa-check"></i> Referral system support</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Installation Actions -->
        <div class="card">
            <div class="card-header-custom">
                <i class="fas fa-tools me-2"></i>Installation Actions
            </div>
            <div class="card-body">
                <?php if (!$is_installed): ?>
                    <!-- Install Module -->
                    <div class="text-center">
                        <i class="fas fa-download fa-3x text-primary mb-3"></i>
                        <h5>Ready to Install</h5>
                        <p class="text-muted mb-4">
                            This will create all necessary database tables, settings, and configurations for the invite system.
                        </p>
                        
                        <form method="POST" onsubmit="return confirm('Install the Invite System module? This will create new database tables.');">
                            <input type="hidden" name="action" value="install">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-download me-2"></i>Install Module
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Module Installed - Show Options -->
                    <div class="row">
                        <div class="col-md-6 text-center">
                            <i class="fas fa-cog fa-3x text-success mb-3"></i>
                            <h5>Module Installed</h5>
                            <p class="text-muted">The invite system is installed and ready to use.</p>
                            
                            <div class="d-grid gap-2">
                                <a href="../../admin/invites.php" class="btn btn-success">
                                    <i class="fas fa-external-link-alt me-2"></i>Open Invite Manager
                                </a>
                                <a href="../../admin/settings.php" class="btn btn-outline-primary">
                                    <i class="fas fa-sliders-h me-2"></i>Configure Settings
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-md-6 text-center">
                            <i class="fas fa-trash fa-3x text-danger mb-3"></i>
                            <h5>Uninstall Module</h5>
                            <p class="text-muted">Remove all tables and data. This action cannot be undone.</p>
                            
                            <form method="POST" onsubmit="return confirm('Are you sure you want to uninstall? This will delete ALL invite data!');">
                                <input type="hidden" name="action" value="uninstall">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="confirm_uninstall" name="confirm_uninstall" value="yes" required>
                                    <label class="form-check-label" for="confirm_uninstall">
                                        I understand this will delete all data
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash me-2"></i>Uninstall Module
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Database Information -->
        <div class="card">
            <div class="card-header-custom">
                <i class="fas fa-database me-2"></i>Database Structure
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">The module will create the following database components:</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-table me-2"></i>Tables</h6>
                        <ul class="list-unstyled">
                            <li><code>invites</code> - Main invite codes</li>
                            <li><code>invite_usages</code> - Usage tracking</li>
                            <li><code>user_invite_quotas</code> - User quotas</li>
                        </ul>
                        
                        <h6><i class="fas fa-eye me-2"></i>Views</h6>
                        <ul class="list-unstyled">
                            <li><code>invite_stats</code> - Statistics view</li>
                            <li><code>recent_invite_usage</code> - Recent usage</li>
                        </ul>
                    </div>
                    
                    <div class="col-md-6">
                        <h6><i class="fas fa-cogs me-2"></i>Procedures</h6>
                        <ul class="list-unstyled">
                            <li><code>GenerateInviteCode</code> - Code generation</li>
                            <li><code>ValidateInviteCode</code> - Code validation</li>
                            <li><code>CleanupExpiredInvites</code> - Maintenance</li>
                        </ul>
                        
                        <h6><i class="fas fa-sliders-h me-2"></i>Settings</h6>
                        <ul class="list-unstyled">
                            <li>11 new configuration options</li>
                            <li>Integrated with settings panel</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="text-center">
            <a href="../../admin/" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Admin
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>