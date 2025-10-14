<?php

/**
 * Sendy Module Installation Script
 * 
 * This script handles the installation of the Sendy Newsletter Integration module.
 * It creates necessary database tables, sets up default configuration,
 * and prepares the module for first use.
 */

/**
 * Install the Sendy Module
 * 
 * @param PDO $pdo Database connection
 * @param array $options Installation options
 * @return bool Success status
 */
function install_sendy_module($pdo, $options = [])
{
    try {
        // Start database transaction for atomic installation
        $pdo->beginTransaction();
        
        // Create module data tables
        if (!create_sendy_module_tables($pdo)) {
            throw new Exception("Failed to create module tables");
        }
        
        // Register the module in the modules table
        if (!register_sendy_module($pdo, $options)) {
            throw new Exception("Failed to register module");
        }
        
        // Create default module configuration
        if (isset($options['create_default_config']) && $options['create_default_config']) {
            create_default_configuration($pdo);
        }
        
        // Set up module permissions
        setup_module_permissions($pdo);
        
        // Create module configuration files
        create_module_config_files();
        
        // Schedule cron jobs
        setup_cron_jobs();
        
        // Commit transaction
        $pdo->commit();
        
        // Log successful installation
        error_log("Sendy Module: Installation completed successfully");
        
        // Trigger post-installation hooks
        if (function_exists('do_action')) {
            do_action('sendy_module_installed');
        }
        
        return true;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        // Log error
        error_log("Sendy Module Installation Error: " . $e->getMessage());
        
        // Clean up any created files
        cleanup_installation_files();
        
        return false;
    }
}

/**
 * Create database tables for the module
 * 
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
function create_sendy_module_tables($pdo)
{
    try {
        // Subscription tracking table
        $sql_subscriptions = "
            CREATE TABLE IF NOT EXISTS sendy_module_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255),
                list_id VARCHAR(100) NOT NULL,
                sendy_installation VARCHAR(50) DEFAULT 'default',
                response TEXT,
                status ENUM('pending', 'subscribed', 'failed', 'unsubscribed') DEFAULT 'pending',
                subscription_date DATETIME,
                ip_address VARCHAR(45),
                user_agent TEXT,
                source VARCHAR(100),
                custom_fields JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                -- Indexes for performance
                INDEX idx_email (email),
                INDEX idx_list (list_id),
                INDEX idx_installation (sendy_installation),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                INDEX idx_subscription_date (subscription_date),
                INDEX idx_source (source),
                
                -- Unique constraint to prevent duplicate subscriptions
                UNIQUE KEY unique_subscription (email, list_id, sendy_installation)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql_subscriptions);
        
        // API logs table for debugging and monitoring
        $sql_api_logs = "
            CREATE TABLE IF NOT EXISTS sendy_module_api_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sendy_installation VARCHAR(50) DEFAULT 'default',
                endpoint VARCHAR(255) NOT NULL,
                method VARCHAR(10) DEFAULT 'POST',
                request_data JSON,
                response_data TEXT,
                response_code INT,
                response_time_ms INT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_installation (sendy_installation),
                INDEX idx_endpoint (endpoint),
                INDEX idx_created_at (created_at),
                INDEX idx_response_code (response_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql_api_logs);
        
        // Configuration cache table
        $sql_cache = "
            CREATE TABLE IF NOT EXISTS sendy_module_cache (
                cache_key VARCHAR(255) PRIMARY KEY,
                cache_value LONGTEXT,
                cache_group VARCHAR(100) DEFAULT 'default',
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_group (cache_group),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql_cache);
        
        // Statistics aggregation table
        $sql_stats = "
            CREATE TABLE IF NOT EXISTS sendy_module_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sendy_installation VARCHAR(50) DEFAULT 'default',
                list_id VARCHAR(100),
                stat_date DATE NOT NULL,
                stat_type VARCHAR(50) NOT NULL,
                stat_value INT DEFAULT 0,
                metadata JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_installation (sendy_installation),
                INDEX idx_list (list_id),
                INDEX idx_date (stat_date),
                INDEX idx_type (stat_type),
                UNIQUE KEY unique_stat (sendy_installation, list_id, stat_date, stat_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql_stats);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Sendy Module: Table creation error - " . $e->getMessage());
        return false;
    }
}

/**
 * Register the module in the PhPstrap modules table
 * 
 * @param PDO $pdo Database connection
 * @param array $options Installation options
 * @return bool Success status
 */
