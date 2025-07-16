<?php
/**
 * PhPstrap Installer - Database Installation Functions
 * Save as: installer/database.php
 */

function installDatabaseSchema($pdo) {
    try {
        // Start transaction for atomic installation
        $pdo->beginTransaction();
        
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
        
        // Insert default settings
        insertDefaultSettings($pdo);
        
        // Add foreign key constraints (after all tables exist)
        addForeignKeyConstraints($pdo);
        
        // Commit transaction
        $pdo->commit();
        
        return true;
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        throw new Exception("Database schema installation failed: " . $e->getMessage());
    }
}

function createUsersTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `users` (
        `id` int NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `email` varchar(100) NOT NULL,
        `password` varchar(255) NOT NULL,
        `totp_enabled` tinyint(1) DEFAULT '0',
        `totp_secret` varchar(32) DEFAULT NULL,
        `affiliate_id` varchar(10) DEFAULT NULL,
        `credits` decimal(10,2) DEFAULT '0.00',
        `api_token` varchar(100) DEFAULT NULL,
        `company_name` varchar(255) DEFAULT NULL,
        `address` text,
        `phone_number` varchar(20) DEFAULT NULL,
        `membership_status` enum('free','premium','lifetime') DEFAULT 'free',
        `membership_expiry` date DEFAULT NULL,
        `api_token_usage_count` int DEFAULT '0',
        `api_token_max_usage` int DEFAULT '1000',
        `api_token_reset_time` datetime DEFAULT CURRENT_TIMESTAMP,
        `stripe_subscription_id` varchar(255) DEFAULT NULL,
        `stripe_customer_id` varchar(255) DEFAULT NULL,
        `verification_token` varchar(64) DEFAULT NULL,
        `verified` tinyint(1) NOT NULL DEFAULT '0',
        `verified_at` datetime DEFAULT NULL,
        `last_verification_sent_at` datetime DEFAULT NULL,
        `last_login_at` datetime DEFAULT NULL,
        `last_login_ip` varchar(45) DEFAULT NULL,
        `login_attempts` int DEFAULT '0',
        `locked_until` datetime DEFAULT NULL,
        `preferences` json DEFAULT NULL,
        `timezone` varchar(50) DEFAULT 'UTC',
        `language` varchar(5) DEFAULT 'en',
        `avatar` varchar(255) DEFAULT NULL,
        `bio` text,
        `website` varchar(255) DEFAULT NULL,
        `social_links` json DEFAULT NULL,
        `email_notifications` tinyint(1) DEFAULT '1',
        `marketing_emails` tinyint(1) DEFAULT '0',
        `is_active` tinyint(1) DEFAULT '1',
        `is_admin` tinyint(1) DEFAULT '0',
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email` (`email`),
        UNIQUE KEY `affiliate_id` (`affiliate_id`),
        UNIQUE KEY `api_token` (`api_token`),
        KEY `membership_status` (`membership_status`),
        KEY `verified` (`verified`),
        KEY `is_active` (`is_active`),
        KEY `created_at` (`created_at`),
        KEY `last_login_at` (`last_login_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function createAffiliateClicksTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `affiliate_clicks` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NOT NULL,
        `click_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text,
        `referrer` varchar(500) DEFAULT NULL,
        `country` varchar(2) DEFAULT NULL,
        `browser` varchar(100) DEFAULT NULL,
        `device_type` enum('desktop','mobile','tablet') DEFAULT 'desktop',
        `conversion_value` decimal(10,2) DEFAULT '0.00',
        `converted` tinyint(1) DEFAULT '0',
        `converted_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `click_time` (`click_time`),
        KEY `ip_address` (`ip_address`),
        KEY `converted` (`converted`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function createAffiliateSignupsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `affiliate_signups` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NOT NULL,
        `referred_user_id` int NOT NULL,
        `signup_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `commission_amount` decimal(10,2) DEFAULT '0.00',
        `commission_rate` decimal(5,2) DEFAULT '0.00',
        `status` enum('pending','approved','paid','cancelled') DEFAULT 'pending',
        `paid_at` datetime DEFAULT NULL,
        `notes` text,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `referred_user_id` (`referred_user_id`),
        KEY `signup_time` (`signup_time`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function createApiResellersTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `api_resellers` (
        `id` int NOT NULL AUTO_INCREMENT,
        `name` varchar(255) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `company` varchar(255) DEFAULT NULL,
        `api_key` varchar(100) DEFAULT NULL,
        `api_secret` varchar(100) DEFAULT NULL,
        `credits_remaining` int DEFAULT '0',
        `credits_used` int DEFAULT '0',
        `rate_limit` int DEFAULT '1000',
        `rate_limit_window` int DEFAULT '3600',
        `allowed_ips` text,
        `webhook_url` varchar(500) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT '1',
        `last_used_at` datetime DEFAULT NULL,
        `expires_at` datetime DEFAULT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `api_key` (`api_key`),
        UNIQUE KEY `api_secret` (`api_secret`),
        KEY `is_active` (`is_active`),
        KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function createInvitesTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `invites` (
        `id` int NOT NULL AUTO_INCREMENT,
        `code` varchar(32) NOT NULL,
        `generated_by` int NOT NULL,
        `used_by` int DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `used_at` timestamp NULL DEFAULT NULL,
        `expires_at` timestamp NULL DEFAULT NULL,
        `max_uses` int DEFAULT '1',
        `uses_count` int DEFAULT '0',
        `invite_type` enum('registration','premium','admin') DEFAULT 'registration',
        `custom_message` text,
        `is_active` tinyint(1) DEFAULT '1',
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`),
        KEY `generated_by` (`generated_by`),
        KEY `used_by` (`used_by`),
        KEY `expires_at` (`expires_at`),
        KEY `email` (`email`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function createPasswordResetsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NOT NULL,
        `token` varchar(100) NOT NULL,
        `expires_at` datetime NOT NULL,
        `used` tinyint(1) DEFAULT '0',
        `used_at` datetime DEFAULT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `token` (`token`),
        KEY `expires_at` (`expires_at`),
        KEY `used` (`used`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function createTokenPurchasesTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `token_purchases` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NOT NULL,
        `token_pack` int NOT NULL,
        `tokens_amount` int NOT NULL,
        `price` decimal(10,2) NOT NULL,
        `currency` varchar(3) DEFAULT 'USD',
        `purchase_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `method` varchar(50) NOT NULL,
        `transaction_id` varchar(255) DEFAULT NULL,
        `gateway_response` json DEFAULT NULL,
        `status` enum('pending','completed','failed','refunded','cancelled') DEFAULT 'pending',
        `refunded_amount` decimal(10,2) DEFAULT '0.00',
        `refunded_at` datetime DEFAULT NULL,
        `notes` text,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `purchase_date` (`purchase_date`),
        KEY `status` (`status`),
        KEY `method` (`method`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function createUserTokensTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `user_tokens` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NOT NULL,
        `token` varchar(64) NOT NULL,
        `token_type` enum('auth','api','reset','verify','remember') DEFAULT 'auth',
        `device_info` json DEFAULT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `user_agent` text,
        `created_at` datetime NOT NULL,
        `expires_at` datetime NOT NULL,
        `last_used_at` datetime DEFAULT NULL,
        `usage_count` int DEFAULT '0',
        `is_active` tinyint(1) DEFAULT '1',
        `revoked_at` datetime DEFAULT NULL,
        `revoked_reason` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `token` (`token`),
        KEY `user_id` (`user_id`),
        KEY `expires_at` (`expires_at`),
        KEY `token_type` (`token_type`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function createWithdrawalsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `withdrawals` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `fee` decimal(10,2) DEFAULT '0.00',
        `net_amount` decimal(10,2) NOT NULL,
        `currency` varchar(3) DEFAULT 'USD',
        `method` varchar(50) NOT NULL,
        `payment_details` json DEFAULT NULL,
        `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
        `request_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `processed_time` timestamp NULL DEFAULT NULL,
        `completed_time` timestamp NULL DEFAULT NULL,
        `processed_by` int DEFAULT NULL,
        `transaction_id` varchar(255) DEFAULT NULL,
        `gateway_response` json DEFAULT NULL,
        `notes` text,
        `admin_notes` text,
        `rejection_reason` varchar(500) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `status` (`status`),
        KEY `request_time` (`request_time`),
        KEY `processed_by` (`processed_by`),
        KEY `method` (`method`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function createSettingsTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `settings` (
        `id` int NOT NULL AUTO_INCREMENT,
        `key` varchar(100) NOT NULL,
        `value` longtext,
        `type` enum('string','integer','boolean','json','text','array') DEFAULT 'string',
        `description` text,
        `category` varchar(50) DEFAULT 'general',
        `is_public` tinyint(1) DEFAULT '0',
        `is_required` tinyint(1) DEFAULT '0',
        `validation_rules` json DEFAULT NULL,
        `default_value` text,
        `sort_order` int DEFAULT '0',
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` int DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `key` (`key`),
        KEY `category` (`category`),
        KEY `is_public` (`is_public`),
        KEY `sort_order` (`sort_order`),
        KEY `updated_by` (`updated_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function createModulesTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `modules` (
        `id` int NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `title` varchar(255) NOT NULL,
        `description` text,
        `version` varchar(20) DEFAULT '1.0.0',
        `required_version` varchar(20) DEFAULT '1.0.0',
        `author` varchar(100) DEFAULT '',
        `author_url` varchar(255) DEFAULT '',
        `enabled` tinyint(1) DEFAULT '0',
        `auto_enable` tinyint(1) DEFAULT '0',
        `dependencies` json DEFAULT NULL,
        `settings` json DEFAULT NULL,
        `hooks` json DEFAULT NULL,
        `permissions` json DEFAULT NULL,
        `install_path` varchar(255) DEFAULT NULL,
        `namespace` varchar(100) DEFAULT NULL,
        `priority` int DEFAULT '10',
        `install_sql` text,
        `uninstall_sql` text,
        `update_urls` json DEFAULT NULL,
        `changelog` text,
        `screenshots` json DEFAULT NULL,
        `tags` json DEFAULT NULL,
        `license` varchar(50) DEFAULT 'MIT',
        `is_core` tinyint(1) DEFAULT '0',
        `is_commercial` tinyint(1) DEFAULT '0',
        `price` decimal(10,2) DEFAULT '0.00',
        `installed_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `last_check` datetime DEFAULT NULL,
        `status` enum('active','inactive','broken','updating') DEFAULT 'inactive',
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`),
        KEY `enabled` (`enabled`),
        KEY `updated_at` (`updated_at`),
        KEY `status` (`status`),
        KEY `is_core` (`is_core`),
        KEY `priority` (`priority`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
}

function insertDefaultSettings($pdo) {
    $settings = array(
        // Core system settings
        array('PhPstrap_version', '1.0.0', 'string', 'PhPstrap version number', 'system', 0, 1),
        array('installation_id', generateInstallationId(), 'string', 'Unique installation identifier', 'system', 0, 1),
        array('site_name', 'PhPstrap', 'string', 'Website name displayed in title and headers', 'general', 1, 1),
        array('site_description', 'A powerful PHP membership system', 'text', 'Website description for SEO', 'general', 1, 0),
        array('site_keywords', 'php,membership,users,cms', 'string', 'SEO keywords', 'general', 1, 0),
        array('site_url', '', 'string', 'Full website URL including protocol', 'general', 1, 1),
        array('admin_email', '', 'string', 'Primary administrator email address', 'general', 1, 1),
        array('primary_admin_id', '', 'integer', 'Primary administrator user ID', 'system', 0, 1),
        array('maintenance_mode', '0', 'boolean', 'Enable maintenance mode to restrict access', 'system', 0, 0),
        array('installation_date', date('Y-m-d H:i:s'), 'string', 'System installation date and time', 'system', 0, 1),
        array('timezone', 'UTC', 'string', 'Default system timezone', 'general', 1, 1),
        array('date_format', 'Y-m-d', 'string', 'Default date display format', 'general', 1, 0),
        array('time_format', 'H:i:s', 'string', 'Default time display format', 'general', 1, 0),
        
        // User registration and management
        array('registration_enabled', '1', 'boolean', 'Allow new user registration', 'users', 1, 0),
        array('email_verification_required', '1', 'boolean', 'Require email verification for new accounts', 'users', 1, 0),
        array('admin_approval_required', '0', 'boolean', 'Require admin approval for new accounts', 'users', 1, 0),
        array('default_user_credits', '0.00', 'string', 'Default credits assigned to new users', 'users', 1, 0),
        array('default_user_role', 'user', 'string', 'Default role for new users', 'users', 1, 0),
        array('auto_approve_users', '1', 'boolean', 'Automatically approve new user accounts', 'users', 1, 0),
        array('allow_username_change', '0', 'boolean', 'Allow users to change their usernames', 'users', 1, 0),
        array('allow_email_change', '1', 'boolean', 'Allow users to change their email addresses', 'users', 1, 0),
        array('delete_account_enabled', '1', 'boolean', 'Allow users to delete their own accounts', 'users', 1, 0),
        
        // Password and security settings
        array('password_min_length', '8', 'integer', 'Minimum password length requirement', 'security', 1, 0),
        array('password_require_uppercase', '0', 'boolean', 'Require uppercase letters in passwords', 'security', 1, 0),
        array('password_require_lowercase', '0', 'boolean', 'Require lowercase letters in passwords', 'security', 1, 0),
        array('password_require_numbers', '0', 'boolean', 'Require numbers in passwords', 'security', 1, 0),
        array('password_require_symbols', '0', 'boolean', 'Require special characters in passwords', 'security', 1, 0),
        array('session_timeout', '3600', 'integer', 'Session timeout in seconds (0 for no timeout)', 'security', 1, 0),
        array('max_login_attempts', '5', 'integer', 'Maximum failed login attempts before lockout', 'security', 1, 0),
        array('lockout_duration', '900', 'integer', 'Account lockout duration in seconds', 'security', 1, 0),
        array('force_https', '0', 'boolean', 'Force HTTPS redirects for all pages', 'security', 1, 0),
        array('two_factor_auth_enabled', '0', 'boolean', 'Enable two-factor authentication', 'security', 1, 0),
        
        // Affiliate program settings
        array('affiliate_program_enabled', '1', 'boolean', 'Enable the affiliate/referral program', 'affiliate', 1, 0),
        array('credit_per_signup', '10.00', 'string', 'Credits awarded per successful referral', 'affiliate', 1, 0),
        array('affiliate_commission_rate', '10.00', 'string', 'Commission percentage for affiliates', 'affiliate', 1, 0),
        array('min_withdrawal_amount', '50.00', 'string', 'Minimum amount required for withdrawal', 'affiliate', 1, 0),
        array('withdrawal_fee', '0.00', 'string', 'Fee charged for withdrawals', 'affiliate', 1, 0),
        array('affiliate_cookie_lifetime', '30', 'integer', 'Affiliate tracking cookie lifetime in days', 'affiliate', 1, 0),
        
        // Invite system settings
        array('invite_only_mode', '0', 'boolean', 'Require invite codes for new registrations', 'invites', 1, 0),
        array('invites_per_week', '1', 'integer', 'Number of invite codes each user can generate per week', 'invites', 1, 0),
        array('invite_expiry_days', '30', 'integer', 'Number of days until invite codes expire', 'invites', 1, 0),
        array('invite_auto_approve', '1', 'boolean', 'Auto-approve users who register with valid invites', 'invites', 1, 0),
        
        // Language and localization
        array('available_languages', 'en,fr,es,de,it,pt,ru,zh,ja,ko', 'string', 'Available language codes (comma-separated)', 'localization', 1, 0),
        array('default_language', 'en', 'string', 'Default language code for new users', 'localization', 1, 1),
        array('show_language_toggle', '1', 'boolean', 'Show language selector in the interface', 'localization', 1, 0),
        array('auto_detect_language', '1', 'boolean', 'Auto-detect user language from browser', 'localization', 1, 0),
        array('rtl_languages', 'ar,he,fa,ur', 'string', 'Right-to-left language codes', 'localization', 1, 0),
        
        // Appearance and theming
        array('site_icon', 'fas fa-home', 'string', 'FontAwesome icon class for site branding', 'appearance', 1, 0),
        array('theme_color', '#007bff', 'string', 'Primary theme color (hex code)', 'appearance', 1, 0),
        array('secondary_color', '#6c757d', 'string', 'Secondary theme color (hex code)', 'appearance', 1, 0),
        array('default_social_image', '/assets/img/social-default.png', 'string', 'Default image for social media sharing', 'appearance', 1, 0),
        array('favicon_url', '/favicon.ico', 'string', 'Favicon URL or path', 'appearance', 1, 0),
        array('logo_url', '', 'string', 'Logo image URL or path', 'appearance', 1, 0),
        array('custom_css', '', 'text', 'Custom CSS code to be included on all pages', 'appearance', 1, 0),
        array('custom_js', '', 'text', 'Custom JavaScript code to be included on all pages', 'appearance', 1, 0),
        array('footer_text', '© 2025 PhPstrap. All rights reserved.', 'string', 'Footer copyright text', 'appearance', 1, 0),
        
        // API and developer settings
        array('api_enabled', '1', 'boolean', 'Enable API endpoints for external access', 'api', 1, 0),
        array('api_rate_limit', '1000', 'integer', 'API requests per hour per user', 'api', 1, 0),
        array('api_rate_limit_window', '3600', 'integer', 'Rate limit time window in seconds', 'api', 1, 0),
        array('api_require_auth', '1', 'boolean', 'Require authentication for API access', 'api', 1, 0),
        array('api_version', 'v1', 'string', 'Current API version', 'api', 1, 0),
        array('webhook_enabled', '0', 'boolean', 'Enable webhook notifications', 'api', 1, 0),
        array('webhook_secret', '', 'string', 'Secret key for webhook verification', 'api', 0, 0),
        
        // Email configuration
        array('mail_driver', 'php', 'string', 'Email driver (php, smtp, sendmail)', 'email', 1, 0),
        array('mail_from_address', '', 'string', 'Default sender email address', 'email', 1, 0),
        array('mail_from_name', '', 'string', 'Default sender name', 'email', 1, 0),
        array('mail_reply_to', '', 'string', 'Default reply-to email address', 'email', 1, 0),
        array('email_templates_enabled', '1', 'boolean', 'Use email templates for notifications', 'email', 1, 0),
        array('email_queue_enabled', '0', 'boolean', 'Queue emails for batch processing', 'email', 1, 0),
        
        // File upload settings
        array('max_upload_size', '10485760', 'integer', 'Maximum file upload size in bytes (10MB)', 'uploads', 1, 0),
        array('allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip', 'string', 'Allowed file extensions for upload', 'uploads', 1, 0),
        array('upload_path', '/uploads', 'string', 'Upload directory path relative to site root', 'uploads', 1, 0),
        array('image_max_width', '1920', 'integer', 'Maximum image width in pixels', 'uploads', 1, 0),
        array('image_max_height', '1080', 'integer', 'Maximum image height in pixels', 'uploads', 1, 0),
        array('generate_thumbnails', '1', 'boolean', 'Automatically generate image thumbnails', 'uploads', 1, 0),
        array('thumbnail_sizes', '150x150,300x300', 'string', 'Thumbnail sizes to generate (WxH)', 'uploads', 1, 0),
        
        // Backup and maintenance
        array('auto_backup_enabled', '0', 'boolean', 'Enable automatic database backups', 'backup', 1, 0),
        array('backup_frequency', 'weekly', 'string', 'Backup frequency (daily, weekly, monthly)', 'backup', 1, 0),
        array('keep_backups', '5', 'integer', 'Number of backup files to retain', 'backup', 1, 0),
        array('last_backup', '', 'string', 'Timestamp of last successful backup', 'backup', 0, 0),
        array('backup_compression', '1', 'boolean', 'Compress backup files', 'backup', 1, 0),
        array('maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.', 'text', 'Message displayed during maintenance mode', 'backup', 1, 0),
        
        // Analytics and tracking
        array('google_analytics_id', '', 'string', 'Google Analytics tracking ID', 'analytics', 1, 0),
        array('facebook_pixel_id', '', 'string', 'Facebook Pixel ID for tracking', 'analytics', 1, 0),
        array('google_tag_manager_id', '', 'string', 'Google Tag Manager container ID', 'analytics', 1, 0),
        array('track_user_activity', '1', 'boolean', 'Track user login and activity', 'analytics', 1, 0),
        array('anonymous_analytics', '0', 'boolean', 'Use anonymous tracking for privacy', 'analytics', 1, 0),
        
        // Performance and caching
        array('cache_enabled', '0', 'boolean', 'Enable application-level caching', 'performance', 1, 0),
        array('cache_driver', 'file', 'string', 'Cache driver (file, redis, memcached)', 'performance', 1, 0),
        array('cache_lifetime', '3600', 'integer', 'Default cache lifetime in seconds', 'performance', 1, 0),
        array('compress_output', '0', 'boolean', 'Enable output compression', 'performance', 1, 0),
        array('minify_css', '0', 'boolean', 'Minify CSS files', 'performance', 1, 0),
        array('minify_js', '0', 'boolean', 'Minify JavaScript files', 'performance', 1, 0),
        
        // Legal and compliance
        array('privacy_policy_url', '', 'string', 'Privacy policy page URL', 'legal', 1, 0),
        array('terms_of_service_url', '', 'string', 'Terms of service page URL', 'legal', 1, 0),
        array('cookie_consent_enabled', '0', 'boolean', 'Show cookie consent banner', 'legal', 1, 0),
        array('gdpr_compliance', '0', 'boolean', 'Enable GDPR compliance features', 'legal', 1, 0),
        array('data_retention_days', '365', 'integer', 'Days to retain user data after deletion', 'legal', 1, 0)
    );
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO settings (`key`, `value`, `type`, `description`, `category`, `is_public`, `is_required`) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
}

function createAdminUser($pdo, $name, $email, $password) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $api_token = bin2hex(random_bytes(32));
    $affiliate_id = generateAffiliateId();
    
    $stmt = $pdo->prepare("
        INSERT INTO users (
            name, email, password, api_token, affiliate_id, 
            membership_status, verified, verified_at, is_admin,
            email_notifications, timezone, language
        ) VALUES (?, ?, ?, ?, ?, 'premium', 1, NOW(), 1, 1, 'UTC', 'en')
    ");
    
    $stmt->execute([$name, $email, $hashed_password, $api_token, $affiliate_id]);
    return $pdo->lastInsertId();
}

function updateSiteSettings($pdo, $admin_email, $site_name, $admin_user_id) {
    // Get current server info for site URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $site_url = $protocol . '://' . $host . $dir;
    
    // Update core settings with installation data
    $updates = array(
        array('admin_email', $admin_email),
        array('site_name', $site_name),
        array('site_url', $site_url),
        array('primary_admin_id', $admin_user_id),
        array('mail_from_address', $admin_email),
        array('mail_from_name', $site_name),
        array('installation_date', date('Y-m-d H:i:s'))
    );
    
    $stmt = $pdo->prepare("UPDATE settings SET value = ?, updated_at = NOW() WHERE `key` = ?");
    foreach ($updates as $update) {
        $stmt->execute([$update[1], $update[0]]);
    }
}

function generateAffiliateId() {
    return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

function generateInstallationId() {
    return 'PhPstrap_' . bin2hex(random_bytes(16));
}

function addForeignKeyConstraints($pdo) {
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
            // Ignore constraint errors if they already exist
            if ($e->getCode() !== '23000') {
                error_log("Foreign key constraint error: " . $e->getMessage());
            }
        }
    }
}

function createDatabaseIndexes($pdo) {
    $indexes = array(
        // Performance indexes for common queries
        "CREATE INDEX idx_users_email_verified ON users (email, verified)",
        "CREATE INDEX idx_users_membership_active ON users (membership_status, is_active)",
        "CREATE INDEX idx_affiliate_clicks_user_time ON affiliate_clicks (user_id, click_time)",
        "CREATE INDEX idx_settings_category_public ON settings (category, is_public)",
        "CREATE INDEX idx_modules_enabled_priority ON modules (enabled, priority)",
        "CREATE INDEX idx_user_tokens_type_active ON user_tokens (token_type, is_active, expires_at)",
        "CREATE INDEX idx_password_resets_token_used ON password_resets (token, used, expires_at)"
    );
    
    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Ignore index errors if they already exist
            error_log("Index creation warning: " . $e->getMessage());
        }
    }
}

function optimizeDatabaseTables($pdo) {
    try {
        // Get all tables in the database
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $pdo->exec("OPTIMIZE TABLE `$table`");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Table optimization error: " . $e->getMessage());
        return false;
    }
}

function createDatabaseViews($pdo) {
    $views = array(
        "CREATE OR REPLACE VIEW user_stats AS 
         SELECT 
             u.id,
             u.name,
             u.email,
             u.membership_status,
             u.credits,
             u.created_at,
             COUNT(ac.id) as total_clicks,
             COUNT(as.id) as total_referrals,
             SUM(as.commission_amount) as total_commissions
         FROM users u 
         LEFT JOIN affiliate_clicks ac ON u.id = ac.user_id 
         LEFT JOIN affiliate_signups as ON u.id = as.user_id 
         GROUP BY u.id",
         
        "CREATE OR REPLACE VIEW active_users AS
         SELECT * FROM users 
         WHERE is_active = 1 AND verified = 1",
         
        "CREATE OR REPLACE VIEW pending_withdrawals AS
         SELECT w.*, u.name as user_name, u.email as user_email 
         FROM withdrawals w 
         JOIN users u ON w.user_id = u.id 
         WHERE w.status = 'pending'"
    );
    
    foreach ($views as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("View creation error: " . $e->getMessage());
        }
    }
}

function runDatabaseMaintenance($pdo) {
    try {
        // Clean up expired password reset tokens
        $pdo->exec("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
        
        // Clean up expired user tokens
        $pdo->exec("DELETE FROM user_tokens WHERE expires_at < NOW() AND token_type != 'api'");
        
        // Clean up old affiliate clicks (older than 1 year)
        $pdo->exec("DELETE FROM affiliate_clicks WHERE click_time < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
        
        // Update user login statistics
        $pdo->exec("UPDATE users SET login_attempts = 0 WHERE locked_until < NOW()");
        
        return true;
    } catch (PDOException $e) {
        error_log("Database maintenance error: " . $e->getMessage());
        return false;
    }
}
?>