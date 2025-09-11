<?php
/**
 * dashboard/nav.php
 * Reusable dashboard navigation (desktop sidebar + mobile offcanvas)
 * Expects: $user (array), optional $settings (array), optional $currentPage (string)
 */

if (!isset($user)) { return; }

// Ensure $settings is an array
$settings = is_array($settings ?? null) ? $settings : [];

/** Treat common truthy shapes as enabled */
function setting_on(array $settings, string $key): bool {
    if (!array_key_exists($key, $settings)) return false;
    $v = $settings[$key];
    // Normalize booleans/ints/strings: 1, '1', true, 'true', 'on', 'yes'
    return in_array($v, [1, '1', true, 'true', 'on', 'yes'], true);
}

/** Optional: hydrate just the needed flags if not provided */
if ((!array_key_exists('affiliate_program_enabled', $settings) || !array_key_exists('api_enabled', $settings))) {
    if (function_exists('getSetting')) {
        $settings['affiliate_program_enabled'] = $settings['affiliate_program_enabled'] ?? getSetting('affiliate_program_enabled', '0');
        $settings['api_enabled']               = $settings['api_enabled']               ?? getSetting('api_enabled', '0');
    }
}

// Current page resolver (fallback)
$currentPage = $currentPage ?? (function () {
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $last = strtolower(basename($path));
    if ($last === '' || $last === 'dashboard' || $last === 'index.php') return 'overview';
    return preg_replace('/\.php$/i', '', $last);
})();

// Base items
$items = [
    ['key' => 'overview', 'title' => 'Overview', 'icon' => 'bi-house',       'href' => './'],
    ['key' => 'profile',  'title' => 'Profile',  'icon' => 'bi-person',      'href' => 'profile.php'],
];

// Feature-gated items (from settings table)
if (setting_on($settings, 'affiliate_program_enabled')) {
    $items[] = ['key' => 'affiliate', 'title' => 'Affiliate', 'icon' => 'bi-share',       'href' => 'affiliate.php'];
}
if (setting_on($settings, 'api_enabled')) {
    $items[] = ['key' => 'api',       'title' => 'API Access','icon' => 'bi-code-slash',  'href' => 'api.php'];
}

// Always-visible items
$items = array_merge($items, [
    ['key' => 'billing',  'title' => 'Billing',  'icon' => 'bi-credit-card',       'href' => 'billing.php'],
    ['key' => 'settings', 'title' => 'Settings', 'icon' => 'bi-gear',              'href' => 'settings.php'],
    ['key' => 'divider'],
    ['key' => 'support',  'title' => 'Support',  'icon' => 'bi-question-circle',   'href' => 'support.php'],
]);

// Admin link
if (!empty($user['is_admin'])) {
    $items[] = ['key' => 'admin', 'title' => 'Admin Panel', 'icon' => 'bi-shield-lock', 'href' => '../admin/'];
}

// Logout
$items[] = ['key' => 'logout', 'title' => 'Logout', 'icon' => 'bi-box-arrow-right', 'href' => '../login/logout.php'];

// Branding icon: your settings table stores Font Awesome class (e.g., "fas fa-home")
// We output it as-is in the title (no "bi" prefix), and use Bootstrap Icons for menu rows.
$siteIcon = $settings['site_icon'] ?? 'fas fa-home';
?>

<!-- Mobile Offcanvas -->
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
  <div class="offcanvas-header bg-primary text-white">
    <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">
      <i class="<?= htmlspecialchars($siteIcon) ?> me-2"></i> Dashboard
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
    <nav class="nav flex-column py-3">
      <?php foreach ($items as $it): ?>
        <?php if (($it['key'] ?? '') === 'divider'): ?>
          <hr class="my-2">
          <?php continue; ?>
        <?php endif; ?>
        <?php
          $active = ($currentPage === ($it['key'] ?? ''));
          $href   = $it['href'] ?? '#';
          $icon   = $it['icon'] ?? 'bi-circle';
          $title  = $it['title'] ?? '';
        ?>
        <a class="nav-link px-3 py-2 <?= $active ? 'active fw-semibold' : 'text-body' ?>" href="<?= htmlspecialchars($href) ?>">
          <i class="bi <?= htmlspecialchars($icon) ?> me-2"></i><?= htmlspecialchars($title) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>

<!-- Desktop Sidebar -->
<aside class="d-none d-lg-block col-lg-2 px-0 border-end bg-white">
  <div class="p-4">
    <div class="d-flex align-items-center mb-3">
      <i class="<?= htmlspecialchars($siteIcon) ?> me-2 text-primary"></i>
      <strong class="text-primary mb-0">Dashboard</strong>
    </div>
    <nav class="nav flex-column">
      <?php foreach ($items as $it): ?>
        <?php if (($it['key'] ?? '') === 'divider'): ?>
          <hr class="my-2">
          <?php continue; ?>
        <?php endif; ?>
        <?php
          $active = ($currentPage === ($it['key'] ?? ''));
          $href   = $it['href'] ?? '#';
          $icon   = $it['icon'] ?? 'bi-circle';
          $title  = $it['title'] ?? '';
        ?>
        <a class="nav-link px-3 py-2 rounded <?= $active ? 'bg-light fw-semibold' : 'text-body' ?>" href="<?= htmlspecialchars($href) ?>">
          <i class="bi <?= htmlspecialchars($icon) ?> me-2"></i><?= htmlspecialchars($title) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</aside>