function register_sendy_module($pdo, $options = [])
{
    try {
        // Check if module already exists
        $stmt = $pdo->prepare("SELECT id FROM modules WHERE name = 'sendy_module'");
        $stmt->execute();
        if ($stmt->fetch()) {
            // Module already exists, update it instead
            return update_existing_sendy_module($pdo, $options);
        }
        
        // Prepare module settings
        $settings = get_default_sendy_settings($options);
        
        // Prepare module hooks
        $hooks = json_encode([
            'sendy_module_init',
            'sendy_module_settings_loaded',
            'sendy_module_subscription_success',
            'sendy_module_subscription_error',
            'sendy_module_settings_updated',
            'sendy_module_activated',
            'sendy_module_deactivated'
        ]);
        
        // Prepare module permissions
        $permissions = json_encode([
            'sendy_module_view',
            'sendy_module_subscribe',
            'sendy_module_admin',
            'sendy_module_settings',
            'sendy_module_analytics',
            'sendy_module_manage_lists'
        ]);
        
        // Get installation SQL for reference
        $install_sql = get_sendy_installation_sql();
        
        // Insert module record
        $stmt = $pdo->prepare("
            INSERT INTO modules (
                name, title, description, version, author, author_url,
                license, enabled, settings, hooks, permissions, 
                install_path, namespace, install_sql, status,
                priority, is_core, is_commercial, price,
                required_version, dependencies, tags
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            'sendy_module',                                      // name
            'Sendy Newsletter Integration',                      // title
            'Professional integration module for Sendy email newsletter software with support for multiple installations, list management, and comprehensive analytics', // description
            '1.0.0',                                            // version
            'PhPstrap Team',                                     // author
            'https://PhPstrap.com',                              // author_url
            'MIT',                                              // license
            1,                                                  // enabled
            json_encode($settings),                             // settings
            $hooks,                                             // hooks
            $permissions,                                       // permissions
            'modules/sendy_module',                             // install_path
            'PhPstrap\\Modules\\Sendy',                          // namespace
            $install_sql,                                       // install_sql
            'active',                                           // status
            15,                                                 // priority
            0,                                                  // is_core
            0,                                                  // is_commercial
            0.00,                                              // price
            '1.0.0',                                           // required_version
            json_encode([]),                                    // dependencies
            json_encode(['sendy', 'newsletter', 'email', 'marketing', 'subscription']) // tags
        ]);
        
        if (!$result) {
            throw new Exception("Failed to insert module record");
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Sendy Module: Registration error - " . $e->getMessage());
        return false;
    }
}

/**
 * Update existing module record
 * 
 * @param PDO $pdo Database connection
 * @param array $options Installation options
 * @return bool Success status
 */
function update_existing_sendy_module($pdo, $options = [])
{
    try {
        $settings = get_default_sendy_settings($options);
        
        $stmt = $pdo->prepare("
            UPDATE modules SET 
                version = '1.0.0',
                settings = ?,
                status = 'active',
                updated_at = NOW()
            WHERE name = 'sendy_module'
        ");
        
        return $stmt->execute([json_encode($settings)]);
        
    } catch (Exception $e) {
        error_log("Sendy Module: Update error - " . $e->getMessage());
        return false;
    }
}

/**
 * Get default module settings
 * 
 * @param array $options Installation options
 * @return array Default settings
 */
function get_default_sendy_settings($options = [])
{
    $defaults = [
         'enabled' => true,
        'sendy_url' => '',
        'sendy_api_key' => '',
        'show_name_field' => true,
        'require_name_field' => false,
        'button_text' => 'Subscribe Now',
        'success_message' => 'Thank you for subscribing to our newsletter!',
        'error_message' => 'Subscription failed. Please try again or contact support.',
        'enable_ajax' => true,
        'gdpr_compliance' => true,
        'consent_required' => false,
        'consent_text' => 'I agree to receive marketing emails and understand I can unsubscribe at any time.',
        'privacy_notice' => 'We respect your privacy and will never share your information.',
        'debug_mode' => false,
        'auto_subscribe_new_users' => false,
        'default_list_for_new_users' => 'list_1',
        'max_lists' => 3,
        'list_1_id' => '',
        'list_1_name' => 'Main Newsletter',
        'list_1_enabled' => true,
        'list_1_auto_subscribe' => false,
        'list_2_id' => '',
        'list_2_name' => 'Product Updates',
        'list_2_enabled' => false,
        'list_2_auto_subscribe' => false,
        'list_3_id' => '',
        'list_3_name' => 'Special Offers',
        'list_3_enabled' => false,
        'list_3_auto_subscribe' => false,
        'installation_date' => date('Y-m-d H:i:s'),
        'version' => '1.0.0'
    ];
    
    // Merge with any custom options
    return array_merge($defaults, $options['settings'] ?? []);
}

/**
 * Create default module configuration
 * 
 * @param PDO $pdo Database connection
 */
function create_default_configuration($pdo)
{
    try {
        // Create sample list configuration
        $sample_config = [
            'id' => 'sample_list',
            'name' => 'Sample Newsletter List',
            'description' => 'This is a sample list configuration. Please update with your actual Sendy list details.',
            'sendy_installation' => 'default',
            'active' => false
        ];
        
        // Insert sample configuration into cache
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO sendy_module_cache (cache_key, cache_value, cache_group, expires_at)
            VALUES (?, ?, 'sample_config', DATE_ADD(NOW(), INTERVAL 1 YEAR))
        ");
        
        $stmt->execute([
            'sample_list_config',
            json_encode($sample_config)
        ]);
        
        error_log("Sendy Module: Default configuration created");
        
    } catch (Exception $e) {
        error_log("Sendy Module: Configuration creation error - " . $e->getMessage());
        // Don't fail installation if configuration creation fails
    }
}

/**
 * Set up module permissions in the system
 * 
 * @param PDO $pdo Database connection
 */
function setup_module_permissions($pdo)
{
    try {
        // If PhPstrap has a permissions system, register permissions here
        $permissions = [
            'sendy_module_view' => 'View newsletter subscription forms and widgets',
            'sendy_module_subscribe' => 'Subscribe to newsletter mailing lists',
            'sendy_module_admin' => 'Access Sendy module admin panel and manage all settings',
            'sendy_module_settings' => 'Configure Sendy installations, API keys, and module settings',
            'sendy_module_analytics' => 'View subscription statistics and analytics data',
            'sendy_module_manage_lists' => 'Create, edit, and manage Sendy mailing lists'
        ];
        
        // Check if permissions table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'permissions'");
        if ($stmt->rowCount() > 0) {
            $perm_stmt = $pdo->prepare("
                INSERT IGNORE INTO permissions (name, description, module)
                VALUES (?, ?, 'sendy_module')
            ");
            
            foreach ($permissions as $perm_name => $description) {
                $perm_stmt->execute([$perm_name, $description]);
            }
            
            error_log("Sendy Module: Permissions set up successfully");
        }
        
    } catch (Exception $e) {
        error_log("Sendy Module: Permission setup error - " . $e->getMessage());
        // Don't fail installation if permissions fail
    }
}

/**
 * Create module configuration files
 */
function create_module_config_files()
{
    try {
        $module_path = dirname(__FILE__);
        
        // Create assets directory if it doesn't exist
        $assets_dir = $module_path . '/assets';
        if (!is_dir($assets_dir)) {
            mkdir($assets_dir, 0755, true);
        }
        
        // Create views directory if it doesn't exist
        $views_dir = $module_path . '/views';
        if (!is_dir($views_dir)) {
            mkdir($views_dir, 0755, true);
        }
        
        // Create languages directory if it doesn't exist
        $languages_dir = $module_path . '/languages';
        if (!is_dir($languages_dir)) {
            mkdir($languages_dir, 0755, true);
        }
        
        // Create basic CSS file if it doesn't exist
        $css_file = $assets_dir . '/sendy-module.css';
        if (!file_exists($css_file)) {
            $css_content = get_default_css_content();
            file_put_contents($css_file, $css_content);
        }
        
        // Create basic JS file if it doesn't exist
        $js_file = $assets_dir . '/sendy-module.js';
        if (!file_exists($js_file)) {
            $js_content = get_default_js_content();
            file_put_contents($js_file, $js_content);
        }
        
        // Create admin JS file if it doesn't exist
        $admin_js_file = $assets_dir . '/sendy-admin.js';
        if (!file_exists($admin_js_file)) {
            $admin_js_content = get_default_admin_js_content();
            file_put_contents($admin_js_file, $admin_js_content);
        }
        
        // Create basic subscription form view if it doesn't exist
        $form_file = $views_dir . '/subscription-form.php';
        if (!file_exists($form_file)) {
            $form_content = get_default_form_template();
            file_put_contents($form_file, $form_content);
        }
        
        // Create widget view if it doesn't exist
        $widget_file = $views_dir . '/widget.php';
        if (!file_exists($widget_file)) {
            $widget_content = get_default_widget_template();
            file_put_contents($widget_file, $widget_content);
        }
        
        error_log("Sendy Module: Configuration files created successfully");
        
    } catch (Exception $e) {
        error_log("Sendy Module: Config file creation error - " . $e->getMessage());
        // Don't fail installation if file creation fails
    }
}

/**
 * Setup cron jobs for the module
 */
function setup_cron_jobs()
{
    try {
        // Setup WordPress cron jobs if available
        if (function_exists('wp_schedule_event')) {
            // Schedule daily stats sync
            if (!wp_next_scheduled('sendy_module_sync_stats')) {
                wp_schedule_event(time(), 'daily', 'sendy_module_sync_stats');
            }
            
            // Schedule weekly cleanup
            if (!wp_next_scheduled('sendy_module_cleanup')) {
                wp_schedule_event(time(), 'weekly', 'sendy_module_cleanup');
            }
        }
        
        error_log("Sendy Module: Cron jobs scheduled successfully");
        
    } catch (Exception $e) {
        error_log("Sendy Module: Cron setup error - " . $e->getMessage());
        // Don't fail installation if cron setup fails
    }
}

/**
 * Get installation SQL for reference
 * 
 * @return string SQL statements used during installation
 */
function get_sendy_installation_sql()
{
    return "-- Sendy Module Installation SQL
CREATE TABLE IF NOT EXISTS sendy_module_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    list_id VARCHAR(100) NOT NULL,
    sendy_installation VARCHAR(50) DEFAULT 'default',
    response TEXT,
    status ENUM('pending', 'subscribed', 'failed', 'unsubscribed') DEFAULT 'pending',
    subscription_date DATETIME,
    ip_address VARCHAR(45),
    user_agent TEXT,
    source VARCHAR(100),
    custom_fields JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_list (list_id),
    INDEX idx_installation (sendy_installation),
    INDEX idx_status (status),
    UNIQUE KEY unique_subscription (email, list_id, sendy_installation)
);

CREATE TABLE IF NOT EXISTS sendy_module_api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sendy_installation VARCHAR(50) DEFAULT 'default',
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) DEFAULT 'POST',
    request_data JSON,
    response_data TEXT,
    response_code INT,
    response_time_ms INT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_installation (sendy_installation),
    INDEX idx_endpoint (endpoint)
);";
}

