<?php
// includes/footer-scripts.php
// Injects custom JS from database settings, if available

if (!function_exists('__safe_include_footer')) {
    function __safe_include_footer($path) {
        try {
            if (is_file($path)) { require_once $path; return true; }
        } catch (Throwable $e) {
            error_log("Footer include failed: $path :: " . $e->getMessage());
        }
        return false;
    }
}

// Bootstrap Settings if not already loaded
$__settings_ready_footer = false;
if (class_exists('Settings')) {
    $__settings_ready_footer = true;
} else {
    __safe_include_footer(__DIR__ . '/../config/database.php');
    if (__safe_include_footer(__DIR__ . '/settings.php') && class_exists('Settings')) {
        try {
            Settings::get('site_name', 'PhPstrap'); // trigger load
            $__settings_ready_footer = true;
        } catch (Throwable $e) {
            error_log("Settings bootstrap failed in footer: " . $e->getMessage());
        }
    }
}

$customJS = $__settings_ready_footer ? (Settings::get('custom_js', '') ?? '') : '';

// Inject custom JS block if present
if (!empty($customJS)): ?>
<script id="custom-js">
<?= $customJS . "\n" ?>
</script>
<?php endif; ?>