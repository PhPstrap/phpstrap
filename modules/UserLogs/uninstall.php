<?php
// modules/UserLogs/uninstall.php
require_once __DIR__ . '/../../config/database.php';
try {
    $pdo = getDbConnection();
    $pdo->exec("DROP TABLE IF EXISTS user_logs");
    return true;
} catch (Throwable $e) {
    error_log('UserLogs uninstall failed: ' . $e->getMessage());
    return false;
}