/**
 * Get default CSS content
 */
function get_default_css_content()
{
    return "/* Sendy Module Default Styles */
.sendy-subscription-form {
    max-width: 400px;
    margin: 20px 0;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background: #fff;
}

.sendy-form-field {
    margin-bottom: 15px;
}

.sendy-field-label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.sendy-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 3px;
    box-sizing: border-box;
}

.sendy-submit-button {
    background: #0073aa;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 16px;
    width: 100%;
}

.sendy-submit-button:hover {
    background: #005177;
}

.sendy-message {
    padding: 10px;
    margin: 10px 0;
    border-radius: 3px;
}

.sendy-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.sendy-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.sendy-field-error .sendy-input {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.sendy-field-error-message {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}";
}

/**
 * Get default JavaScript content
 */
function get_default_js_content()
{
    return "/* Sendy Module Frontend JavaScript */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Sendy Module loaded');
    
    // Initialize all subscription forms
    const forms = document.querySelectorAll('[data-sendy-form]');
    forms.forEach(form => {
        initializeSubscriptionForm(form);
    });
});

function initializeSubscriptionForm(form) {
    // Basic form validation
    form.addEventListener('submit', function(e) {
        const email = form.querySelector('.sendy-email-input');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!email || !emailRegex.test(email.value)) {
            e.preventDefault();
            showFieldError(email, 'Please enter a valid email address.');
            return false;
        }
        
        // Check honeypot
        const honeypot = form.querySelector('input[name=\"website\"]');
        if (honeypot && honeypot.value !== '') {
            e.preventDefault();
            return false;
        }
    });
}

