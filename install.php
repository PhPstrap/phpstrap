<?php
/**
 * PhPstrap Installer - Main Entry Point
 * 
 * This is the main installer file that orchestrates the complete installation process
 * Save as: install.php (in your website root)
 * 
 * Version: 1.0.0
 * Author: PhPstrap Team
 */

// Enable error reporting for installation
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start session for installation data
session_start();

// Check if already installed
if (file_exists('config/database.php') && !isset($_GET['force'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PhPstrap Already Installed</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                            <h3>PhPstrap Already Installed</h3>
                            <p class="text-muted">PhPstrap is already installed on this server.</p>
                            <div class="mt-4">
                                <a href="login/" class="btn btn-primary me-2">Go to Login</a>
                                <a href="?force=1" class="btn btn-outline-danger">Reinstall</a>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">
                                    To reinstall, delete <code>config/database.php</code> or add <code>?force=1</code> to the URL
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Include installer components
$installer_files = [
    'installer/requirements.php',
    'installer/database.php', 
    'installer/config.php',
    'installer/ui.php'
];

// Check if installer files exist
$missing_files = [];
foreach ($installer_files as $file) {
    if (!file_exists($file)) {
        $missing_files[] = $file;
    }
}

// If installer files are missing, show error
if (!empty($missing_files)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PhPstrap Installer - Missing Files</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-danger">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <i class="fas fa-exclamation-triangle text-danger fa-3x"></i>
                                <h3 class="mt-3">Installer Files Missing</h3>
                            </div>
                            
                            <div class="alert alert-danger">
                                <h5>The following installer files are missing:</h5>
                                <ul class="mb-0">
                                    <?php foreach ($missing_files as $file): ?>
                                        <li><code><?php echo htmlspecialchars($file); ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>How to fix this:</h6>
                                <ol class="mb-0">
                                    <li>Create the <code>installer/</code> directory in your website root</li>
                                    <li>Download the missing installer component files</li>
                                    <li>Upload them to the <code>installer/</code> directory</li>
                                    <li>Refresh this page to continue</li>
                                </ol>
                            </div>
                            
                            <div class="text-center">
                                <button onclick="location.reload()" class="btn btn-primary">
                                    <i class="fas fa-refresh me-2"></i>Refresh Page
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Include all installer components
foreach ($installer_files as $file) {
    require_once $file;
}

// Installation steps
$steps = array(
    1 => 'System Requirements',
    2 => 'Database Configuration', 
    3 => 'Admin Account Setup',
    4 => 'Module Selection',
    5 => 'Final Configuration',
    6 => 'Installation Complete'
);

// Get current step
$current_step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$errors = array();
$success = array();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($current_step) {
            case 2:
                $result = handleDatabaseStep($_POST);
                $errors = $result['errors'];
                $success = $result['success'];
                if (empty($errors)) $current_step = 3;
                break;
                
            case 3:
                $result = handleAdminAccountStep($_POST);
                $errors = $result['errors'];
                $success = $result['success'];
                if (empty($errors)) $current_step = 4;
                break;
                
            case 4:
                $result = handleModuleStep($_POST);
                $errors = $result['errors'];
                $success = $result['success'];
                if (empty($errors)) $current_step = 5;
                break;
                
            case 5:
                $result = handleConfigurationStep($_POST);
                $errors = $result['errors'];
                $success = $result['success'];
                if (empty($errors)) $current_step = 6;
                break;
                
            case 6:
                if (isset($_POST['remove_installer'])) {
                    cleanupInstaller();
                    exit;
                }
                break;
        }
    } catch (Exception $e) {
        $errors[] = "Unexpected error: " . $e->getMessage();
        error_log("PhPstrap Installer error: " . $e->getMessage());
    }
}

// Render the installer interface
renderInstallerPage($steps, $current_step, $errors, $success);

// ===== STEP HANDLERS =====

function installDatabaseSchemaWithoutTransaction($pdo) {
    try {
        // Create all tables in correct order (considering foreign keys)
        createUsersTable($pdo);
        createAffiliateClicksTable($pdo);
        createAffiliateSignupsTable($pdo);
        createApiResellersTable($pdo);
        createInvitesTable($pdo);
        createPasswordResetsTable($pdo);
        createTokenPurchasesTable($pdo);
        createUserTokensTable($pdo);
        createWithdrawalsTable($pdo);
        createSettingsTable($pdo);
        createModulesTable($pdo);
        
        // Insert default settings (with duplicate handling)
        insertDefaultSettingsSafe($pdo);
        
        // Add foreign key constraints (after all tables exist) - with error handling
        addForeignKeyConstraintsWithoutTransaction($pdo);
        
    } catch (Exception $e) {
        error_log("Schema installation error: " . $e->getMessage());
        throw new Exception("Database schema installation failed: " . $e->getMessage());
    }
}

// Alternative simpler installation method (fallback)
function installDatabaseSchemaSimple($pdo) {
    // This is a simpler approach that doesn't use transactions at all
    // Use this as a fallback if transaction-based installation fails
    
    try {
        // Create tables one by one
        createUsersTable($pdo);
        createSettingsTable($pdo);
        createModulesTable($pdo);
        createAffiliateClicksTable($pdo);
        createAffiliateSignupsTable($pdo);
        createApiResellersTable($pdo);
        createInvitesTable($pdo);
        createPasswordResetsTable($pdo);
        createTokenPurchasesTable($pdo);
        createUserTokensTable($pdo);
        createWithdrawalsTable($pdo);
        
        // Insert default settings (with duplicate handling)
        insertDefaultSettingsSafe($pdo);
        
        // Skip foreign key constraints for now (can be added later)
        error_log("Database schema installed without foreign key constraints");
        
    } catch (Exception $e) {
        error_log("Simple schema installation error: " . $e->getMessage());
        throw new Exception("Database installation failed: " . $e->getMessage());
    }
}

// Safe version of insertDefaultSettings that handles duplicates
function insertDefaultSettingsSafe($pdo) {
    // Check if settings already exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        error_log("Settings already exist, skipping default settings insertion");
        return; // Settings already exist, skip insertion
    }
    
    // If no settings exist, insert them
    insertDefaultSettings($pdo);
}

// Safe admin user creation that handles existing users
function createAdminUserSafe($pdo, $name, $email, $password) {
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id, api_token FROM users WHERE email = ? OR is_admin = 1");
    $stmt->execute([$email]);
    $existing_admin = $stmt->fetch();
    
    if ($existing_admin) {
        // Update existing admin user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users SET 
                name = ?, 
                password = ?, 
                membership_status = 'premium', 
                verified = 1, 
                verified_at = NOW(), 
                is_admin = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $hashed_password, $existing_admin['id']]);
        
        error_log("Updated existing admin user with ID: " . $existing_admin['id']);
        return $existing_admin['id'];
    } else {
        // Create new admin user
        return createAdminUser($pdo, $name, $email, $password);
    }
}

