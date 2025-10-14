<?php
/**
 * Module Configuration File
 * File: /modules/invite_system/config.php
 */

return [
    // Module Identity
    'name' => 'invite_system',
    'display_name' => 'Invite System',
    'description' => 'Complete invitation system for user registration management',
    'version' => '1.0.0',
    'author' => 'PhPstrap',
    'website' => 'https://phpstrap.com',
    
    // Requirements
    'requires' => [
        'php' => '7.4.0',
        'mysql' => '5.7.0',
        'modules' => [] // No module dependencies
    ],
    
    // Module Settings
    'settings' => [
        'default_expiry_days' => 30,
        'default_max_uses' => 1,
        'max_user_quota' => 10,
        'cleanup_interval' => 'daily',
        'enable_user_creation' => true,
        'enable_quota_system' => true
    ],
    
    // Database Tables
    'tables' => [
        'invites',
        'invite_usages', 
        'user_invite_quotas'
    ],
    
    // Views
    'views' => [
        'invite_stats',
        'recent_invite_usage'
    ],
    
    // Stored Procedures
    'procedures' => [
        'GenerateInviteCode',
        'ValidateInviteCode',
        'CleanupExpiredInvites'
    ],
    
    // Settings Keys
    'settings_keys' => [
        'invite_only_mode',
        'invites_per_week',
        'invite_expiry_days',
        'invite_auto_approve',
        'invite_max_uses',
        'invite_allow_user_creation',
        'invite_admin_only',
        'verification_bonus_credits',
        'auto_login_after_verification',
        'verification_token_expiry',
        'verification_resend_rate_limit'
    ],
    
    // Admin Pages
    'admin_pages' => [
        'main' => '/admin/invites.php',
        'settings' => '/admin/settings.php#invites'
    ],
    
    // User Pages  
    'user_pages' => [
        'manage' => '/dashboard/invites.php'
    ],
    
    // Hooks (if your system supports them)
    'hooks' => [
        'user_registration' => 'handleUserRegistration',
        'daily_cleanup' => 'cleanupExpiredInvites',
        'admin_menu' => 'addAdminMenuItem'
    ],
    
    // Assets
    'assets' => [
        'css' => [],
        'js' => []
    ],
    
    // Permissions
    'permissions' => [
        'invite.create' => 'Create invite codes',
        'invite.manage' => 'Manage all invites',
        'invite.view' => 'View invite statistics',
        'invite.delete' => 'Delete invite codes'
    ]
];

/**
 * Module Manifest File  
 * File: /modules/invite_system/module.json
 */
/*
{
    "name": "invite_system",
    "display_name": "Invite System",
    "description": "Complete invitation system for user registration management",
    "version": "1.0.0",
    "author": "PhPstrap",
    "license": "MIT",
    "type": "feature",
    "category": "user_management",
    "tags": ["invites", "registration", "referrals", "user-management"],
    
    "requirements": {
        "php": ">=7.4.0",
        "mysql": ">=5.7.0",
        "phpstrap": ">=1.0.0"
    },
    
    "files": {
        "main": "InviteSystem.php",
        "installer": "install.php",
        "config": "config.php",
        "admin": [
            "../../admin/invites.php"
        ],
        "user": [
            "../../dashboard/invites.php"
        ]
    },
    
    "database": {
        "tables": ["invites", "invite_usages", "user_invite_quotas"],
        "views": ["invite_stats", "recent_invite_usage"],
        "procedures": ["GenerateInviteCode", "ValidateInviteCode", "CleanupExpiredInvites"]
    },
    
    "settings": {
        "category": "invites",
        "keys": [
            "invite_only_mode",
            "invites_per_week", 
            "invite_expiry_days",
            "invite_auto_approve",
            "invite_max_uses",
            "invite_allow_user_creation",
            "invite_admin_only"
        ]
    },
    
    "menu_items": {
        "admin": {
            "invites": {
                "title": "Invites",
                "url": "/admin/invites.php",
                "icon": "fas fa-envelope",
                "permission": "invite.view"
            }
        },
        "user": {
            "my_invites": {
                "title": "My Invites", 
                "url": "/dashboard/invites.php",
                "icon": "fas fa-share-alt"
            }
        }
    },
    
    "hooks": {
        "registration.before": "validateInviteCode",
        "registration.after": "recordInviteUsage",
        "daily.cleanup": "cleanupExpiredInvites"
    }
}
*/

/**
 * Module Integration Helper
 * File: /modules/invite_system/integration.php
 */

/**
 * Integration functions for the invite system module
 */

/**
 * Add invite system to registration process
 */
