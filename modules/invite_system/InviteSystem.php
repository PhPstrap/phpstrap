<?php
/**
 * PhPstrap Invite System Module
 * File: /modules/invite_system/InviteSystem.php
 */

namespace PhPstrap\Modules\InviteSystem;

class InviteSystem {
    
    private $pdo;
    private $config;
    private $version = '1.0.0';
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: $this->getDbConnection();
        $this->config = $this->loadConfig();
    }
    
    /**
     * Initialize the module
     */
    public function init() {
        // Register hooks if your system supports them
        if (function_exists('add_action')) {
            add_action('user_registration', [$this, 'handleUserRegistration'], 10, 2);
            add_action('daily_cleanup', [$this, 'cleanupExpiredInvites']);
        }
        
        return true;
    }
    
    /**
     * Install the module (create tables and settings)
     */
    public function install() {
        try {
            $this->pdo->beginTransaction();
            
            // Create tables
            $this->createTables();
            
            // Add settings
            $this->addSettings();
            
            // Add module entry to modules table
            $this->registerModule();
            
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'Invite System module installed successfully.'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            return ['success' => false, 'message' => 'Installation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Uninstall the module (remove tables and settings)
     */
    public function uninstall() {
        try {
            $this->pdo->beginTransaction();
            
            // Remove tables (with confirmation)
            $this->dropTables();
            
            // Remove settings
            $this->removeSettings();
            
            // Remove module entry
            $this->unregisterModule();
            
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'Invite System module uninstalled successfully.'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            return ['success' => false, 'message' => 'Uninstallation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update the module to a newer version
     */
    public function update($from_version) {
        try {
            $this->pdo->beginTransaction();
            
            // Run version-specific updates
            if (version_compare($from_version, '1.0.0', '<')) {
                $this->updateTo100();
            }
            
            // Update module version
            $this->updateModuleVersion();
            
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'Invite System module updated successfully.'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Check if module is installed
     */
    public function isInstalled() {
        try {
            // Check if main table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'invites'");
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get module information
     */
    public function getModuleInfo() {
        return [
            'name' => 'Invite System',
            'description' => 'Complete invitation system for user registration management',
            'version' => $this->version,
            'author' => 'PhPstrap',
            'requires' => ['php' => '7.4', 'mysql' => '5.7'],
            'settings_page' => '/admin/invites.php'
        ];
    }
    
    /* =============================================================================
       Database Operations
       ========================================================================== */
    
    /**
     * Create all required tables
     */
    private function createTables() {
        $tables = [
            'invites' => "
                CREATE TABLE IF NOT EXISTS `invites` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `user_id` int NOT NULL COMMENT 'User who created the invite',
                  `max_uses` int NOT NULL DEFAULT 1 COMMENT 'Maximum number of times this invite can be used (0 = unlimited)',
                  `used_count` int NOT NULL DEFAULT 0 COMMENT 'Number of times this invite has been used',
                  `expires_at` datetime NOT NULL COMMENT 'When this invite expires',
                  `status` enum('active','inactive','expired','disabled') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
                  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional description/note for this invite',
                  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `code` (`code`),
                  KEY `user_id` (`user_id`),
                  KEY `status` (`status`),
                  KEY `expires_at` (`expires_at`),
                  CONSTRAINT `invites_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'invite_usages' => "
                CREATE TABLE IF NOT EXISTS `invite_usages` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `invite_id` int NOT NULL,
                  `user_id` int NOT NULL COMMENT 'User who used the invite',
                  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                  `user_agent` text COLLATE utf8mb4_unicode_ci,
                  `used_at` datetime DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `invite_id` (`invite_id`),
                  KEY `user_id` (`user_id`),
                  KEY `used_at` (`used_at`),
                  CONSTRAINT `invite_usages_invite_fk` FOREIGN KEY (`invite_id`) REFERENCES `invites` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `invite_usages_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            
            'user_invite_quotas' => "
                CREATE TABLE IF NOT EXISTS `user_invite_quotas` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `user_id` int NOT NULL,
                  `period_start` date NOT NULL COMMENT 'Start of the quota period (weekly/monthly)',
                  `period_type` enum('weekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weekly',
                  `invites_created` int NOT NULL DEFAULT 0 COMMENT 'Number of invites created in this period',
                  `quota_limit` int NOT NULL DEFAULT 1 COMMENT 'Maximum invites allowed in this period',
                  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `user_period` (`user_id`,`period_start`,`period_type`),
                  KEY `user_id` (`user_id`),
                  KEY `period_start` (`period_start`),
                  CONSTRAINT `user_invite_quotas_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        ];
        
        foreach ($tables as $name => $sql) {
            $this->pdo->exec($sql);
        }
        
        // Create indexes
        $this->createIndexes();
        
        // Create stored procedures
        $this->createStoredProcedures();
        
        // Create views
        $this->createViews();
    }
    
    /**
     * Create indexes for better performance
     */
    private function createIndexes() {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS `idx_invites_active` ON `invites` (`status`, `expires_at`)",
            "CREATE INDEX IF NOT EXISTS `idx_invite_usages_recent` ON `invite_usages` (`used_at` DESC)",
            "CREATE INDEX IF NOT EXISTS `idx_user_quotas_current` ON `user_invite_quotas` (`user_id`, `period_type`, `period_start` DESC)"
        ];
        
        foreach ($indexes as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (Exception $e) {
                // Index might already exist, continue
                error_log("Index creation warning: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Create stored procedures
     */
    private function createStoredProcedures() {
        // Generate invite code procedure
        $this->pdo->exec("DROP PROCEDURE IF EXISTS `GenerateInviteCode`");
        $this->pdo->exec("
            CREATE PROCEDURE `GenerateInviteCode`(
                IN p_user_id INT,
                IN p_max_uses INT,
                IN p_expiry_days INT,
                IN p_description VARCHAR(255),
                OUT p_invite_code VARCHAR(50)
            )
            BEGIN
                DECLARE v_code VARCHAR(50);
                DECLARE v_exists INT DEFAULT 1;
                DECLARE v_attempts INT DEFAULT 0;
                
                -- Generate unique code
                WHILE v_exists > 0 AND v_attempts < 10 DO
                    SET v_code = UPPER(CONCAT(
                        SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
                        SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
                        SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
                        SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
                        SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
                        SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
                        SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1),
                        SUBSTRING('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', FLOOR(1 + RAND() * 36), 1)
                    ));
                    
                    SELECT COUNT(*) INTO v_exists FROM `invites` WHERE `code` = v_code;
                    SET v_attempts = v_attempts + 1;
                END WHILE;
                
                -- Insert the invite
                IF v_exists = 0 THEN
                    INSERT INTO `invites` (`code`, `user_id`, `max_uses`, `expires_at`, `description`)
                    VALUES (v_code, p_user_id, p_max_uses, DATE_ADD(NOW(), INTERVAL p_expiry_days DAY), p_description);
                    
                    SET p_invite_code = v_code;
                ELSE
                    SET p_invite_code = NULL;
                END IF;
            END
        ");
        
        // Validate invite code procedure
        $this->pdo->exec("DROP PROCEDURE IF EXISTS `ValidateInviteCode`");
        $this->pdo->exec("
            CREATE PROCEDURE `ValidateInviteCode`(
                IN p_invite_code VARCHAR(50),
                OUT p_valid BOOLEAN,
                OUT p_invite_id INT,
                OUT p_message VARCHAR(255)
            )
            BEGIN
                DECLARE v_count INT DEFAULT 0;
                DECLARE v_max_uses INT DEFAULT 0;
                DECLARE v_used_count INT DEFAULT 0;
                DECLARE v_expires_at DATETIME;
                DECLARE v_status VARCHAR(20);
                
                -- Check if invite exists and get details
                SELECT COUNT(*), MAX(id), MAX(max_uses), MAX(used_count), MAX(expires_at), MAX(status)
                INTO v_count, p_invite_id, v_max_uses, v_used_count, v_expires_at, v_status
                FROM `invites` 
                WHERE `code` = p_invite_code;
                
                IF v_count = 0 THEN
                    SET p_valid = FALSE;
                    SET p_message = 'Invalid invite code';
                ELSEIF v_status != 'active' THEN
                    SET p_valid = FALSE;
                    SET p_message = 'Invite code is disabled';
                ELSEIF v_expires_at < NOW() THEN
                    SET p_valid = FALSE;
                    SET p_message = 'Invite code has expired';
                ELSEIF v_max_uses > 0 AND v_used_count >= v_max_uses THEN
                    SET p_valid = FALSE;
                    SET p_message = 'Invite code has reached its usage limit';
                ELSE
                    SET p_valid = TRUE;
                    SET p_message = 'Valid invite code';
                END IF;
            END
        ");
        
        // Cleanup procedure
        $this->pdo->exec("DROP PROCEDURE IF EXISTS `CleanupExpiredInvites`");
        $this->pdo->exec("
            CREATE PROCEDURE `CleanupExpiredInvites`()
            BEGIN
                -- Update status of expired invites
                UPDATE `invites` 
                SET `status` = 'expired', `updated_at` = NOW()
                WHERE `expires_at` < NOW() AND `status` = 'active';
            END
        ");
    }
    
    /**
     * Create useful views
     */
    private function createViews() {
        // Invite statistics view
        $this->pdo->exec("DROP VIEW IF EXISTS `invite_stats`");
        $this->pdo->exec("
            CREATE VIEW `invite_stats` AS
            SELECT 
                i.id,
                i.code,
                i.user_id,
                u.name as creator_name,
                u.email as creator_email,
                i.max_uses,
                i.used_count,
                i.expires_at,
                i.status,
                i.description,
                i.created_at,
                CASE 
                    WHEN i.expires_at < NOW() THEN 'expired'
                    WHEN i.max_uses > 0 AND i.used_count >= i.max_uses THEN 'exhausted'
                    WHEN i.status = 'active' THEN 'available'
                    ELSE i.status 
                END as effective_status,
                CASE 
                    WHEN i.max_uses = 0 THEN 'unlimited'
                    ELSE CONCAT(i.used_count, '/', i.max_uses)
                END as usage_display
            FROM `invites` i
            LEFT JOIN `users` u ON i.user_id = u.id
            ORDER BY i.created_at DESC
        ");
        
        // Recent usage view
        $this->pdo->exec("DROP VIEW IF EXISTS `recent_invite_usage`");
        $this->pdo->exec("
            CREATE VIEW `recent_invite_usage` AS
            SELECT 
                iu.id,
                iu.invite_id,
                i.code as invite_code,
                iu.user_id,
                u.name as user_name,
                u.email as user_email,
                creator.name as invite_creator,
                iu.ip_address,
                iu.used_at
            FROM `invite_usages` iu
            JOIN `invites` i ON iu.invite_id = i.id
            JOIN `users` u ON iu.user_id = u.id
            LEFT JOIN `users` creator ON i.user_id = creator.id
            ORDER BY iu.used_at DESC
        ");
    }
    
    /**
     * Add module settings
     */
    private function addSettings() {
        $settings = [
            ['invite_only_mode', '0', 'boolean', 'Require invite codes for new registrations', 'invites', 1, 0, '0', 10],
            ['invites_per_week', '1', 'integer', 'Number of invite codes each user can generate per week', 'invites', 1, 0, '1', 20],
            ['invite_expiry_days', '30', 'integer', 'Number of days until invite codes expire', 'invites', 1, 0, '30', 30],
            ['invite_auto_approve', '1', 'boolean', 'Auto-approve users who register with valid invites', 'invites', 1, 0, '1', 40],
            ['invite_max_uses', '1', 'integer', 'Default maximum uses per invite code', 'invites', 1, 0, '1', 50],
            ['invite_allow_user_creation', '1', 'boolean', 'Allow regular users to create their own invite codes', 'invites', 1, 0, '1', 60],
            ['invite_admin_only', '0', 'boolean', 'Only allow administrators to create invite codes', 'invites', 1, 0, '0', 70],
            ['verification_bonus_credits', '5.00', 'string', 'Bonus credits awarded for email verification', 'users', 1, 0, '5.00', 110],
            ['auto_login_after_verification', '0', 'boolean', 'Automatically log in users after email verification', 'users', 1, 0, '0', 120],
            ['verification_token_expiry', '24', 'integer', 'Hours until verification tokens expire', 'email', 1, 0, '24', 80],
            ['verification_resend_rate_limit', '300', 'integer', 'Seconds between verification email resends', 'email', 1, 0, '300', 90]
        ];
        
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO `settings` 
            (`key`, `value`, `type`, `description`, `category`, `is_public`, `is_required`, `default_value`, `sort_order`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }
    }
    
    /**
     * Register module in modules table
     */
    private function registerModule() {
        // Create modules table if it doesn't exist
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `modules` (
              `id` int NOT NULL AUTO_INCREMENT,
              `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
              `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
              `enabled` tinyint(1) DEFAULT 1,
              `settings` json DEFAULT NULL,
              `installed_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Register this module
        $stmt = $this->pdo->prepare("
            INSERT INTO `modules` (`name`, `version`, `enabled`, `settings`) 
            VALUES ('invite_system', ?, 1, ?) 
            ON DUPLICATE KEY UPDATE 
            `version` = VALUES(`version`), 
            `updated_at` = NOW()
        ");
        
        $settings = json_encode([
            'install_date' => date('Y-m-d H:i:s'),
            'auto_cleanup' => true
        ]);
        
        $stmt->execute([$this->version, $settings]);
    }
    
    /* =============================================================================
       Core Invite Functions
       ========================================================================== */
    
    /**
     * Validate an invite code
     */
    public function validateInviteCode($invite_code) {
        try {
            $stmt = $this->pdo->prepare("CALL ValidateInviteCode(?, @valid, @invite_id, @message)");
            $stmt->execute([$invite_code]);
            
            $result = $this->pdo->query("SELECT @valid as valid, @invite_id as invite_id, @message as message")->fetch();
            
            return [
                'valid' => (bool)$result['valid'],
                'invite_id' => $result['invite_id'],
                'message' => $result['message']
            ];
        } catch (Exception $e) {
            error_log("Error validating invite code: " . $e->getMessage());
            return ['valid' => false, 'invite_id' => null, 'message' => 'Validation error'];
        }
    }
    
    /**
     * Record invite usage
     */
    public function recordInviteUsage($invite_id, $user_id, $ip_address = null, $user_agent = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Record usage
            $stmt = $this->pdo->prepare("
                INSERT INTO invite_usages (invite_id, user_id, ip_address, user_agent, used_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$invite_id, $user_id, $ip_address, $user_agent]);
            
            // Update invite usage count
            $stmt = $this->pdo->prepare("
                UPDATE invites 
                SET used_count = used_count + 1, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$invite_id]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error recording invite usage: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cleanup expired invites
     */
    public function cleanupExpiredInvites() {
        try {
            $stmt = $this->pdo->prepare("CALL CleanupExpiredInvites()");
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            error_log("Error cleaning up expired invites: " . $e->getMessage());
            return false;
        }
    }
    
    /* =============================================================================
       Helper Methods
       ========================================================================== */
    
    private function getDbConnection() {
        if (function_exists('getDbConnection')) {
            return getDbConnection();
        }
        
        // Fallback if function doesn't exist
        throw new Exception('Database connection function not available');
    }
    
    private function loadConfig() {
        $config_file = __DIR__ . '/config.php';
        if (file_exists($config_file)) {
            return include $config_file;
        }
        
        // Default config
        return [
            'default_expiry_days' => 30,
            'default_max_uses' => 1,
            'cleanup_interval' => 'daily'
        ];
    }
    
    private function dropTables() {
        $tables = ['user_invite_quotas', 'invite_usages', 'invites'];
        
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        
        // Drop views
        $this->pdo->exec("DROP VIEW IF EXISTS `invite_stats`");
        $this->pdo->exec("DROP VIEW IF EXISTS `recent_invite_usage`");
        
        // Drop procedures
        $this->pdo->exec("DROP PROCEDURE IF EXISTS `GenerateInviteCode`");
        $this->pdo->exec("DROP PROCEDURE IF EXISTS `ValidateInviteCode`");
        $this->pdo->exec("DROP PROCEDURE IF EXISTS `CleanupExpiredInvites`");
    }
    
    private function removeSettings() {
        $setting_keys = [
            'invite_only_mode', 'invites_per_week', 'invite_expiry_days',
            'invite_auto_approve', 'invite_max_uses', 'invite_allow_user_creation',
            'invite_admin_only', 'verification_bonus_credits', 'auto_login_after_verification',
            'verification_token_expiry', 'verification_resend_rate_limit'
        ];
        
        $placeholders = str_repeat('?,', count($setting_keys) - 1) . '?';
        $stmt = $this->pdo->prepare("DELETE FROM settings WHERE `key` IN ($placeholders)");
        $stmt->execute($setting_keys);
    }
    
    private function unregisterModule() {
        $stmt = $this->pdo->prepare("DELETE FROM modules WHERE name = 'invite_system'");
        $stmt->execute();
    }
    
    private function updateModuleVersion() {
        $stmt = $this->pdo->prepare("
            UPDATE modules 
            SET version = ?, updated_at = NOW() 
            WHERE name = 'invite_system'
        ");
        $stmt->execute([$this->version]);
    }
    
    private function updateTo100() {
        // Placeholder for future updates
        // Add any database schema changes needed for version 1.0.0
    }
}