// Safe site settings update that handles existing settings
function updateSiteSettingsSafe($pdo, $admin_email, $site_name, $admin_user_id) {
    // Get current server info for site URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $site_url = $protocol . '://' . $host . $dir;
    
    // Update core settings with installation data (using INSERT ... ON DUPLICATE KEY UPDATE)
    $updates = array(
        array('admin_email', $admin_email),
        array('site_name', $site_name),
        array('site_url', $site_url),
        array('primary_admin_id', $admin_user_id),
        array('mail_from_address', $admin_email),
        array('mail_from_name', $site_name),
        array('installation_date', date('Y-m-d H:i:s'))
    );
    
    $stmt = $pdo->prepare("
        INSERT INTO settings (`key`, `value`, updated_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE 
        `value` = VALUES(`value`), 
        updated_at = NOW()
    ");
    
    foreach ($updates as $update) {
        $stmt->execute([$update[0], $update[1]]);
    }
}

function addForeignKeyConstraintsWithoutTransaction($pdo) {
    $constraints = array(
        "ALTER TABLE `affiliate_clicks` ADD CONSTRAINT `affiliate_clicks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `affiliate_signups` ADD CONSTRAINT `affiliate_signups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `affiliate_signups` ADD CONSTRAINT `affiliate_signups_ibfk_2` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `invites` ADD CONSTRAINT `invites_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `invites` ADD CONSTRAINT `invites_ibfk_2` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `password_resets` ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `token_purchases` ADD CONSTRAINT `token_purchases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `user_tokens` ADD CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `withdrawals` ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",
        "ALTER TABLE `withdrawals` ADD CONSTRAINT `withdrawals_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL",
        "ALTER TABLE `settings` ADD CONSTRAINT `settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL"
    );
    
    foreach ($constraints as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Ignore constraint errors if they already exist (error code 23000 = integrity constraint violation)
            // Also ignore error code 42000 for syntax/access errors on duplicate constraints
            $error_code = $e->getCode();
            $error_message = $e->getMessage();
            
            if ($error_code === '23000' || 
                $error_code === '42000' ||
                strpos($error_message, 'Duplicate key name') !== false || 
                strpos($error_message, 'already exists') !== false ||
                strpos($error_message, 'Cannot add or update a child row') !== false) {
                // These are expected errors when constraints already exist - ignore them
                continue;
            }
            
            // For any other error, log it but don't throw (to prevent transaction rollback)
            error_log("Foreign key constraint warning: " . $e->getMessage());
        }
    }
}

function handleDatabaseStep($post_data) {
    $errors = array();
    $success = array();
    
    // Get and validate form data
    $db_host = trim($post_data['db_host'] ?? '');
    $db_name = trim($post_data['db_name'] ?? '');
    $db_user = trim($post_data['db_user'] ?? '');
    $db_pass = $post_data['db_pass'] ?? '';
    $db_port = (int)($post_data['db_port'] ?? 3306);
    
    // Basic validation
    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $errors[] = "Please fill in all required database fields.";
        return compact('errors', 'success');
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
        $errors[] = "Database name can only contain letters, numbers, and underscores.";
        return compact('errors', 'success');
    }
    
    // Test database connection
    try {
        $dsn = "mysql:host=$db_host;port=$db_port;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10
        ]);
        
        // Test if we can create databases
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");
        
        // Test basic operations
        $pdo->exec("CREATE TABLE IF NOT EXISTS `installer_test` (`id` int AUTO_INCREMENT PRIMARY KEY)");
        $pdo->exec("DROP TABLE `installer_test`");
        
        // Store database config in session
        $_SESSION['db_config'] = array(
            'host' => $db_host,
            'name' => $db_name,
            'user' => $db_user,
            'pass' => $db_pass,
            'port' => $db_port
        );
        
        $success[] = "Database connection successful and ready for installation!";
        
    } catch (PDOException $e) {
        $error_msg = "Database connection failed: ";
        
        // Provide user-friendly error messages
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            $error_msg .= "Invalid username or password.";
        } elseif (strpos($e->getMessage(), "Can't connect") !== false) {
            $error_msg .= "Cannot connect to database server. Check host and port.";
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            $error_msg .= "Database does not exist and cannot be created. Check permissions.";
        } else {
            $error_msg .= $e->getMessage();
        }
        
        $errors[] = $error_msg;
    }
    
    return compact('errors', 'success');
}

