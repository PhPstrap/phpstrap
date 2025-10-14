<?php
// modules/UserLogs/helpers.php

if (!function_exists('logUserActivity')) {
    /**
     * Log a user event.
     * @param string $action e.g. 'login','logout','register','profile_update'
     * @param int|null $userId the user id (nullable for anonymous)
     */
    function logUserActivity(string $action, ?int $userId = null): void {
        try {
            if (!function_exists('getDbConnection')) return;
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                INSERT INTO user_logs (user_id, action, ip_address, user_agent)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Throwable $e) {
            error_log('User log insert failed: ' . $e->getMessage());
        }
    }
}