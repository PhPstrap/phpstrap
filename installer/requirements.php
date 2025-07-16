<?php
/**
 * PhPstrap Installer - System Requirements Checker
 * Save as: installer/requirements.php
 */

function checkSystemRequirements() {
    $requirements = array(
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'OpenSSL Extension' => extension_loaded('openssl'),
        'JSON Extension' => extension_loaded('json'),
        'cURL Extension' => extension_loaded('curl'),
        'Directory is writable' => is_writable('.'),
        'Config directory can be created' => is_writable('.') || is_dir('config'),
        'GD Extension (optional)' => extension_loaded('gd'),
        'Zip Extension (optional)' => extension_loaded('zip'),
        'mbstring Extension (optional)' => extension_loaded('mbstring')
    );
    
    return $requirements;
}

function getPhpInfo() {
    return array(
        'version' => PHP_VERSION,
        'sapi' => php_sapi_name(),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Yes' : 'No',
        'date_timezone' => date_default_timezone_get(),
        'session_save_path' => session_save_path(),
        'temp_dir' => sys_get_temp_dir()
    );
}

function checkOptionalRequirements() {
    $optional = array(
        'mod_rewrite' => checkModRewrite(),
        'https_support' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'opcache' => extension_loaded('opcache'),
        'redis' => extension_loaded('redis'),
        'memcached' => extension_loaded('memcached'),
        'imagick' => extension_loaded('imagick'),
        'fileinfo' => extension_loaded('fileinfo'),
        'intl' => extension_loaded('intl')
    );
    
    return $optional;
}

function checkModRewrite() {
    // Check if mod_rewrite is available
    if (function_exists('apache_get_modules')) {
        return in_array('mod_rewrite', apache_get_modules());
    } elseif (isset($_SERVER['HTTP_MOD_REWRITE'])) {
        return $_SERVER['HTTP_MOD_REWRITE'] === 'On';
    } elseif (getenv('HTTP_MOD_REWRITE')) {
        return getenv('HTTP_MOD_REWRITE') === 'On';
    }
    
    // Try to detect via .htaccess test
    return testHtaccessRewrite();
}

function testHtaccessRewrite() {
    // Create a temporary .htaccess file to test rewrite
    $htaccess_content = "RewriteEngine On\nRewriteRule ^test-rewrite$ test-rewrite-success [L]";
    $test_file = '.htaccess_test';
    
    try {
        file_put_contents($test_file, $htaccess_content);
        
        // Test if we can access the rewrite rule
        $test_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/test-rewrite';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);
        
        $result = @file_get_contents($test_url, false, $context);
        
        // Clean up
        if (file_exists($test_file)) {
            unlink($test_file);
        }
        
        return $result !== false;
    } catch (Exception $e) {
        // Clean up on error
        if (file_exists($test_file)) {
            unlink($test_file);
        }
        return false;
    }
}

function getRecommendations() {
    $recommendations = array();
    
    // PHP Version
    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        $recommendations[] = array(
            'type' => 'upgrade',
            'title' => 'PHP Version',
            'message' => 'Consider upgrading to PHP 8.0+ for better performance and security',
            'priority' => 'medium'
        );
    }
    
    // Memory Limit
    $memory_limit = ini_get('memory_limit');
    if ($memory_limit !== '-1' && (int)$memory_limit < 256) {
        $recommendations[] = array(
            'type' => 'config',
            'title' => 'Memory Limit',
            'message' => "Current: $memory_limit. Consider increasing to 256M or higher",
            'priority' => 'medium'
        );
    }
    
    // OPCache
    if (!extension_loaded('opcache')) {
        $recommendations[] = array(
            'type' => 'extension',
            'title' => 'OPCache',
            'message' => 'Enable OPCache extension for better performance',
            'priority' => 'medium'
        );
    }
    
    // GD Extension
    if (!extension_loaded('gd')) {
        $recommendations[] = array(
            'type' => 'extension',
            'title' => 'GD Extension',
            'message' => 'Install GD extension for image processing features',
            'priority' => 'low'
        );
    }
    
    // cURL vs allow_url_fopen
    if (!extension_loaded('curl') && !ini_get('allow_url_fopen')) {
        $recommendations[] = array(
            'type' => 'config',
            'title' => 'Remote Access',
            'message' => 'Enable either cURL extension or allow_url_fopen for external API calls',
            'priority' => 'high'
        );
    }
    
    // HTTPS
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        $recommendations[] = array(
            'type' => 'security',
            'title' => 'HTTPS',
            'message' => 'Enable HTTPS/SSL for secure connections',
            'priority' => 'high'
        );
    }
    
    // File Permissions
    if (!is_writable('.')) {
        $recommendations[] = array(
            'type' => 'permissions',
            'title' => 'File Permissions',
            'message' => 'Ensure web server has write permissions to installation directory',
            'priority' => 'high'
        );
    }
    
    // Session Configuration
    if (empty(session_save_path()) || !is_writable(session_save_path())) {
        $recommendations[] = array(
            'type' => 'config',
            'title' => 'Session Storage',
            'message' => 'Check session.save_path configuration and permissions',
            'priority' => 'medium'
        );
    }
    
    // Max Execution Time
    $max_execution_time = (int)ini_get('max_execution_time');
    if ($max_execution_time > 0 && $max_execution_time < 30) {
        $recommendations[] = array(
            'type' => 'config',
            'title' => 'Execution Time',
            'message' => "Current: {$max_execution_time}s. Consider increasing to 30s or more",
            'priority' => 'low'
        );
    }
    
    return $recommendations;
}