function handleAdminAccountStep($post_data) {
    $errors = array();
    $success = array();
    
    if (!isset($_SESSION['db_config'])) {
        $errors[] = "Database configuration lost. Please start over.";
        return compact('errors', 'success');
    }
    
    // Get and validate form data
    $admin_name = trim($post_data['admin_name'] ?? '');
    $admin_email = trim($post_data['admin_email'] ?? '');
    $admin_password = $post_data['admin_password'] ?? '';
    $admin_confirm = $post_data['admin_confirm'] ?? '';
    $site_name = trim($post_data['site_name'] ?? 'PhPstrap');
    
    // Validation
    if (empty($admin_name) || empty($admin_email) || empty($admin_password)) {
        $errors[] = "All admin fields are required.";
    }
    
    if (strlen($admin_name) < 2 || strlen($admin_name) > 100) {
        $errors[] = "Admin name must be between 2 and 100 characters.";
    }
    
    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (strlen($admin_password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    
    if ($admin_password !== $admin_confirm) {
        $errors[] = "Passwords do not match.";
    }
    
    if (strlen($site_name) < 1 || strlen($site_name) > 100) {
        $errors[] = "Site name must be between 1 and 100 characters.";
    }
    
    if (empty($errors)) {
        try {
            $db = $_SESSION['db_config'];
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_AUTOCOMMIT => true
            ]);
            
            $admin_user_id = null;
            $installation_successful = false;
            $was_update = false;
            
            // Check if admin user already exists (before installation)
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR is_admin = 1");
                $stmt->execute([$admin_email]);
                $was_update = $stmt->fetchColumn() !== false;
            } catch (Exception $e) {
                // Table might not exist yet, ignore error
                $was_update = false;
            }
            
            // Try transaction-based installation first
            try {
                // Check if we're already in a transaction and rollback if needed
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                // Start a fresh transaction for the entire installation process
                $pdo->beginTransaction();
                
                // Install database schema (but don't let it manage its own transaction)
                installDatabaseSchemaWithoutTransaction($pdo);
                
                // Create admin user (safe version that handles existing users)
                $admin_user_id = createAdminUserSafe($pdo, $admin_name, $admin_email, $admin_password);
                
                // Update site settings (safe version)
                updateSiteSettingsSafe($pdo, $admin_email, $site_name, $admin_user_id);
                
                // Commit the entire installation
                $pdo->commit();
                $installation_successful = true;
                
            } catch (Exception $e) {
                // Rollback on any error - but check if transaction is still active
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                error_log("Transaction-based installation failed: " . $e->getMessage());
                
                // Try simpler installation without transactions
                try {
                    installDatabaseSchemaSimple($pdo);
                    $admin_user_id = createAdminUserSafe($pdo, $admin_name, $admin_email, $admin_password);
                    updateSiteSettingsSafe($pdo, $admin_email, $site_name, $admin_user_id);
                    $installation_successful = true;
                    
                } catch (Exception $e2) {
                    throw new Exception("Both installation methods failed. Transaction error: " . $e->getMessage() . " | Simple error: " . $e2->getMessage());
                }
            }
            
            if ($installation_successful && $admin_user_id) {
                // Get the admin user's API token for display
                $stmt = $pdo->prepare("SELECT api_token FROM users WHERE id = ?");
                $stmt->execute([$admin_user_id]);
                $admin_api_token = $stmt->fetchColumn();
                
                // Store admin info for final step
                $_SESSION['admin_info'] = array(
                    'id' => $admin_user_id,
                    'name' => $admin_name,
                    'email' => $admin_email,
                    'api_token' => $admin_api_token
                );
                $_SESSION['site_name'] = $site_name;
                
                // Check if this was an update to existing installation
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$admin_email]);
                $user_existed = $stmt->fetchColumn() > 0;
                
                if ($user_existed) {
                    $success[] = "Database schema verified and administrator account updated successfully!";
                    $success[] = "Previous installation data was preserved and updated.";
                } else {
                    $success[] = "Database schema installed and administrator account created successfully!";
                }
            } else {
                throw new Exception("Installation completed but admin user creation failed");
            }
            
        } catch (Exception $e) {
            $errors[] = "Installation failed: " . $e->getMessage();
            error_log("Database installation error: " . $e->getMessage());
        }
    }
    
    return compact('errors', 'success');
}