function integrateWithRegistration() {
    // This function should be called from your registration.php file
    // to integrate invite validation
    
    if (!function_exists('validateInviteCode')) {
        function validateInviteCode($pdo, $invite_code) {
            require_once __DIR__ . '/InviteSystem.php';
            
            $inviteSystem = new PhPstrap\Modules\InviteSystem\InviteSystem($pdo);
            return $inviteSystem->validateInviteCode($invite_code);
        }
    }
    
    if (!function_exists('recordInviteUsage')) {
        function recordInviteUsage($pdo, $invite_id, $user_id, $ip = null, $user_agent = null) {
            require_once __DIR__ . '/InviteSystem.php';
            
            $inviteSystem = new PhPstrap\Modules\InviteSystem\InviteSystem($pdo);
            return $inviteSystem->recordInviteUsage($invite_id, $user_id, $ip, $user_agent);
        }
    }
}

/**
 * Add admin menu item for invites
 */
function addInviteAdminMenuItem() {
    // Add this to your admin menu rendering function
    return [
        'title' => 'Invites',
        'url' => '/admin/invites.php',
        'icon' => 'fas fa-envelope',
        'active' => (basename($_SERVER['PHP_SELF']) === 'invites.php'),
        'permission' => 'admin'
    ];
}

/**
 * Add user dashboard menu item
 */
function addInviteUserMenuItem() {
    // Add this to your user dashboard menu
    return [
        'title' => 'My Invites',
        'url' => '/dashboard/invites.php', 
        'icon' => 'fas fa-share-alt',
        'active' => (basename($_SERVER['PHP_SELF']) === 'invites.php')
    ];
}

/**
 * Check if invite system is available
 */
function isInviteSystemAvailable() {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SHOW TABLES LIKE 'invites'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get invite system statistics for dashboard
 */
function getInviteSystemStats($user_id = null) {
    try {
        $pdo = getDbConnection();
        
        if ($user_id) {
            // User-specific stats
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as my_invites,
                    SUM(used_count) as total_uses,
                    COUNT(CASE WHEN status = 'active' AND expires_at > NOW() THEN 1 END) as active_invites
                FROM invites 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
        } else {
            // System-wide stats
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_invites,
                    SUM(used_count) as total_uses,
                    COUNT(CASE WHEN status = 'active' AND expires_at > NOW() THEN 1 END) as active_invites,
                    COUNT(DISTINCT user_id) as unique_creators
                FROM invites
            ");
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching invite stats: " . $e->getMessage());
        return null;
    }
}

/**
 * Installation Instructions
 * 
 * 1. Upload the entire /modules/invite_system/ folder to your PhPstrap installation
 * 
 * 2. Navigate to /modules/invite_system/install.php in your browser
 * 
 * 3. Click "Install Module" to create all database tables and settings
 * 
 * 4. Copy the admin interface file to /admin/invites.php:
 *    cp admin_interface.php ../../admin/invites.php
 * 
 * 5. Copy the user interface file to /dashboard/invites.php:
 *    cp user_interface.php ../../dashboard/invites.php
 * 
 * 6. Update your registration system to integrate invite validation:
 *    - Include invite_system/integration.php in your registration.php
 *    - Call integrateWithRegistration() at the start
 *    - Use validateInviteCode() and recordInviteUsage() functions
 * 
 * 7. Add menu items to your admin and user interfaces:
 *    - Use addInviteAdminMenuItem() for admin menu
 *    - Use addInviteUserMenuItem() for user dashboard menu
 * 
 * 8. Optional: Set up automatic cleanup by calling cleanupExpiredInvites() 
 *    in your daily cron job or maintenance script
 * 
 * 9. Configure settings via Admin > Settings > Invites section
 * 
 * Directory Structure:
 * /modules/invite_system/
 * ├── InviteSystem.php          (Main module class)
 * ├── install.php               (Installation interface) 
 * ├── config.php                (Module configuration)
 * ├── integration.php           (Integration helpers)
 * ├── module.json               (Module manifest)
 * └── README.md                 (Documentation)
 * 
 * /admin/
 * └── invites.php               (Admin interface)
 * 
 * /dashboard/
 * └── invites.php               (User interface)
 */

/**
 * Quick Integration Example for registration.php:
 */
/*

// At the top of your registration.php file:
require_once '../modules/invite_system/integration.php';
integrateWithRegistration();

// In your form processing logic:
if ($requires_invite || !empty($invite_code)) {
    $invite_validation = validateInviteCode($pdo, $invite_code);
    if (!$invite_validation['valid']) {
        $error_message = $invite_validation['message'];
    } else {
        $invite_id = $invite_validation['invite_id'];
        // Continue with registration...
        
        // After successful user creation:
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        recordInviteUsage($pdo, $invite_id, $new_user_id, $user_ip, $user_agent);
    }
}

*/