function checkSecuritySettings() {
    $security = array(
        'display_errors' => array(
            'current' => ini_get('display_errors') ? 'On' : 'Off',
            'recommended' => 'Off',
            'status' => !ini_get('display_errors') ? 'good' : 'warning'
        ),
        'expose_php' => array(
            'current' => ini_get('expose_php') ? 'On' : 'Off',
            'recommended' => 'Off',
            'status' => !ini_get('expose_php') ? 'good' : 'warning'
        ),
        'session.cookie_httponly' => array(
            'current' => ini_get('session.cookie_httponly') ? 'On' : 'Off',
            'recommended' => 'On',
            'status' => ini_get('session.cookie_httponly') ? 'good' : 'warning'
        ),
        'session.use_only_cookies' => array(
            'current' => ini_get('session.use_only_cookies') ? 'On' : 'Off',
            'recommended' => 'On',
            'status' => ini_get('session.use_only_cookies') ? 'good' : 'warning'
        )
    );
    
    return $security;
}

function getSystemInfo() {
    $info = array(
        'os' => PHP_OS,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'server_admin' => $_SERVER['SERVER_ADMIN'] ?? 'Unknown',
        'loaded_extensions' => get_loaded_extensions(),
        'php_ini_path' => php_ini_loaded_file(),
        'include_path' => ini_get('include_path'),
        'open_basedir' => ini_get('open_basedir') ?: 'Not set',
        'disable_functions' => ini_get('disable_functions') ?: 'None'
    );
    
    return $info;
}

function checkDiskSpace() {
    $required_space = 50 * 1024 * 1024; // 50MB minimum
    $available_space = disk_free_space('.');
    
    return array(
        'required' => formatBytes($required_space),
        'available' => formatBytes($available_space),
        'sufficient' => $available_space >= $required_space
    );
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function checkWritableDirectories() {
    $directories = array('.', 'config', 'logs', 'uploads', 'cache');
    $results = array();
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            // Try to create the directory
            $created = @mkdir($dir, 0755, true);
            $results[$dir] = array(
                'exists' => $created,
                'writable' => $created ? is_writable($dir) : false,
                'created' => $created
            );
        } else {
            $results[$dir] = array(
                'exists' => true,
                'writable' => is_writable($dir),
                'created' => false
            );
        }
    }
    
    return $results;
}

function testDatabaseConnectivity() {
    // This is a basic test to see if we can even load the PDO MySQL driver
    try {
        $pdo = new PDO('mysql:host=localhost', '', '', array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ));
        return array('status' => true, 'message' => 'PDO MySQL driver is functional');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            return array('status' => true, 'message' => 'PDO MySQL driver is available (credentials needed)');
        }
        return array('status' => false, 'message' => 'PDO MySQL driver issue: ' . $e->getMessage());
    }
}

function generateRequirementsReport() {
    $report = array(
        'php_info' => getPhpInfo(),
        'requirements' => checkSystemRequirements(),
        'optional' => checkOptionalRequirements(),
        'recommendations' => getRecommendations(),
        'security' => checkSecuritySettings(),
        'system_info' => getSystemInfo(),
        'disk_space' => checkDiskSpace(),
        'writable_dirs' => checkWritableDirectories(),
        'database_test' => testDatabaseConnectivity(),
        'timestamp' => date('Y-m-d H:i:s')
    );
    
    return $report;
}

function exportRequirementsReport($format = 'json') {
    $report = generateRequirementsReport();
    
    switch ($format) {
        case 'json':
            return json_encode($report, JSON_PRETTY_PRINT);
        case 'text':
            return formatReportAsText($report);
        default:
            return $report;
    }
}

function formatReportAsText($report) {
    $text = "BootPHP System Requirements Report\n";
    $text .= "Generated: " . $report['timestamp'] . "\n";
    $text .= str_repeat("=", 50) . "\n\n";
    
    $text .= "PHP Information:\n";
    foreach ($report['php_info'] as $key => $value) {
        $text .= "  " . ucwords(str_replace('_', ' ', $key)) . ": $value\n";
    }
    
    $text .= "\nSystem Requirements:\n";
    foreach ($report['requirements'] as $req => $status) {
        $text .= "  [" . ($status ? 'PASS' : 'FAIL') . "] $req\n";
    }
    
    $text .= "\nRecommendations:\n";
    foreach ($report['recommendations'] as $rec) {
        $text .= "  - " . $rec['title'] . ": " . $rec['message'] . "\n";
    }
    
    return $text;
}
?>