function handleModuleStep($post_data) {
    $errors = array();
    $success = array();
    
    try {
        // Get selected modules
        $selected_modules = isset($post_data['modules']) ? $post_data['modules'] : array();
        
        // Validate module names
        $valid_modules = ['hcaptcha', 'smtp', 'analytics'];
        $selected_modules = array_intersect($selected_modules, $valid_modules);
        
        // Store module selections
        $_SESSION['selected_modules'] = $selected_modules;
        
        if (empty($selected_modules)) {
            $success[] = "No modules selected. You can install modules later from the admin panel.";
        } else {
            $success[] = "Selected " . count($selected_modules) . " modules for installation: " . implode(', ', $selected_modules);
        }
        
    } catch (Exception $e) {
        $errors[] = "Module selection failed: " . $e->getMessage();
    }
    
    return compact('errors', 'success');
}

function handleConfigurationStep($post_data) {
    $errors = array();
    $success = array();
    
    if (!isset($_SESSION['db_config']) || !isset($_SESSION['admin_info'])) {
        $errors[] = "Installation data lost. Please start over.";
        return compact('errors', 'success');
    }
    
    try {
        // Create necessary directories
        createInstallationDirectories();
        
        // Generate core configuration files
        generateCoreConfigFiles();
        
        // Install selected modules
        if (isset($_SESSION['selected_modules']) && !empty($_SESSION['selected_modules'])) {
            installSelectedModules();
        }
        
        // Create security and optimization files
        createSecurityFiles();
        
        // Final system optimization
        optimizeInstallation();
        
        $success[] = "Installation configuration completed successfully! PhPstrap is ready to use.";
        
    } catch (Exception $e) {
        $errors[] = "Configuration failed: " . $e->getMessage();
        error_log("Configuration error: " . $e->getMessage());
    }
    
    return compact('errors', 'success');
}

