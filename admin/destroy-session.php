<?php
/**
 * Direct Session Destroyer - No redirects, just destroy session
 */
session_start();

// Show what we have before
echo "<!DOCTYPE html><html><head><title>Session Destroyer</title></head><body>";
echo "<h1>🧨 Session Destroyer</h1>";

echo "<h2>BEFORE Destruction:</h2>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not Active') . "</p>";
echo "<p><strong>Session Contents:</strong></p>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Test 1: Can we even modify a session variable?
echo "<h2>TEST 1: Modifying Session</h2>";
$_SESSION['test_modification'] = 'MODIFIED_' . time();
echo "<p>✅ Added test_modification to session</p>";
echo "<p><strong>Session now contains:</strong></p>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Test 2: Can we unset a single variable?
echo "<h2>TEST 2: Unsetting Single Variable</h2>";
if (isset($_SESSION['admin_name'])) {
    $old_name = $_SESSION['admin_name'];
    unset($_SESSION['admin_name']);
    echo "<p>✅ Unset admin_name (was: $old_name)</p>";
} else {
    echo "<p>❌ admin_name not found</p>";
}
echo "<p><strong>Session after unset:</strong></p>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Test 3: Can we clear the entire session array?
echo "<h2>TEST 3: Clearing Session Array</h2>";
$_SESSION = array();
echo "<p>✅ Set \$_SESSION = array()</p>";
echo "<p><strong>Session after clearing:</strong></p>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Test 4: Can we destroy the session?
echo "<h2>TEST 4: Destroying Session</h2>";
$old_session_id = session_id();
echo "<p>Old Session ID: $old_session_id</p>";

session_destroy();
echo "<p>✅ Called session_destroy()</p>";

// Start new session
session_start();
$new_session_id = session_id();
echo "<p>New Session ID: $new_session_id</p>";
echo "<p><strong>ID Changed:</strong> " . ($old_session_id !== $new_session_id ? '✅ YES' : '❌ NO') . "</p>";

echo "<p><strong>New Session Contents:</strong></p>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<h2>🎯 Results:</h2>";
if (empty($_SESSION)) {
    echo "<p style='color:green;font-weight:bold;'>✅ SUCCESS: Session is now empty!</p>";
} else {
    echo "<p style='color:red;font-weight:bold;'>❌ FAILED: Session still contains data</p>";
}

if ($old_session_id !== $new_session_id) {
    echo "<p style='color:green;font-weight:bold;'>✅ SUCCESS: Session ID changed</p>";
} else {
    echo "<p style='color:red;font-weight:bold;'>❌ FAILED: Session ID did not change</p>";
}

echo "<h2>🔧 Next Steps:</h2>";
echo "<ol>";
echo "<li><a href='session-debug.php'>Check Session Debug</a> - See if changes persisted</li>";
echo "<li><a href='dashboard.php'>Try Dashboard</a> - Should redirect to login if session destroyed</li>";
echo "<li><a href='destroy-session.php'>Run This Test Again</a> - See if it works consistently</li>";
echo "</ol>";

echo "</body></html>";
?>