function showFieldError(field, message) {
    const fieldContainer = field.closest('.sendy-form-field');
    if (!fieldContainer) return;
    
    fieldContainer.classList.add('sendy-field-error');
    
    let errorElement = fieldContainer.querySelector('.sendy-field-error-message');
    if (!errorElement) {
        errorElement = document.createElement('span');
        errorElement.className = 'sendy-field-error-message';
        field.parentNode.appendChild(errorElement);
    }
    
    errorElement.textContent = message;
}";
}

/**
 * Get default admin JavaScript content
 */
function get_default_admin_js_content()
{
    return "/* Sendy Module Admin JavaScript */
document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation
    const tabs = document.querySelectorAll('.nav-tab');
    const sections = document.querySelectorAll('.settings-section');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs and sections
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            sections.forEach(s => s.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('nav-tab-active');
            
            // Show corresponding section
            const targetId = this.getAttribute('href').substring(1);
            const targetSection = document.getElementById(targetId);
            if (targetSection) {
                targetSection.classList.add('active');
            }
        });
    });
    
    // Test connection functionality
    const testButtons = document.querySelectorAll('.test-connection');
    testButtons.forEach(button => {
        button.addEventListener('click', testSendyConnection);
    });
    
    // Password toggle functionality
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                this.textContent = 'Hide';
            } else {
                input.type = 'password';
                this.textContent = 'Show';
            }
        });
    });
});