function createInstallationDirectories() {
    $dirs = [
        'config' => 0755,
        'includes' => 0755,
        'modules' => 0755,
        'logs' => 0755,
        'uploads' => 0755,
        'cache' => 0755,
        'lang' => 0755,
        'assets' => 0755,
        'assets/css' => 0755,
        'assets/js' => 0755,
        'assets/img' => 0755
    ];
    
    foreach ($dirs as $dir => $permissions) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, $permissions, true)) {
                throw new Exception("Could not create directory: $dir");
            }
        }
        
        // Add protection to sensitive directories
        if (in_array($dir, ['logs', 'cache', 'config'])) {
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents("$dir/.htaccess", $htaccess_content);
        }
    }
}

function generateCoreConfigFiles() {
    // Generate database configuration
    $db_config = generateDatabaseConfig($_SESSION['db_config']);
    if (file_put_contents('config/database.php', $db_config) === false) {
        throw new Exception("Could not write database configuration file");
    }
    
    // Generate application configuration
    $app_config = generateAppConfig($_SESSION['site_name']);
    if (file_put_contents('config/app.php', $app_config) === false) {
        throw new Exception("Could not write application configuration file");
    }
    
    // Generate settings helper
    $settings_helper = generateSettingsHelper();
    if (file_put_contents('includes/settings.php', $settings_helper) === false) {
        throw new Exception("Could not write settings helper file");
    }
    
    // Generate modules system
    $modules_system = generateModulesSystem();
    if (file_put_contents('includes/modules.php', $modules_system) === false) {
        throw new Exception("Could not write modules system file");
    }
    
    // Generate language file
    $lang_en = generateLanguageFile();
    if (file_put_contents('lang/lang_en.php', $lang_en) === false) {
        throw new Exception("Could not write language file");
    }
}

function installSelectedModules() {
    if (!isset($_SESSION['selected_modules']) || empty($_SESSION['selected_modules'])) {
        return;
    }
    
    $db = $_SESSION['db_config'];
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    foreach ($_SESSION['selected_modules'] as $module_name) {
        try {
            installCoreModule($pdo, $module_name);
        } catch (Exception $e) {
            error_log("Failed to install module $module_name: " . $e->getMessage());
            // Continue with other modules even if one fails
        }
    }
}

