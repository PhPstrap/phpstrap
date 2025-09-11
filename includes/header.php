<?php
// --- Safe includes -----------------------------------------------------------
$__settings_error = null;
$__settings_ready = false;

function __safe_include($path) {
    try {
        if (is_file($path)) { require_once $path; return true; }
    } catch (Throwable $e) { error_log("Include failed: $path :: " . $e->getMessage()); }
    return false;
}

// Include DB first (Settings depends on it)
__safe_include(__DIR__ . '/../config/database.php');

// Include the Settings helper if available
if (__safe_include(__DIR__ . '/../includes/settings.php') && class_exists('Settings')) {
    try {
        // touch settings so it loads once (internally cached by the class)
        Settings::get('site_name', 'PhPstrap');
        $__settings_ready = true;
    } catch (Throwable $e) {
        $__settings_error = $e->getMessage();
        $__settings_ready = false;
    }
}

// --- Read settings with fallbacks -------------------------------------------
$siteName   = $__settings_ready ? Settings::get('site_name', 'PhPstrap') : 'PhPstrap';
$faviconUrl = $__settings_ready ? Settings::get('favicon_url', '/favicon.ico') : '/favicon.ico';
$themeColor = $__settings_ready ? Settings::get('theme_color', '#007bff') : '#007bff';
$logoUrl    = $__settings_ready ? Settings::get('logo_url', '') : '';
$customCSS  = $__settings_ready ? (Settings::get('custom_css', '') ?? '') : '';

// Page meta provided by the page (with safe fallbacks)
$pageTitle       = isset($pageTitle) ? $pageTitle : $siteName;
$metaDescription = isset($metaDescription) ? $metaDescription : '';
$metaKeywords    = isset($metaKeywords) ? $metaKeywords : '';
$metaOgImage     = isset($metaOgImage) ? $metaOgImage : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
  <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
  <meta name="theme-color" content="<?= htmlspecialchars($themeColor) ?>">

  <?php if (!empty($faviconUrl)): ?>
    <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>">
  <?php endif; ?>

  <!-- Open Graph (optional) -->
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
  <?php if (!empty($metaOgImage)): ?>
    <meta property="og:image" content="<?= htmlspecialchars($metaOgImage) ?>">
  <?php endif; ?>

  <!-- Vendor CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/custom.css">

  <?php
  // header-scripts.php is optional; include if present (prevents 500s if missing)
  $headerScriptsPath = __DIR__ . '/header-scripts.php';
  if (is_file($headerScriptsPath)) {
      include $headerScriptsPath;
  }
  ?>

  <!-- Custom CSS from settings -->
  <?php if (!empty($customCSS)): ?>
    <style id="custom-css">
<?= $customCSS . "\n" ?>
    </style>
  <?php endif; ?>
</head>
<body>

<?php
// nav.php may expect $user/$settings; include defensively.
$navPath = __DIR__ . '/nav.php';
if (is_file($navPath)) {
    // If your nav expects $settings as array, you can pass a minimal map from Settings:
    $settings = [
        'affiliate_program_enabled' => $__settings_ready ? (Settings::get('affiliate_program_enabled', '0') ? '1' : '0') : '0',
        'api_enabled'               => $__settings_ready ? (Settings::get('api_enabled', '0') ? '1' : '0') : '0',
        'site_icon'                 => $__settings_ready ? (Settings::get('site_icon', 'bi bi-speedometer2')) : 'bi bi-speedometer2',
    ];
    include $navPath;
}
?>