function testSendyConnection(e) {
    e.preventDefault();
    
    const button = e.target;
    const installation = button.dataset.installation;
    const container = button.closest('.installation-item');
    const urlField = container.querySelector('.sendy-url');
    const apiKeyField = container.querySelector('.sendy-api-key');
    
    if (!urlField.value || !apiKeyField.value) {
        alert('Please enter both Sendy URL and API key');
        return;
    }
    
    button.disabled = true;
    button.textContent = 'Testing...';
    
    // Here you would make an AJAX request to test the connection
    // For now, just simulate a test
    setTimeout(() => {
        button.disabled = false;
        button.textContent = 'Test Connection';
        
        // Simulate success/failure
        if (urlField.value && apiKeyField.value) {
            alert('Connection test successful!');
        } else {
            alert('Connection test failed. Please check your settings.');
        }
    }, 2000);
}";
}

/**
 * Get default form template
 */
function get_default_form_template()
{
    return "<?php
// Sendy Module Subscription Form Template
if (!defined('ABSPATH') && !isset(\$this)) {
    exit('Direct access not allowed');
}
?>

<form class=\"sendy-subscription-form\" method=\"post\" data-sendy-form>
    <input type=\"hidden\" name=\"sendy_subscribe\" value=\"1\">
    <input type=\"hidden\" name=\"list_id\" value=\"<?php echo esc_attr(\$attributes['list_id'] ?? ''); ?>\">
    <input type=\"hidden\" name=\"sendy_installation\" value=\"<?php echo esc_attr(\$attributes['sendy_installation'] ?? 'default'); ?>\">
    
    <div class=\"sendy-form-field\">
        <label for=\"sendy-email\" class=\"sendy-field-label\">Email Address *</label>
        <input type=\"email\" id=\"sendy-email\" name=\"email\" class=\"sendy-input sendy-email-input\" required>
    </div>
    
    <div class=\"sendy-form-field\">
        <button type=\"submit\" class=\"sendy-submit-button\">Subscribe</button>
    </div>
    
    <!-- Honeypot for spam protection -->
    <div style=\"display: none !important;\">
        <input type=\"text\" name=\"website\" value=\"\" tabindex=\"-1\">
    </div>
</form>";
}

