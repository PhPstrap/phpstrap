<?php

namespace PhPstrap\Modules\Sendy;

use Exception;
use PDO;

class SendyModule
{
    const VERSION = '1.0.0';
    const MAX_LISTS = 10;
    
    private $settings;
    private $db;
    private $initialized = false;
    private $module_path;
    private static $instance = null;

    public function __construct($db = null)
    {
        $this->db = $db ?: $this->getDbConnection();
        $this->module_path = dirname(__FILE__);
        $this->loadSettings();
        
        // Create necessary tables
        $this->createTables();
        
        self::$instance = $this;
        
        $this->log("SendyModule constructed", 'info');
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create module tables
     */
    private function createTables()
    {
        try {
            // Main tables
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS sendy_module_subscriptions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    list_id VARCHAR(100) NOT NULL,
                    status VARCHAR(50) DEFAULT 'pending',
                    response TEXT,
                    subscription_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_user_id (user_id),
                    KEY idx_email (email),
                    KEY idx_list_id (list_id),
                    UNIQUE KEY unique_email_list (email, list_id)
                )
            ");

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS sendy_module_api_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    endpoint VARCHAR(255),
                    method VARCHAR(10) DEFAULT 'POST',
                    request_data TEXT,
                    response_data TEXT,
                    response_code INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_created (created_at)
                )
            ");

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS sendy_module_debug_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    level VARCHAR(20),
                    message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_created (created_at)
                )
            ");

            $this->db->exec("
                CREATE TABLE IF NOT EXISTS sendy_module_processed_users (
                    user_id INT PRIMARY KEY,
                    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

        } catch (Exception $e) {
            error_log("Sendy Module: Error creating tables - " . $e->getMessage());
        }
    }

    /**
     * Initialize module
     */
    public function init()
    {
        if ($this->initialized) {
            return;
        }

        $this->loadSettings();

        if (!$this->settings['enabled']) {
            $this->log("Module is disabled", 'info');
            return;
        }

        $this->initialized = true;
        $this->log("Module initialized successfully", 'info');
    }

