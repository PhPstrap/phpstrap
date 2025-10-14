<?php
/**
 * TEST VERSION - Remove after testing!
 * This version has no authentication for debugging
 */

// Include the module
require_once __DIR__ . '/SendyModule.php';
$module = new \PhPstrap\Modules\Sendy\SendyModule();
$module->init();

// Get current status
$settings = $module->getSettings();
$unprocessed = $module->getUnprocessedUsersCount();

// Show current configuration
echo "<h2>Sendy Module Status</h2>";
echo "<pre>";
echo "Module Enabled: " . ($settings['enabled'] ? 'YES' : 'NO') . "\n";
echo "Auto-Subscribe Enabled: " . ($settings['auto_subscribe_new_users'] ? 'YES' : 'NO') . "\n";
echo "Sendy URL: " . (!empty($settings['sendy_url']) ? 'SET' : 'NOT SET') . "\n";
echo "API Key: " . (!empty($settings['sendy_api_key']) ? 'SET' : 'NOT SET') . "\n";
echo "Unprocessed Users: " . $unprocessed . "\n";
echo "Secret Key: " . ($settings['check_key'] ?? 'NOT SET') . "\n";

// Count configured lists
$lists_configured = 0;
for ($i = 1; $i <= 10; $i++) {
    if ($settings['list_' . $i . '_enabled'] && 
        $settings['list_' . $i . '_auto_subscribe'] &&
        !empty($settings['list_' . $i . '_id'])) {
        $lists_configured++;
        echo "List $i: " . $settings['list_' . $i . '_name'] . " (ID: " . $settings['list_' . $i . '_id'] . ")\n";
    }
}
echo "Lists Configured: " . $lists_configured . "\n";
echo "</pre>";

// Process button
if (isset($_GET['process'])) {
    echo "<h3>Processing...</h3>";
    $processed = $module->processNewUsers();
    echo "<p>Processed $processed users</p>";
}

// Show process button if there are unprocessed users
if ($unprocessed > 0) {
    echo "<p><a href='?process=1' class='button'>Process $unprocessed Users Now</a></p>";
}

// Test subscription form
?>
<h3>Test Subscription</h3>
<form method="post">
    Email: <input type="email" name="test_email" required><br>
    Name: <input type="text" name="test_name"><br>
    <input type="submit" name="test" value="Test Subscribe">
</form>

<?php
if (isset($_POST['test'])) {
    $result = $module->manuallySubscribeUser($_POST['test_email'], $_POST['test_name'] ?? '');
    echo "<h4>Test Result:</h4>";
    echo "<pre>" . print_r($result, true) . "</pre>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
.button { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; }
.button:hover { background: #005a87; }
</style>