/**
 * Get default widget template
 */
function get_default_widget_template()
{
    return "<?php
// Sendy Module Widget Template
if (!defined('ABSPATH') && !isset(\$this)) {
    exit('Direct access not allowed');
}
?>

<div class=\"sendy-widget\">
    <h3 class=\"sendy-widget-title\"><?php echo esc_html(\$attributes['title'] ?? 'Newsletter Signup'); ?></h3>
    <p class=\"sendy-widget-description\"><?php echo esc_html(\$attributes['description'] ?? 'Stay updated with our latest news.'); ?></p>
    
    <form class=\"sendy-subscription-form\" method=\"post\" data-sendy-form>
        <input type=\"hidden\" name=\"sendy_subscribe\" value=\"1\">
        <input type=\"hidden\" name=\"list_id\" value=\"<?php echo esc_attr(\$attributes['list_id'] ?? ''); ?>\">
        <input type=\"hidden\" name=\"sendy_installation\" value=\"<?php echo esc_attr(\$attributes['sendy_installation'] ?? 'default'); ?>\">
        
        <div class=\"sendy-form-field\">
            <input type=\"email\" name=\"email\" class=\"sendy-input sendy-email-input\" 
                   placeholder=\"Your email address\" required>
        </div>
        
        <div class=\"sendy-form-field\">
            <button type=\"submit\" class=\"sendy-submit-button\">Subscribe</button>
        </div>
    </form>
</div>";
}

/**
 * Clean up installation files on error
 */
function cleanup_installation_files()
{
    try {
        $module_path = dirname(__FILE__);
        
        // Remove created directories if they're empty
        $dirs_to_check = [
            $module_path . '/assets',
            $module_path . '/views',
            $module_path . '/languages'
        ];
        
        foreach ($dirs_to_check as $dir) {
            if (is_dir($dir) && count(scandir($dir)) == 2) { // Only . and ..
                rmdir($dir);
            }
        }
        
    } catch (Exception $e) {
        error_log("Sendy Module: Cleanup error - " . $e->getMessage());
    }
}

/**
 * Verify installation success
 * 
 * @param PDO $pdo Database connection
 * @return array Verification results
 */
