<?php

/**
 * Sendy Module Uninstallation Script
 * 
 * This script handles the safe removal of the Sendy Newsletter Integration module.
 * It provides options for:
 * - Complete or partial data removal
 * - Backup creation before uninstallation
 * - Module deregistration and cleanup
 * - File system cleanup
 * - Permission and cache cleanup
 * - Rollback capabilities if needed
 */

/**
 * Uninstall the Sendy Module
 * 
 * @param PDO $pdo Database connection
 * @param array $options Uninstall options
 * @return bool Success status
 */
function uninstall_sendy_module($pdo, $options = [])
{
    try {
        // Set default options
        $options = array_merge([
            'remove_data' => false,        // Keep subscription data by default
            'remove_settings' => true,     // Remove module settings
            'remove_files' => false,       // Keep files by default
            'remove_logs' => true,         // Remove API logs
            'remove_cache' => true,        // Clear cache
            'create_backup' => true,       // Create backup before removal
            'force_removal' => false,      // Force removal even if errors occur
            'keep_stats' => false          // Keep statistics data
        ], $options);
        
        // Start database transaction for atomic uninstallation
        $pdo->beginTransaction();
        
        // Trigger pre-uninstall hooks
        if (function_exists('do_action')) {
            do_action('sendy_module_before_uninstall', $options);
        }
        
        // Create backup if requested
        if ($options['create_backup']) {
            $backup_result = create_sendy_uninstall_backup($pdo);
            if (!$backup_result && !$options['force_removal']) {
                throw new Exception("Failed to create backup - uninstall aborted");
            }
        }
        
        // Deactivate module and clear cron jobs
        if (!deactivate_sendy_module($pdo)) {
            if (!$options['force_removal']) {
                throw new Exception("Failed to deactivate module");
            }
        }
        
        // Remove module data based on options
        if ($options['remove_data']) {
            if (!remove_sendy_subscription_data($pdo, $options['force_removal'])) {
                throw new Exception("Failed to remove subscription data");
            }
        }
        
        // Remove API logs
        if ($options['remove_logs']) {
            if (!remove_sendy_api_logs($pdo, $options['force_removal'])) {
                if (!$options['force_removal']) {
                    throw new Exception("Failed to remove API logs");
                }
            }
        }
        
        // Remove cache data
        if ($options['remove_cache']) {
            if (!remove_sendy_cache_data($pdo, $options['force_removal'])) {
                if (!$options['force_removal']) {
                    throw new Exception("Failed to remove cache data");
                }
            }
        }
        
        // Remove statistics data
        if (!$options['keep_stats']) {
            if (!remove_sendy_stats_data($pdo, $options['force_removal'])) {
                if (!$options['force_removal']) {
                    throw new Exception("Failed to remove statistics data");
                }
            }
        }
        
        // Remove module settings and configuration
        if ($options['remove_settings']) {
            if (!remove_sendy_module_settings($pdo)) {
                throw new Exception("Failed to remove module settings");
            }
        }
        
        // Remove module permissions
        if (!remove_sendy_module_permissions($pdo)) {
            if (!$options['force_removal']) {
                throw new Exception("Failed to remove module permissions");
            }
        }
        
        // Deregister the module
        if (!deregister_sendy_module($pdo)) {
            throw new Exception("Failed to deregister module");
        }
        
        // Clear all module cache
        clear_sendy_module_cache($pdo);
        
        // Remove files if requested
        if ($options['remove_files']) {
            remove_sendy_module_files();
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Log successful uninstallation
        error_log("Sendy Module: Uninstallation completed successfully");
        
        // Trigger post-uninstall hooks
        if (function_exists('do_action')) {
            do_action('sendy_module_after_uninstall', $options);
        }
        
        return true;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        // Log error
        error_log("Sendy Module Uninstall Error: " . $e->getMessage());
        
        // Attempt to restore from backup if available
        if ($options['create_backup']) {
            restore_sendy_from_backup($pdo);
        }
        
        return false;
    }
}

/**
 * Deactivate the module before uninstallation
 * 
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
function deactivate_sendy_module($pdo)
{
    try {
        // Check if module exists and is active
        $stmt = $pdo->prepare("SELECT id, settings FROM modules WHERE name = 'sendy_module'");
        $stmt->execute();
        $module = $stmt->fetch();
        
        if (!$module) {
            return true; // Module doesn't exist, consider it deactivated
        }
        
        // Load module if it exists to call deactivation method
        $module_path = dirname(__FILE__) . '/SendyModule.php';
        if (file_exists($module_path)) {
            require_once $module_path;
            
            if (class_exists('PhPstrap\\Modules\\Sendy\\SendyModule')) {
                $moduleInstance = new \PhPstrap\Modules\Sendy\SendyModule($pdo);
                if (method_exists($moduleInstance, 'deactivate')) {
                    $moduleInstance->deactivate();
                }
            }
        }
        
        // Clear scheduled cron events
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('sendy_module_sync_stats');
            wp_clear_scheduled_hook('sendy_module_cleanup');
        }
        
        // Mark module as inactive
        $stmt = $pdo->prepare("
            UPDATE modules 
            SET enabled = 0, status = 'inactive', updated_at = NOW() 
            WHERE name = 'sendy_module'
        ");
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Sendy Module: Deactivation error - " . $e->getMessage());
        return false;
    }
}

/**
 * Create backup before uninstallation
 * 
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
function create_sendy_uninstall_backup($pdo)
{
    try {
        $backup_timestamp = date('Y-m-d_H-i-s');
        $backup_prefix = "sendy_module_backup_{$backup_timestamp}";
        
        // Backup subscription data
        $pdo->exec("
            CREATE TABLE {$backup_prefix}_subscriptions AS 
            SELECT * FROM sendy_module_subscriptions
        ");
        
        // Backup API logs
        $pdo->exec("
            CREATE TABLE {$backup_prefix}_api_logs AS 
            SELECT * FROM sendy_module_api_logs
        ");
        
        // Backup cache data
        $pdo->exec("
            CREATE TABLE {$backup_prefix}_cache AS 
            SELECT * FROM sendy_module_cache
        ");
        
        // Backup statistics
        $pdo->exec("
            CREATE TABLE {$backup_prefix}_stats AS 
            SELECT * FROM sendy_module_stats
        ");
        
        // Backup module settings
        $pdo->exec("
            CREATE TABLE {$backup_prefix}_module_settings AS 
            SELECT * FROM modules WHERE name = 'sendy_module'
        ");
        
        // Create backup metadata
        $backup_info = [
            'backup_prefix' => $backup_prefix,
            'created_at' => date('Y-m-d H:i:s'),
            'module_version' => '1.0.0',
            'tables_backed_up' => [
                'sendy_module_subscriptions',
                'sendy_module_api_logs',
                'sendy_module_cache',
                'sendy_module_stats',
                'modules (sendy_module only)'
            ]
        ];
        
        // Store backup metadata
        $stmt = $pdo->prepare("
            INSERT INTO {$backup_prefix}_cache (cache_key, cache_value, cache_group, created_at)
            VALUES ('backup_metadata', ?, 'backup_info', NOW())
        ");
        $stmt->execute([json_encode($backup_info)]);
        
        // Log backup creation
        error_log("Sendy Module: Backup created with prefix {$backup_prefix}");
        
        return true;
        
    } catch (Exception $e) {
        error_log("Sendy Module: Backup creation error - " . $e->getMessage());
        return false;
    }
}

/**
 * Remove subscription data
 * 
 * @param PDO $pdo Database connection
 * @param bool $force_removal Force removal even on errors
 * @return bool Success status
 */
function remove_sendy_subscription_data($pdo, $force_removal = false)
{
    try {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'sendy_module_subscriptions'");
        if ($stmt->rowCount() > 0) {
            $pdo->exec("DROP TABLE sendy_module_subscriptions");
            error_log("Sendy Module: Removed subscription data table");
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Sendy Module: Subscription data removal error - " . $e->getMessage());
        return false;
    }
}

/**
 * Remove API logs
 * 
 * @param PDO $pdo Database connection
 * @param bool $force_removal Force removal even on errors
 * @return bool Success status
 */
function remove_sendy_api_logs($pdo, $force_removal = false)
{
    try {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'sendy_module_api_logs'");
        if ($stmt->rowCount() > 0) {
            $pdo->exec("DROP TABLE sendy_module_api_logs");
            error_log("Sendy Module: Removed API logs table");
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Sendy Module: API logs removal error - " . $e->getMessage());
        return false;
    }
}

/**
 * Remove cache data
 * 
 * @param PDO $pdo Database connection
 * @param bool $force_removal Force removal even on errors
 * @return bool Success status
 */
function remove_sendy_cache_data($pdo, $force_removal = false)
{
    try {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'sendy_module_cache'");
        if ($stmt->rowCount() > 0) {
            $pdo->exec("DROP TABLE sendy_module_cache");
            error_log("Sendy Module: Removed cache data table");
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Sendy Module: Cache data removal error - " . $e->getMessage());
        return false;
    }
}

/**
 * Remove statistics data
 * 
 * @param PDO $pdo Database connection
 * @param bool $force_removal Force removal even on errors
 * @return bool Success status
 */
function remove_sendy_stats_data($pdo, $force_removal = false)
{
    try {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'sendy_module_stats'");
        if ($stmt->rowCount() > 0) {
            $pdo->exec("DROP TABLE sendy_module_stats");
            error_log("Sendy Module: Removed statistics data table");
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Sendy Module: Statistics data removal error - " . $e->getMessage());
        return false;
    }
}

/**
 * Remove module settings and configuration
 * 
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
function remove_sendy_module_settings($pdo)
{
    try {
        // Module removal will be handled by deregister_sendy_module
        
        // Remove any cached settings if using WordPress
        if (function_exists('delete_option')) {
            delete_option('sendy_module_settings');
            delete_option('sendy_module_cache');
            delete_option('sendy_module_version');
        }
        
        // Remove any transients
        if (function_exists('delete_transient')) {
            delete_transient('sendy_module_lists');
            delete_transient('sendy_module_stats');
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Sendy Module: Settings removal error - " . $e->getMessage());
        return false;
    }
}

/**
 * Remove module permissions from the system
 * 
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
function remove_sendy_module_permissions($pdo)
{
    try {
        // Check if permissions table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'permissions'");
        if ($stmt->rowCount() > 0) {
            // Remove module permissions
            $stmt = $pdo->prepare("DELETE FROM permissions WHERE module = 'sendy_module'");
            $stmt->execute();
            
            // Remove role-permission associations if they exist
            $stmt = $pdo->query("SHOW TABLES LIKE 'role_permissions'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    DELETE rp FROM role_permissions rp
                    INNER JOIN permissions p ON rp.permission_id = p.id
                    WHERE p.module = 'sendy_module'
                ");
                $stmt->execute();
            }
            
            // Remove user-permission associations if they exist
            $stmt = $pdo->query("SHOW TABLES LIKE 'user_permissions'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    DELETE up FROM user_permissions up
                    INNER JOIN permissions p ON up.permission_id = p.id
                    WHERE p.module = 'sendy_module'
                ");
                $stmt->execute();
            }
        }
        
        error_log("Sendy Module: Permissions removed successfully");
        return true;
        
    } catch (Exception $e) {
        error_log("Sendy Module: Permission removal error - " . $e->getMessage());
        return false;
    }
}

/**
 * Deregister the module from PhPstrap
 * 
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
function deregister_sendy_module($pdo)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM modules WHERE name = 'sendy_module'");
        $result = $stmt->execute();
        
        if ($result) {
            error_log("Sendy Module: Module deregistered successfully");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Sendy Module: Deregistration error - " . $e->getMessage());
        return false;
    }
}

/**
 * Clear all module cache entries
 * 
 * @param PDO $pdo Database connection
 */
function clear_sendy_module_cache($pdo)
{
    try {
        // Clear from WordPress cache if functions exist
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('sendy_module_settings');
            wp_cache_delete('sendy_module_lists');
            wp_cache_delete('sendy_module_stats');
            wp_cache_delete('sendy_module_version');
        }
        
        // Clear object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear file-based cache if it exists
        $cache_dir = dirname(__FILE__) . '/cache';
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        error_log("Sendy Module: Cache cleared successfully");
        
    } catch (Exception $e) {
        error_log("Sendy Module: Cache clearing error - " . $e->getMessage());
    }
}

/**
 * Remove module files from the filesystem
 */
function remove_sendy_module_files()
{
    try {
        $module_path = dirname(__FILE__);
        
        // Files to remove
        $files_to_remove = [
            '/assets/sendy-module.css',
            '/assets/sendy-module.js',
            '/assets/sendy-admin.js',
            '/assets/sendy-admin.css',
            '/views/subscription-form.php',
            '/views/widget.php',
            '/views/admin-settings.php',
            '/views/admin-dashboard.php',
            '/views/admin-stats.php',
            '/languages/sendy-module.pot',
            '/languages/sendy-module-en.po',
            '/languages/sendy-module-en.mo'
        ];
        
        foreach ($files_to_remove as $file) {
            $full_path = $module_path . $file;
            if (file_exists($full_path)) {
                unlink($full_path);
                error_log("Sendy Module: Removed file {$file}");
            }
        }
        
        // Remove empty directories
        $dirs_to_remove = [
            $module_path . '/assets',
            $module_path . '/views',
            $module_path . '/languages',
            $module_path . '/cache'
        ];
        
        foreach ($dirs_to_remove as $dir) {
            if (is_dir($dir) && count(scandir($dir)) == 2) { // Only . and ..
                rmdir($dir);
                error_log("Sendy Module: Removed directory " . basename($dir));
            }
        }
        
        error_log("Sendy Module: Module files removed successfully");
        
    } catch (Exception $e) {
        error_log("Sendy Module: File removal error - " . $e->getMessage());
    }
}

/**
 * Restore module from backup
 * 
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
function restore_sendy_from_backup($pdo)
{
    try {
        // Find the most recent backup
        $stmt = $pdo->query("SHOW TABLES LIKE 'sendy_module_backup_%_cache'");
        $backup_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($backup_tables)) {
            error_log("Sendy Module: No backup found for restoration");
            return false;
        }
        
        // Get the latest backup
        rsort($backup_tables);
        $latest_cache_table = $backup_tables[0];
        $backup_prefix = str_replace('_cache', '', $latest_cache_table);
        
        // Get backup metadata
        $stmt = $pdo->prepare("
            SELECT cache_value FROM {$latest_cache_table}
            WHERE cache_key = 'backup_metadata'
        ");
        $stmt->execute();
        $backup_metadata = $stmt->fetch();
        
        if ($backup_metadata) {
            $metadata = json_decode($backup_metadata['cache_value'], true);
            error_log("Sendy Module: Restoring from backup created at " . $metadata['created_at']);
        }
        
        // Restore data tables
        $restore_tables = [
            'subscriptions' => 'sendy_module_subscriptions',
            'api_logs' => 'sendy_module_api_logs',
            'cache' => 'sendy_module_cache',
            'stats' => 'sendy_module_stats'
        ];
        
        foreach ($restore_tables as $suffix => $target_table) {
            $source_table = $backup_prefix . '_' . $suffix;
            
            // Check if source table exists
            $stmt = $pdo->query("SHOW TABLES LIKE '{$source_table}'");
            if ($stmt->rowCount() > 0) {
                // Drop existing table if it exists
                $pdo->exec("DROP TABLE IF EXISTS {$target_table}");
                
                // Recreate table from backup
                $pdo->exec("
                    CREATE TABLE {$target_table} AS 
                    SELECT * FROM {$source_table}
                ");
                
                error_log("Sendy Module: Restored table {$target_table}");
            }
        }
        
        // Restore module registration
        $stmt = $pdo->query("SHOW TABLES LIKE '{$backup_prefix}_module_settings'");
        if ($stmt->rowCount() > 0) {
            $pdo->exec("
                INSERT INTO modules 
                SELECT * FROM {$backup_prefix}_module_settings
                ON DUPLICATE KEY UPDATE
                    settings = VALUES(settings),
                    enabled = VALUES(enabled),
                    status = VALUES(status)
            ");
            
            error_log("Sendy Module: Restored module settings");
        }
        
        error_log("Sendy Module: Restored from backup {$backup_prefix}");
        return true;
        
    } catch (Exception $e) {
        error_log("Sendy Module: Backup restoration error - " . $e->getMessage());
        return false;
    }
}

/**
 * Clean up backup tables after successful uninstall
 * 
 * @param PDO $pdo Database connection
 * @param int $keep_days Number of days to keep backups
 */
function cleanup_sendy_old_backups($pdo, $keep_days = 30)
{
    try {
        // Find old backup tables
        $stmt = $pdo->query("SHOW TABLES LIKE 'sendy_module_backup_%'");
        $backup_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $cutoff_date = date('Y-m-d', strtotime("-{$keep_days} days"));
        
        foreach ($backup_tables as $table) {
            // Extract date from table name
            if (preg_match('/sendy_module_backup_(\d{4}-\d{2}-\d{2})_/', $table, $matches)) {
                $backup_date = $matches[1];
                
                if ($backup_date < $cutoff_date) {
                    $pdo->exec("DROP TABLE {$table}");
                    error_log("Sendy Module: Cleaned up old backup table {$table}");
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Sendy Module: Backup cleanup error - " . $e->getMessage());
    }
}

/**
 * Verify uninstallation success
 * 
 * @param PDO $pdo Database connection
 * @return array Verification results
 */
function verify_sendy_module_uninstall($pdo)
{
    $results = [
        'module_removed' => false,
        'tables_removed' => false,
        'permissions_removed' => false,
        'cache_cleared' => false,
        'files_removed' => false,
        'success' => false
    ];
    
    try {
        // Check if module is removed from modules table
        $stmt = $pdo->prepare("SELECT id FROM modules WHERE name = 'sendy_module'");
        $stmt->execute();
        $results['module_removed'] = ($stmt->rowCount() == 0);
        
        // Check if tables are removed
        $tables = ['sendy_module_subscriptions', 'sendy_module_api_logs', 'sendy_module_cache', 'sendy_module_stats'];
        $tables_exist = 0;
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if ($stmt->rowCount() > 0) {
                $tables_exist++;
            }
        }
        $results['tables_removed'] = ($tables_exist == 0);
        
        // Check if permissions are removed
        $stmt = $pdo->query("SHOW TABLES LIKE 'permissions'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT id FROM permissions WHERE module = 'sendy_module'");
            $stmt->execute();
            $results['permissions_removed'] = ($stmt->rowCount() == 0);
        } else {
            $results['permissions_removed'] = true;
        }
        
        // Check cache status (assume cleared if no errors)
        $results['cache_cleared'] = true;
        
        // Check if files are removed
        $module_path = dirname(__FILE__);
        $key_files = [
            $module_path . '/assets/sendy-module.css',
            $module_path . '/assets/sendy-module.js'
        ];
        $files_exist = 0;
        foreach ($key_files as $file) {
            if (file_exists($file)) {
                $files_exist++;
            }
        }
        $results['files_removed'] = ($files_exist == 0);
        
        // Overall success
        $results['success'] = $results['module_removed'] && 
                             $results['tables_removed'] && 
                             $results['permissions_removed'];
        
    } catch (Exception $e) {
        error_log("Sendy Module: Verification error - " . $e->getMessage());
    }
    
    return $results;
}

/**
 * Get uninstall options from user input or config
 * 
 * @return array Uninstall options
 */
function get_sendy_uninstall_options()
{
    $options = [
        'remove_data' => false,
        'remove_settings' => true,
        'remove_files' => false,
        'remove_logs' => true,
        'remove_cache' => true,
        'create_backup' => true,
        'force_removal' => false,
        'keep_stats' => false
    ];
    
    // Check for command line arguments
    if (isset($_SERVER['argv'])) {
        foreach ($_SERVER['argv'] as $arg) {
            switch ($arg) {
                case '--remove-data':
                    $options['remove_data'] = true;
                    break;
                case '--remove-files':
                    $options['remove_files'] = true;
                    break;
                case '--keep-logs':
                    $options['remove_logs'] = false;
                    break;
                case '--keep-stats':
                    $options['keep_stats'] = true;
                    break;
                case '--no-backup':
                    $options['create_backup'] = false;
                    break;
                case '--force':
                    $options['force_removal'] = true;
                    break;
                case '--keep-settings':
                    $options['remove_settings'] = false;
                    break;
                case '--complete':
                    $options['remove_data'] = true;
                    $options['remove_files'] = true;
                    $options['remove_logs'] = true;
                    $options['keep_stats'] = false;
                    break;
            }
        }
    }
    
    // Check for web form input
    if (isset($_POST)) {
        foreach ($options as $key => $default) {
            if (isset($_POST[$key])) {
                $options[$key] = filter_var($_POST[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }
    }
    
    return $options;
}

/**
 * Display interactive uninstall interface
 */
function display_sendy_uninstall_interface()
{
    if (isset($_POST['confirm_uninstall'])) {
        return false; // Proceed with uninstall
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Sendy Module Uninstaller</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .danger { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .option { margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 3px; }
            .btn { padding: 10px 20px; margin: 10px 5px; border: none; border-radius: 3px; cursor: pointer; }
            .btn-danger { background: #dc3545; color: white; }
            .btn-secondary { background: #6c757d; color: white; }
        </style>
    </head>
    <body>
        <h1>🗑️ Sendy Module Uninstaller</h1>
        
        <div class="danger">
            <strong>⚠️ Warning:</strong> You are about to uninstall the Sendy Newsletter Integration module. 
            This action may be irreversible depending on the options you choose.
        </div>
        
        <form method="post">
            <h2>Uninstall Options</h2>
            
            <div class="option">
                <label>
                    <input type="checkbox" name="remove_data" value="1">
                    <strong>Remove Subscription Data</strong> - Delete all subscriber information and form submissions
                </label>
            </div>
            
            <div class="option">
                <label>
                    <input type="checkbox" name="remove_files" value="1">
                    <strong>Remove Module Files</strong> - Delete CSS, JavaScript, and template files
                </label>
            </div>
            
            <div class="option">
                <label>
                    <input type="checkbox" name="remove_logs" value="1" checked>
                    <strong>Remove API Logs</strong> - Delete API request logs and debugging information
                </label>
            </div>
            
            <div class="option">
                <label>
                    <input type="checkbox" name="create_backup" value="1" checked>
                    <strong>Create Backup</strong> - Backup all data before removal (recommended)
                </label>
            </div>
            
            <div class="option">
                <label>
                    <input type="checkbox" name="keep_stats" value="1">
                    <strong>Keep Statistics</strong> - Preserve subscription statistics and analytics data
                </label>
            </div>
            
            <div class="warning">
                <strong>Note:</strong> Module settings and permissions will always be removed. 
                The module registration will be removed from the system.
            </div>
            
            <input type="hidden" name="confirm_uninstall" value="1">
            
            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure you want to proceed with the uninstallation?')">
                Uninstall Sendy Module
            </button>
            <a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>
        </form>
    </body>
    </html>
    <?php
    return true; // Don't proceed with uninstall
}

// If called directly, run uninstallation
if (basename($_SERVER['PHP_SELF']) == 'uninstall.php') {
    try {
        // Show interface if accessed via web and no confirmation
        if (isset($_SERVER['REQUEST_METHOD']) && !isset($_POST['confirm_uninstall']) && !isset($_SERVER['argv'])) {
            if (display_sendy_uninstall_interface()) {
                exit;
            }
        }
        
        if (function_exists('getDbConnection')) {
            $pdo = getDbConnection();
            $options = get_sendy_uninstall_options();
            
            echo "Sendy Module Uninstaller\n";
            echo "========================\n";
            echo "Remove data: " . ($options['remove_data'] ? 'Yes' : 'No') . "\n";
            echo "Remove files: " . ($options['remove_files'] ? 'Yes' : 'No') . "\n";
            echo "Remove logs: " . ($options['remove_logs'] ? 'Yes' : 'No') . "\n";
            echo "Create backup: " . ($options['create_backup'] ? 'Yes' : 'No') . "\n";
            echo "Keep statistics: " . ($options['keep_stats'] ? 'Yes' : 'No') . "\n";
            echo "Force removal: " . ($options['force_removal'] ? 'Yes' : 'No') . "\n";
            echo "\n";
            
            if (uninstall_sendy_module($pdo, $options)) {
                echo "✅ Sendy Module uninstalled successfully!\n";
                
                $verification = verify_sendy_module_uninstall($pdo);
                echo "\nVerification Results:\n";
                echo "Module removed: " . ($verification['module_removed'] ? 'Yes' : 'No') . "\n";
                echo "Tables removed: " . ($verification['tables_removed'] ? 'Yes' : 'No') . "\n";
                echo "Permissions removed: " . ($verification['permissions_removed'] ? 'Yes' : 'No') . "\n";
                echo "Cache cleared: " . ($verification['cache_cleared'] ? 'Yes' : 'No') . "\n";
                echo "Files removed: " . ($verification['files_removed'] ? 'Yes' : 'No') . "\n";
                echo "Overall success: " . ($verification['success'] ? 'Yes' : 'No') . "\n";
                
                // Cleanup old backups
                cleanup_sendy_old_backups($pdo);
                
                if ($verification['success']) {
                    echo "\n🎉 Uninstallation completed successfully!\n";
                    echo "Thank you for using the Sendy Newsletter Integration module.\n";
                }
                
            } else {
                echo "❌ Sendy Module uninstallation failed!\n";
                echo "Check error logs for details.\n";
                echo "You can use --force flag to attempt forced removal.\n";
            }
        } else {
            echo "❌ Database connection not available.\n";
        }
    } catch (Exception $e) {
        echo "❌ Uninstallation error: " . $e->getMessage() . "\n";
    }
}
?>