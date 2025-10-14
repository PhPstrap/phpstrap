<?php
// admin/includes/admin-sidebar.php
// Renders the sidebar menu (no wrapper markup).
// Expects optional $activeKey (string) and $sidebarBadges (array: key => int).

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Candidate items in the order you want to show them.
$candidates = [
  'dashboard'      => ['label' => 'Dashboard',       'icon' => 'fa-solid fa-gauge',            'href' => 'dashboard.php'],
  'system-check'   => ['label' => 'System Check',    'icon' => 'fa-solid fa-stethoscope',      'href' => 'system-check.php'],
  'update'         => ['label' => 'Updater',         'icon' => 'fa-solid fa-code-branch',      'href' => 'update.php'],
  'modules'        => ['label' => 'Modules',         'icon' => 'fa-solid fa-puzzle-piece',     'href' => 'modules.php'],
  'cleanup'        => ['label' => 'System Cleanup',  'icon' => 'fa-solid fa-broom',            'href' => 'cleanup.php'],
  'users'          => ['label' => 'Users',           'icon' => 'fa-solid fa-users',            'href' => 'users.php'],
  'invites'        => ['label' => 'Invites',         'icon' => 'fa-solid fa-ticket',           'href' => 'invites.php'],
  'logs'           => ['label' => 'Admin Logs',      'icon' => 'fa-solid fa-clipboard-list',   'href' => 'logs.php'],
  'user-logs'      => ['label' => 'User Logs',       'icon' => 'fa-regular fa-address-card',   'href' => 'user-logs.php'],
  'module-logs'    => ['label' => 'Module Logs',     'icon' => 'fa-solid fa-puzzle-piece',     'href' => 'module-logs.php'],
  'affiliate-logs' => ['label' => 'Affiliate Logs',  'icon' => 'fa-solid fa-handshake',        'href' => 'affiliate-logs.php'],
  'server-metrics' => ['label' => 'Server Metrics',  'icon' => 'fa-solid fa-chart-line',       'href' => 'server-metrics.php'],
  'settings'       => ['label' => 'Settings',        'icon' => 'fa-solid fa-gear',             'href' => 'settings.php'],
  'logout'         => ['label' => 'Logout',          'icon' => 'fa-solid fa-right-from-bracket','href' => 'logout.php'],
];

// Only keep items whose target file exists under /admin
$menu = [];
$adminDir = dirname(__DIR__); // /admin
foreach ($candidates as $key => $item) {
  $target = $adminDir . '/' . ltrim($item['href'], '/');
  if (is_file($target)) {
    $menu[$key] = $item;
  }
}

echo '<nav class="list-group list-group-flush">';
foreach ($menu as $key => $item) {
  $active = isset($activeKey) && $activeKey === $key ? ' active' : '';
  $badge  = isset($sidebarBadges[$key]) ? '<span class="badge rounded-pill text-bg-secondary ms-2">'.(int)$sidebarBadges[$key].'</span>' : '';
  echo '<a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between'
     . $active . ' bg-dark text-white border-secondary-subtle" href="'.h($item['href']).'">';
  echo '  <span><i class="'.h($item['icon']).' me-2"></i>'.h($item['label']).'</span>';
  echo    $badge;
  echo '</a>';
}
echo '</nav>';