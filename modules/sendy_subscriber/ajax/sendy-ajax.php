<?php
/**
 * AJAX handler for Sendy module admin actions
 */

session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Include the module
require_once dirname(__DIR__) . '/SendyModule.php';

// Initialize module
$module = new \PhPstrap\Modules\Sendy\SendyModule();
$module->init();

// Handle different actions
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'test_subscribe':
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $name = $_POST['name'] ?? 'Test User';
        
        if (!$email) {
            die(json_encode(['success' => false, 'message' => 'Invalid email address']));
        }
        
        $result = $module->manuallySubscribeUser($email, $name);
        die(json_encode($result));
        
    case 'process_new_users':
        $count = $module->processNewUsers();
        die(json_encode([
            'success' => true,
            'message' => $count > 0 ? "Processed {$count} users" : "No new users to process"
        ]));
        
    case 'process_user':
        $user_id = intval($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            $success = $module->processUserById($user_id);
            die(json_encode([
                'success' => $success,
                'message' => $success ? 'User processed successfully' : 'Failed to process user'
            ]));
        }
        die(json_encode(['success' => false, 'message' => 'Invalid user ID']));
        
    case 'reset_processed':
        $success = $module->resetProcessedUsers();
        die(json_encode([
            'success' => $success,
            'message' => $success ? 'All users marked as unprocessed' : 'Reset failed'
        ]));
        
    case 'clear_logs':
        $success = $module->clearLogs();
        die(json_encode([
            'success' => $success,
            'message' => $success ? 'Logs cleared successfully' : 'Failed to clear logs'
        ]));
        
    case 'save_settings':
        $settings = $module->sanitizeSettings($_POST);
        $success = $module->saveSettings($settings);
        die(json_encode([
            'success' => $success,
            'message' => $success ? 'Settings saved successfully' : 'Failed to save settings'
        ]));
        
    case 'get_status':
        $unprocessed = $module->getUnprocessedUsersCount();
        $settings = $module->getSettings();
        die(json_encode([
            'success' => true,
            'unprocessed_count' => $unprocessed,
            'module_enabled' => $settings['enabled'],
            'auto_subscribe_enabled' => $settings['auto_subscribe_new_users']
        ]));
        
    default:
        die(json_encode(['success' => false, 'message' => 'Invalid action']));
}