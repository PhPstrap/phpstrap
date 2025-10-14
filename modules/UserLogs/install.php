<?php
declare(strict_types=1);

/**
 * UserLogs installer (robust pathing + verification)
 * - Include and call phpstrap_userlogs_install()
 * - Or run directly: /admin/modules/userlogs/install.php
 */

// ---- Locate /config/database.php by walking up the tree ----
(function () {
    $maxUp = 6;
    $dir = __DIR__;
    for ($i = 0; $i <= $maxUp; $i++) {
        $candidate = $dir . '/config/database.php';
        if (is_file($candidate)) { require_once $candidate; return; }
        // also try sibling when modules live under /admin/modules/...
        $candidate2 = $dir . '/../../config/database.php';
        if (is_file($candidate2)) { require_once $candidate2; return; }
        $dir = dirname($dir);
    }
    throw new RuntimeException('config/database.php not found from installer.');
})();

/**
 * Run install. Returns [bool success, string message].
 */
function phpstrap_userlogs_install(): array
{
    try {
        if (!function_exists('getDbConnection')) {
            return [false, 'getDbConnection() not found (ensure config/database.php is included)'];
        }

        /** @var PDO $pdo */
        $pdo = getDbConnection();
        if (!$pdo instanceof PDO) {
            return [false, 'DB connection failed (no PDO instance)'];
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create user_logs table
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `user_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(64) NOT NULL,
  `meta` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`, `created_at`),
  KEY `idx_action_created` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        // Optional: module_migrations record
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `module_migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(100) NOT NULL,
  `version` VARCHAR(20) NOT NULL,
  `ran_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mod_ver` (`module`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
        $ins = $pdo->prepare("INSERT IGNORE INTO module_migrations (module, version) VALUES (?, ?)");
        $ins->execute(['userlogs', '1.0.0']);

        // Verify table presence and write perms
        $chk = $pdo->query("SHOW TABLES LIKE 'user_logs'");
        if (!$chk || !$chk->rowCount()) {
            return [false, "Verification failed: 'user_logs' not visible to user"];
        }

        // Smoke insert/delete
        $pdo->exec("INSERT INTO user_logs (user_id, action, meta) VALUES (NULL, 'install_smoke', JSON_OBJECT())");
        $pdo->exec("DELETE FROM user_logs WHERE action='install_smoke'");

        return [true, 'UserLogs installed: user_logs table ready'];
    } catch (Throwable $e) {
        error_log('UserLogs install error: ' . $e->getMessage());
        return [false, 'Install error: ' . $e->getMessage()];
    }
}

// ---- Auto-run when accessed directly (browser/CLI) ----
if (php_sapi_name() === 'cli' || (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    [$ok, $msg] = phpstrap_userlogs_install();
    header('Content-Type: text/plain; charset=utf-8');
    echo ($ok ? 'SUCCESS: ' : 'FAIL: ') . $msg . PHP_EOL;
}