function installCoreModule($pdo, $module_name) {
    $core_modules = getCoreModuleDefinitions();
    
    if (!isset($core_modules[$module_name])) {
        throw new Exception("Unknown module: $module_name");
    }
    
    $module = $core_modules[$module_name];
    
    // Insert module into database
    $stmt = $pdo->prepare("
        INSERT INTO modules (name, title, description, version, enabled, settings, hooks) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            version = VALUES(version),
            settings = VALUES(settings),
            hooks = VALUES(hooks)
    ");
    
    $stmt->execute([
        $module_name,
        $module['title'],
        $module['description'],
        $module['version'],
        $module['auto_enable'] ? 1 : 0,
        json_encode($module['settings']),
        json_encode($module['hooks'] ?? [])
    ]);
    
    // Create module files
    createModuleFiles($module_name, $module);
}

function getCoreModuleDefinitions() {
    return [
        'hcaptcha' => [
            'title' => 'hCaptcha Protection',
            'description' => 'Add spam protection to forms using hCaptcha service',
            'version' => '1.0.0',
            'auto_enable' => false,
            'settings' => [
                'site_key' => '',
                'secret_key' => '',
                'enabled' => false,
                'theme' => 'light',
                'size' => 'normal'
            ],
            'hooks' => [
                'form_captcha' => [
                    ['method' => 'renderCaptcha', 'priority' => 10]
                ],
                'form_validate' => [
                    ['method' => 'validateCaptcha', 'priority' => 10]
                ]
            ]
        ],
        'smtp' => [
            'title' => 'SMTP Email',
            'description' => 'Send emails via SMTP instead of PHP mail() function',
            'version' => '1.0.0',
            'auto_enable' => false,
            'settings' => [
                'enabled' => false,
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_encryption' => 'tls',
                'from_email' => '',
                'from_name' => 'PhPstrap'
            ],
            'hooks' => [
                'send_email' => [
                    ['method' => 'sendEmail', 'priority' => 5]
                ]
            ]
        ],
        'analytics' => [
            'title' => 'Analytics Tracking',
            'description' => 'Google Analytics and Facebook Pixel integration',
            'version' => '1.0.0',
            'auto_enable' => false,
            'settings' => [
                'enabled' => false,
                'google_analytics_id' => '',
                'facebook_pixel_id' => '',
                'google_tag_manager_id' => '',
                'track_user_id' => false
            ],
            'hooks' => [
                'html_head' => [
                    ['method' => 'renderHeadTags', 'priority' => 10]
                ],
                'html_body' => [
                    ['method' => 'renderBodyTags', 'priority' => 10]
                ]
            ]
        ]
    ];
}

function createModuleFiles($module_name, $module_config) {
    $module_dir = "modules/$module_name";
    
    if (!is_dir($module_dir)) {
        if (!mkdir($module_dir, 0755, true)) {
            throw new Exception("Could not create module directory: $module_dir");
        }
    }
    
    // Create module.json configuration
    $module_json = [
        'name' => $module_name,
        'title' => $module_config['title'],
        'description' => $module_config['description'],
        'version' => $module_config['version'],
        'settings' => $module_config['settings'],
        'hooks' => $module_config['hooks'] ?? []
    ];
    
    file_put_contents("$module_dir/module.json", json_encode($module_json, JSON_PRETTY_PRINT));
    
    // Create basic module PHP class
    $module_php = generateModuleTemplate($module_name, $module_config);
    file_put_contents("$module_dir/$module_name.php", $module_php);
    
    // Create module-specific files if needed
    createModuleSpecificFiles($module_name, $module_dir);
}

function createModuleSpecificFiles($module_name, $module_dir) {
    switch ($module_name) {
        case 'hcaptcha':
            // Create hCaptcha specific documentation
            $readme = "# hCaptcha Module\n\nThis module adds hCaptcha spam protection to your forms.\n\n## Configuration\n\n1. Sign up at https://www.hcaptcha.com/\n2. Get your site key and secret key\n3. Configure in Admin Panel > Modules > hCaptcha\n\n## Usage\n\nAdd to your forms:\n```php\necho executeHook('form_captcha', '');\n```";
            file_put_contents("$module_dir/README.md", $readme);
            break;
            
        case 'smtp':
            // Create SMTP configuration template
            $config_template = "# SMTP Configuration Template\n\n## Gmail\nHost: smtp.gmail.com\nPort: 587\nEncryption: TLS\n\n## Outlook\nHost: smtp-mail.outlook.com\nPort: 587\nEncryption: STARTTLS\n\n## Yahoo\nHost: smtp.mail.yahoo.com\nPort: 587\nEncryption: TLS";
            file_put_contents("$module_dir/smtp-providers.md", $config_template);
            break;
            
        case 'analytics':
            // Create analytics tracking template
            $template = "# Analytics Module\n\nThis module integrates Google Analytics and Facebook Pixel.\n\n## Setup\n\n1. Get your tracking IDs from respective platforms\n2. Configure in Admin Panel\n3. Tracking will be automatically added to all pages";
            file_put_contents("$module_dir/README.md", $template);
            break;
    }
}

function generateModuleTemplate($module_name, $config) {
    $class_name = ucfirst($module_name) . 'Module';
    
    $template = "<?php\n/**\n * {$config['title']} Module\n * {$config['description']}\n * \n * Generated by PhPstrap Installer\n * Version: {$config['version']}\n */\n\nclass $class_name extends BaseModule {\n    \n    public function init() {\n        // Module initialization\n        // This method is called when the module is loaded\n    }\n    \n";
    
    // Add hook methods based on module type
    switch ($module_name) {
        case 'hcaptcha':
            $template .= "    public function renderCaptcha(\$data) {\n        \$site_key = \$this->getSetting('site_key', '');\n        if (empty(\$site_key) || !\$this->getSetting('enabled', false)) {\n            return \$data;\n        }\n        \n        \$html = '<div class=\"h-captcha\" data-sitekey=\"' . htmlspecialchars(\$site_key) . '\"></div>';\n        \$html .= '<script src=\"https://js.hcaptcha.com/1/api.js\" async defer></script>';\n        \n        return \$data . \$html;\n    }\n    \n    public function validateCaptcha(\$data) {\n        if (!\$this->getSetting('enabled', false)) {\n            return \$data;\n        }\n        \n        \$secret_key = \$this->getSetting('secret_key', '');\n        \$response = \$_POST['h-captcha-response'] ?? '';\n        \n        if (empty(\$secret_key) || empty(\$response)) {\n            \$data['errors'][] = 'Captcha verification required.';\n            return \$data;\n        }\n        \n        // Verify with hCaptcha API\n        \$verify_url = 'https://api.hcaptcha.com/siteverify';\n        \$post_data = http_build_query([\n            'secret' => \$secret_key,\n            'response' => \$response\n        ]);\n        \n        \$context = stream_context_create([\n            'http' => [\n                'method' => 'POST',\n                'header' => 'Content-Type: application/x-www-form-urlencoded',\n                'content' => \$post_data\n            ]\n        ]);\n        \n        \$result = file_get_contents(\$verify_url, false, \$context);\n        \$response_data = json_decode(\$result, true);\n        \n        if (!\$response_data['success']) {\n            \$data['errors'][] = 'Captcha verification failed.';\n        }\n        \n        return \$data;\n    }\n";
            break;
            
        case 'smtp':
            $template .= "    public function sendEmail(\$data) {\n        if (!\$this->getSetting('enabled', false)) {\n            return \$data; // Fall back to default email\n        }\n        \n        // This is a basic implementation\n        // In production, you would use PHPMailer or similar\n        \$data['sent'] = false;\n        \$data['error'] = 'SMTP module requires PHPMailer library';\n        \n        return \$data;\n    }\n";
            break;
            
        case 'analytics':
            $template .= "    public function renderHeadTags(\$data) {\n        if (!\$this->getSetting('enabled', false)) {\n            return \$data;\n        }\n        \n        \$html = '';\n        \n        // Google Analytics\n        \$ga_id = \$this->getSetting('google_analytics_id', '');\n        if (!empty(\$ga_id)) {\n            \$html .= \"<script async src='https://www.googletagmanager.com/gtag/js?id={\$ga_id}'></script>\";\n            \$html .= \"<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{\$ga_id}');</script>\";\n        }\n        \n        // Facebook Pixel\n        \$fb_id = \$this->getSetting('facebook_pixel_id', '');\n        if (!empty(\$fb_id)) {\n            \$html .= \"<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{\$fb_id}');fbq('track','PageView');</script>\";\n        }\n        \n        return \$data . \$html;\n    }\n";
            break;
    }
    
    $template .= "}\n?>";
    
    return $template;
}

function createSecurityFiles() {
    // Create main .htaccess
    $htaccess = generateHtaccess();
    file_put_contents('.htaccess', $htaccess);
    
    // Create robots.txt
    $robots = generateRobotsTxt();
    file_put_contents('robots.txt', $robots);
    
    // Create maintenance page template
    $maintenance = generateMaintenancePage();
    file_put_contents('maintenance.php', $maintenance);
}

function optimizeInstallation() {
    // Set proper file permissions (if possible)
    try {
        chmod('config', 0755);
        chmod('config/database.php', 0644);
        chmod('config/app.php', 0644);
        chmod('logs', 0755);
        chmod('uploads', 0755);
        chmod('cache', 0755);
    } catch (Exception $e) {
        // Ignore permission errors - not all hosting environments allow this
    }
    
    // Clear any PHP opcache if available
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    // Set up log rotation (basic)
    $log_rotate_config = "# PhPstrap log rotation\n# Add this to your system's logrotate configuration\n/path/to/PhPstrap/logs/*.log {\n    daily\n    missingok\n    rotate 52\n    compress\n    notifempty\n    create 644 www-data www-data\n}";
    file_put_contents('logs/logrotate.conf', $log_rotate_config);
}

function cleanupInstaller() {
    // Clear session
    session_destroy();
    
    // List of installer files to remove
    $installer_files = [
        __FILE__, // This installer file
        'installer/requirements.php',
        'installer/database.php',
        'installer/config.php',
        'installer/ui.php'
    ];
    
    $removed_files = [];
    $failed_files = [];
    
    foreach ($installer_files as $file) {
        if (file_exists($file)) {
            if (unlink($file)) {
                $removed_files[] = $file;
            } else {
                $failed_files[] = $file;
            }
        }
    }
    
    // Remove installer directory if empty
    if (is_dir('installer')) {
        $files = scandir('installer');
        if (count($files) <= 2) { // Only . and .. remain
            rmdir('installer');
        }
    }
    
    // Log cleanup results
    if (!empty($failed_files)) {
        error_log("Failed to remove installer files: " . implode(', ', $failed_files));
    }
    
    // Redirect to login page
    header('Location: login/');
    exit;
}
?>