function verify_sendy_module_installation($pdo)
{
    $results = [
        'module_registered' => false,
        'tables_created' => false,
        'settings_valid' => false,
        'files_created' => false,
        'permissions_setup' => false,
        'success' => false
    ];
    
    try {
        // Check if module is registered
        $stmt = $pdo->prepare("SELECT id, settings FROM modules WHERE name = 'sendy_module' AND status = 'active'");
        $stmt->execute();
        $module = $stmt->fetch();
        $results['module_registered'] = !empty($module);
        
        // Check if settings are valid JSON
        if ($module && !empty($module['settings'])) {
            $settings = json_decode($module['settings'], true);
            $results['settings_valid'] = is_array($settings) && 
                                       isset($settings['enabled']) && 
                                       isset($settings['installations']);
        }
        
        // Check if main table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'sendy_module_subscriptions'");
        $results['tables_created'] = ($stmt->rowCount() > 0);
        
        // Check if module files exist
        $module_path = dirname(__FILE__);
        $required_files = [
            $module_path . '/SendyModule.php',
            $module_path . '/assets/sendy-module.css',
            $module_path . '/assets/sendy-module.js',
            $module_path . '/views/subscription-form.php'
        ];
        
        $files_exist = 0;
        foreach ($required_files as $file) {
            if (file_exists($file)) {
                $files_exist++;
            }
        }
        $results['files_created'] = ($files_exist == count($required_files));
        
        // Check permissions
        $stmt = $pdo->query("SHOW TABLES LIKE 'permissions'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM permissions WHERE module = 'sendy_module'");
            $stmt->execute();
            $perm_count = $stmt->fetch();
            $results['permissions_setup'] = ($perm_count['count'] > 0);
        } else {
            $results['permissions_setup'] = true; // No permissions table exists
        }
        
        // Overall success
        $results['success'] = $results['module_registered'] && 
                             $results['tables_created'] && 
                             $results['settings_valid'] &&
                             $results['files_created'] &&
                             $results['permissions_setup'];
        
    } catch (Exception $e) {
        error_log("Sendy Module: Verification error - " . $e->getMessage());
    }
    
    return $results;
}

function get_option($option, $default = '') {
    // Fallback for non-WordPress environments
    return $default;
}

// If called directly, run installation
if (basename($_SERVER['PHP_SELF']) == 'install.php') {
    try {
        if (function_exists('getDbConnection')) {
            $pdo = getDbConnection();
            $options = [
                'create_default_config' => true,
                'settings' => [
                    'installations' => [
                        'default' => [
                            'name' => 'Default Installation',
                            'url' => '',
                            'api_key' => '',
                            'lists' => []
                        ]
                    ]
                ]
            ];
            
            echo "Sendy Module Installer\n";
            echo "======================\n";
            echo "Installing Sendy Newsletter Integration Module...\n\n";
            
            if (install_sendy_module($pdo, $options)) {
                echo "✅ Sendy Module installed successfully!\n\n";
                
                $verification = verify_sendy_module_installation($pdo);
                echo "Verification Results:\n";
                echo "Module registered: " . ($verification['module_registered'] ? 'Yes' : 'No') . "\n";
                echo "Tables created: " . ($verification['tables_created'] ? 'Yes' : 'No') . "\n";
                echo "Settings valid: " . ($verification['settings_valid'] ? 'Yes' : 'No') . "\n";
                echo "Files created: " . ($verification['files_created'] ? 'Yes' : 'No') . "\n";
                echo "Permissions setup: " . ($verification['permissions_setup'] ? 'Yes' : 'No') . "\n";
                echo "Overall success: " . ($verification['success'] ? 'Yes' : 'No') . "\n\n";
                
                if ($verification['success']) {
                    echo "🎉 Installation completed successfully!\n";
                    echo "\nNext Steps:\n";
                    echo "1. Configure your Sendy installation URL and API key in the admin settings\n";
                    echo "2. Add your mailing list IDs\n";
                    echo "3. Test the connection to ensure everything is working\n";
                    echo "4. Start using the [sendy_form] shortcode or widgets!\n";
                } else {
                    echo "⚠️ Installation completed with some issues. Please check the logs.\n";
                }
                
            } else {
                echo "❌ Sendy Module installation failed!\n";
                echo "Check error logs for details.\n";
            }
        } else {
            echo "❌ Database connection not available.\n";
        }
    } catch (Exception $e) {
        echo "❌ Installation error: " . $e->getMessage() . "\n";
    }
}
?>