    /**
     * Process new users that haven't been subscribed yet
     */
    public function processNewUsers()
    {
        if (!$this->settings['auto_subscribe_new_users']) {
            $this->log("Auto-subscribe is disabled", 'info');
            return 0;
        }

        if (empty($this->settings['sendy_url']) || empty($this->settings['sendy_api_key'])) {
            $this->log("Sendy not configured", 'error');
            return 0;
        }

        $processed_count = 0;

        try {
            // Find users that haven't been processed yet
            $stmt = $this->db->prepare("
                SELECT u.* 
                FROM users u
                LEFT JOIN sendy_module_processed_users p ON u.id = p.user_id
                WHERE p.user_id IS NULL
                AND u.verified = 1
                ORDER BY u.id ASC
                LIMIT 50
            ");
            $stmt->execute();
            $new_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($new_users) {
                $this->log("Found " . count($new_users) . " new users to process", 'info');
                
                foreach ($new_users as $user) {
                    $this->log("Processing user ID: {$user['id']} ({$user['email']})", 'info');
                    
                    // Subscribe the user
                    if ($this->autoSubscribeUser($user)) {
                        $processed_count++;
                    }
                    
                    // Mark as processed regardless of success to avoid repeated attempts
                    $this->markUserAsProcessed($user['id']);
                }
            } else {
                $this->log("No new users to process", 'info');
            }
        } catch (Exception $e) {
            $this->log("Error processing new users: " . $e->getMessage(), 'error');
        }

        return $processed_count;
    }

    /**
     * Process a specific user by ID
     */
    public function processUserById($user_id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $success = $this->autoSubscribeUser($user);
                $this->markUserAsProcessed($user_id);
                return $success;
            }
            return false;
        } catch (Exception $e) {
            $this->log("Error processing user by ID: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Auto-subscribe a user to all configured lists
     */
    public function autoSubscribeUser($user_data)
    {
        try {
            $email = $user_data['email'];
            $name = $user_data['name'] ?? '';
            $user_id = $user_data['id'] ?? 0;

            $this->log("Starting auto-subscription for: {$email}", 'info');

            $subscribed_count = 0;
            $total_lists = 0;

            // Subscribe to all lists marked for auto-subscribe
            for ($i = 1; $i <= self::MAX_LISTS; $i++) {
                $list_key = 'list_' . $i;
                
                if ($this->settings[$list_key . '_enabled'] && 
                    $this->settings[$list_key . '_auto_subscribe'] &&
                    !empty($this->settings[$list_key . '_id'])) {
                    
                    $total_lists++;
                    $list_id = $this->settings[$list_key . '_id'];
                    $list_name = $this->settings[$list_key . '_name'] ?? "List {$i}";
                    
                    $result = $this->subscribeUserToSendy($email, $name, $list_id);
                    
                    if ($result['success']) {
                        $subscribed_count++;
                        $this->log("✓ Subscribed to {$list_name}", 'success');
                        $this->recordSubscription($user_id, $email, $list_id, 'subscribed', $result['message']);
                    } else {
                        $this->log("✗ Failed to subscribe to {$list_name}: {$result['message']}", 'error');
                        $this->recordSubscription($user_id, $email, $list_id, 'failed', $result['message']);
                    }
                }
            }

            $this->log("Subscription complete: {$subscribed_count}/{$total_lists} successful", 'info');
            return $subscribed_count > 0;
            
        } catch (Exception $e) {
            $this->log("Auto-subscribe exception: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Subscribe user to Sendy via API
     */
    private function subscribeUserToSendy($email, $name, $list_id)
    {
        $sendy_url = rtrim($this->settings['sendy_url'], '/');
        $api_key = $this->settings['sendy_api_key'];

        $data = [
            'email' => $email,
            'name' => $name,
            'list' => $list_id,
            'api_key' => $api_key,
            'boolean' => 'true'
        ];

        // Make API request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sendy_url . '/subscribe');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For testing
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Log the API call
        $this->logApiCall($sendy_url . '/subscribe', $data, $response, $http_code);

        // Parse response
        if ($curl_error) {
            return ['success' => false, 'message' => 'CURL Error: ' . $curl_error];
        }

        $response = trim($response);
        
        if ($response === '1' || $response === 'true' || $response === 'Subscribed') {
            return ['success' => true, 'message' => 'Successfully subscribed'];
        } elseif (strpos($response, 'Already subscribed') !== false) {
            return ['success' => true, 'message' => 'Already subscribed'];
        } else {
            return ['success' => false, 'message' => $response ?: 'Unknown error (HTTP ' . $http_code . ')'];
        }
    }

    /**
     * Manual subscription method for testing
     */
    public function manuallySubscribeUser($email, $name = '')
    {
        if (empty($this->settings['sendy_url']) || empty($this->settings['sendy_api_key'])) {
            return ['success' => false, 'message' => 'Sendy URL or API key not configured'];
        }

        $results = [];
        $success_count = 0;
        $total_lists = 0;

        for ($i = 1; $i <= self::MAX_LISTS; $i++) {
            $list_key = 'list_' . $i;
            
            if ($this->settings[$list_key . '_enabled'] && 
                $this->settings[$list_key . '_auto_subscribe'] &&
                !empty($this->settings[$list_key . '_id'])) {
                
                $total_lists++;
                $list_id = $this->settings[$list_key . '_id'];
                $list_name = $this->settings[$list_key . '_name'] ?? "List {$i}";
                
                $result = $this->subscribeUserToSendy($email, $name, $list_id);
                
                if ($result['success']) {
                    $success_count++;
                    $results[] = "✓ {$list_name}: " . $result['message'];
                } else {
                    $results[] = "✗ {$list_name}: " . $result['message'];
                }
            }
        }

        if ($total_lists === 0) {
            return [
                'success' => false,
                'message' => 'No lists are configured for auto-subscription',
                'details' => ['Please enable at least one list and check "Auto-Subscribe"']
            ];
        }

        return [
            'success' => $success_count > 0,
            'message' => "Subscribed to {$success_count} out of {$total_lists} lists",
            'details' => $results
        ];
    }

    /**
     * Mark user as processed
     */
    private function markUserAsProcessed($user_id)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO sendy_module_processed_users (user_id, processed_at)
                VALUES (?, NOW())
            ");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            $this->log("Error marking user as processed: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Record subscription attempt
     */
    private function recordSubscription($user_id, $email, $list_id, $status, $response = '')
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sendy_module_subscriptions 
                (user_id, email, list_id, status, response, subscription_date)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                response = VALUES(response),
                updated_at = NOW()
            ");
            
            $stmt->execute([$user_id, $email, $list_id, $status, $response]);
        } catch (Exception $e) {
            $this->log("Error recording subscription: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Get unprocessed users count
     */
    public function getUnprocessedUsersCount()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM users u
                LEFT JOIN sendy_module_processed_users p ON u.id = p.user_id
                WHERE p.user_id IS NULL
                AND u.verified = 1
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return isset($result['count']) ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            $this->log("Error counting unprocessed users: " . $e->getMessage(), 'error');
            return 0;
        }
    }

    /**
     * Reset processed users (for testing)
     */
    public function resetProcessedUsers()
    {
        try {
            $this->db->exec("TRUNCATE TABLE sendy_module_processed_users");
            $this->log("Reset processed users table", 'info');
            return true;
        } catch (Exception $e) {
            $this->log("Error resetting processed users: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get recent API logs
     */
    public function getRecentApiLogs($limit = 10)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM sendy_module_api_logs 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get recent debug logs
     */
    public function getRecentDebugLogs($limit = 20)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM sendy_module_debug_log 
                ORDER BY id DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Clear logs
     */
    public function clearLogs()
    {
        try {
            $this->db->exec("TRUNCATE TABLE sendy_module_api_logs");
            $this->db->exec("TRUNCATE TABLE sendy_module_debug_log");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Log API calls
     */
    private function logApiCall($url, $data, $response, $http_code)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sendy_module_api_logs 
                (endpoint, request_data, response_data, response_code)
                VALUES (?, ?, ?, ?)
            ");
            
            // Remove API key from logged data
            $log_data = $data;
            $log_data['api_key'] = 'HIDDEN';
            
            $stmt->execute([
                $url,
                json_encode($log_data),
                $response,
                $http_code
            ]);
            
            // Keep only last 1000 entries
            $this->db->exec("
                DELETE FROM sendy_module_api_logs 
                WHERE id < (
                    SELECT MIN(id) FROM (
                        SELECT id FROM sendy_module_api_logs 
                        ORDER BY id DESC 
                        LIMIT 1000
                    ) tmp
                )
            ");
        } catch (Exception $e) {
            // Silently fail logging
        }
    }

    /**
     * Settings management
     */
    public function loadSettings()
    {
        $default_settings = $this->getDefaultSettings();

        try {
            $stmt = $this->db->prepare("SELECT settings FROM modules WHERE name = 'sendy_module'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['settings']) && !empty($result['settings'])) {
                $stored_settings = json_decode($result['settings'], true);
                if (is_array($stored_settings)) {
                    $this->settings = array_merge($default_settings, $stored_settings);
                } else {
                    $this->settings = $default_settings;
                }
            } else {
                $this->settings = $default_settings;
                $this->registerModule();
            }
        } catch (Exception $e) {
            $this->settings = $default_settings;
            $this->log("Error loading settings: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Register module in database
     */
    private function registerModule()
{
    try {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO modules 
            (name, title, description, version, author, enabled, settings, install_path, namespace, admin_menu)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $admin_menu = json_encode([
            'title' => 'Sendy Auto-Subscribe',
            'icon' => 'fas fa-envelope',
            'url' => '/modules/sendy_subscriber/views/sendy-settings.php',
            'position' => 50
        ]);
        
        $stmt->execute([
            'sendy_module',
            'Sendy Auto-Subscribe',
            'Automatically subscribe new users to Sendy mailing lists',
            self::VERSION,
            'PhPstrap Team',
            1,
            json_encode($this->getDefaultSettings()),
            'modules/sendy_subscriber',
            'PhPstrap\\Modules\\Sendy',
            $admin_menu
        ]);
        
        $this->log("Module registered successfully", 'info');
    } catch (Exception $e) {
        $this->log("Error registering module: " . $e->getMessage(), 'error');
    }
}

    private function getDefaultSettings()
    {
        $settings = [
            'enabled' => true,
            'sendy_url' => '',
            'sendy_api_key' => '',
            'auto_subscribe_new_users' => false,
            'debug_mode' => false,
            'check_key' => $this->generateRandomKey()
        ];

        for ($i = 1; $i <= self::MAX_LISTS; $i++) {
            $settings['list_' . $i . '_id'] = '';
            $settings['list_' . $i . '_name'] = "List {$i}";
            $settings['list_' . $i . '_enabled'] = false;
            $settings['list_' . $i . '_auto_subscribe'] = false;
        }

        return $settings;
    }

    /**
     * Generate a random key for check.php access
     */
    private function generateRandomKey()
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception $e) {
            // Fallback for older PHP versions
            return md5(uniqid(rand(), true));
        }
    }

    public function saveSettings($new_settings)
    {
        $this->settings = array_merge($this->settings, $new_settings);

        try {
            $stmt = $this->db->prepare("
                UPDATE modules 
                SET settings = ?, updated_at = NOW() 
                WHERE name = 'sendy_module'
            ");
            $success = $stmt->execute([json_encode($this->settings)]);
            
            if ($success) {
                $this->log("Settings saved successfully", 'info');
            }
            
            return $success;
        } catch (Exception $e) {
            $this->log("Settings save error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    public function sanitizeSettings($settings)
    {
        $sanitized = [];

        // Boolean fields
        $boolean_fields = ['enabled', 'auto_subscribe_new_users', 'debug_mode'];
        for ($i = 1; $i <= self::MAX_LISTS; $i++) {
            $boolean_fields[] = 'list_' . $i . '_enabled';
            $boolean_fields[] = 'list_' . $i . '_auto_subscribe';
        }
        
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($settings[$field]) && $settings[$field] == '1';
        }

        // Text fields
        $sanitized['sendy_url'] = filter_var($settings['sendy_url'] ?? '', FILTER_SANITIZE_URL);
        $sanitized['sendy_api_key'] = trim($settings['sendy_api_key'] ?? '');
        $sanitized['check_key'] = isset($settings['check_key']) ? trim($settings['check_key']) : $this->generateRandomKey();

        // List fields
        for ($i = 1; $i <= self::MAX_LISTS; $i++) {
            $sanitized['list_' . $i . '_id'] = trim($settings['list_' . $i . '_id'] ?? '');
            $sanitized['list_' . $i . '_name'] = trim($settings['list_' . $i . '_name'] ?? '');
        }

        return $sanitized;
    }

    /**
     * Get settings
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Render admin settings page
     */
    public function renderAdminSettings()
    {
        include $this->getViewPath('admin-settings.php');
    }

    private function getViewPath($filename)
    {
        return $this->module_path . '/views/' . $filename;
    }

    /**
     * Get database connection - handles define() style config
     */
    private function getDbConnection()
    {
        global $db;
        if ($db instanceof PDO) {
            return $db;
        }
        
        // Try to load config file
        $config_paths = [
            __DIR__ . '/../../config/database.php',
            dirname(__DIR__, 2) . '/config/database.php',
            $_SERVER['DOCUMENT_ROOT'] . '/config/database.php',
        ];
        
        foreach ($config_paths as $path) {
            if (file_exists($path)) {
                // Include the file which should define the constants
                include_once $path;
                
                // Check if constants are defined
                if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
                    try {
                        $dsn = sprintf(
                            "mysql:host=%s;dbname=%s;charset=%s",
                            DB_HOST,
                            DB_NAME,
                            defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
                        );
                        
                        if (defined('DB_PORT') && DB_PORT != 3306) {
                            $dsn .= ';port=' . DB_PORT;
                        }
                        
                        $pdo = new PDO($dsn, DB_USER, DB_PASS);
                        
                        // Set PDO attributes
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                        
                        return $pdo;
                    } catch (Exception $e) {
                        error_log("Sendy Module: Database connection error - " . $e->getMessage());
                    }
                    break; // Stop looking once we found the config
                }
            }
        }
        
        error_log("Sendy Module: Could not establish database connection");
        return null;
    }

    private function log($message, $level = 'info')
    {
        if ($this->settings['debug_mode'] ?? false) {
            error_log("[Sendy Module {$level}] {$message}");
            
            // Also log to database if available
            if ($this->db) {
                try {
                    $stmt = $this->db->prepare("
                        INSERT INTO sendy_module_debug_log (level, message)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$level, $message]);
                    
                    // Keep only last 1000 entries
                    $this->db->exec("
                        DELETE FROM sendy_module_debug_log 
                        WHERE id < (
                            SELECT MIN(id) FROM (
                                SELECT id FROM sendy_module_debug_log 
                                ORDER BY id DESC 
                                LIMIT 1000
                            ) tmp
                        )
                    ");
                } catch (Exception $e) {
                    // Ignore logging errors
                }
            }
        }
    }
}