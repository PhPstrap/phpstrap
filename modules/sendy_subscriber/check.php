<?php
/**
 * Simple endpoint to process new users
 * Can be called from anywhere: browser, API, or included in other PHP files
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include the module
require_once __DIR__ . '/SendyModule.php';
$module = new \PhPstrap\Modules\Sendy\SendyModule();
$module->init();

// Get settings to check the key
$settings = $module->getSettings();

// Security check
$allowed = false;
$auth_method = '';

// Method 1: Allow if called from CLI
if (php_sapi_name() === 'cli') {
    $allowed = true;
    $auth_method = 'CLI';
}

// Method 2: Allow if admin user
if (!$allowed && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    $allowed = true;
    $auth_method = 'Admin Session';
}

// Method 3: Allow with secret key (GET or POST)
$provided_key = $_GET['key'] ?? $_POST['key'] ?? $_REQUEST['key'] ?? '';
if (!$allowed && !empty($provided_key) && !empty($settings['check_key'])) {
    if ($provided_key === $settings['check_key']) {
        $allowed = true;
        $auth_method = 'Secret Key';
    } else {
        // Key provided but incorrect
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'error' => 'Invalid key',
            'message' => 'The provided key is incorrect. Check your admin settings for the correct key.'
        ]));
    }
}

// Method 4: Allow if no key is set in settings (first time setup)
if (!$allowed && empty($settings['check_key'])) {
    $allowed = true;
    $auth_method = 'No Key Set (Please configure in admin)';
}

// If still not allowed, show helpful error
if (!$allowed) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Access denied. You need to either:',
        'options' => [
            '1. Be logged in as admin',
            '2. Provide the secret key: ?key=YOUR_SECRET_KEY',
            '3. Call this script from command line'
        ],
        'hint' => 'Find your secret key in the Sendy module admin settings'
    ]));
}

// Check if module is enabled
if (!$settings['enabled']) {
    die(json_encode([
        'success' => false,
        'error' => 'Module disabled',
        'message' => 'The Sendy module is currently disabled. Enable it in admin settings.'
    ]));
}

// Check if auto-subscribe is enabled
if (!$settings['auto_subscribe_new_users']) {
    die(json_encode([
        'success' => false,
        'error' => 'Auto-subscribe disabled',
        'message' => 'Auto-subscribe is disabled. Enable it in admin settings.'
    ]));
}

// Check if Sendy is configured
if (empty($settings['sendy_url']) || empty($settings['sendy_api_key'])) {
    die(json_encode([
        'success' => false,
        'error' => 'Sendy not configured',
        'message' => 'Please configure your Sendy URL and API key in admin settings.'
    ]));
}

// Process new users
$result = [
    'success' => true,
    'auth_method' => $auth_method,
    'unprocessed_before' => $module->getUnprocessedUsersCount(),
    'processed' => 0
];

if ($result['unprocessed_before'] > 0) {
    $processed = $module->processNewUsers();
    $result['processed'] = $processed;
    $result['unprocessed_after'] = $module->getUnprocessedUsersCount();
    $result['message'] = "Successfully processed {$processed} users";
} else {
    $result['message'] = "No new users to process";
}

// Add configuration info for debugging
$result['config'] = [
    'module_enabled' => $settings['enabled'],
    'auto_subscribe_enabled' => $settings['auto_subscribe_new_users'],
    'sendy_configured' => !empty($settings['sendy_url']) && !empty($settings['sendy_api_key']),
    'lists_configured' => 0
];

// Count configured lists
for ($i = 1; $i <= 10; $i++) {
    if ($settings['list_' . $i . '_enabled'] && 
        $settings['list_' . $i . '_auto_subscribe'] &&
        !empty($settings['list_' . $i . '_id'])) {
        $result['config']['lists_configured']++;
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);