<?php
// admin/includes/admin-sidebar.php
// Renders the sidebar menu (no wrapper markup).
// Expects optional $activeKey (string) and $sidebarBadges (array: key => int).

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$menu = [
  'dashboard' => ['label' => 'Dashboard',       'icon' => 'fa-solid fa-gauge',            'href' => 'dashboard.php'],
  'cleanup'   => ['label' => 'System Cleanup',  'icon' => 'fa-solid fa-broom',            'href' => 'cleanup.php'],
  'users'     => ['label' => 'Users',           'icon' => 'fa-solid fa-users',            'href' => 'users.php'],
  'invites'   => ['label' => 'Invites',         'icon' => 'fa-solid fa-ticket',           'href' => 'invites.php'],
  'logs'      => ['label' => 'Admin Logs',      'icon' => 'fa-solid fa-clipboard-list',   'href' => 'logs.php'],
  'settings'  => ['label' => 'Settings',        'icon' => 'fa-solid fa-gear',             'href' => 'settings.php'],
  'logout'    => ['label' => 'Logout',          'icon' => 'fa-solid fa-right-from-bracket','href' => 'logout.php'],
];

// You can unset items if you don't have those pages yet, e.g.:
// unset($menu['invites'], $menu['logs']);

echo '<nav class="list-group list-group-flush">';
foreach ($menu as $key => $item) {
  $active = isset($activeKey) && $activeKey === $key ? ' active' : '';
  $badge  = isset($sidebarBadges[$key]) ? '<span class="badge rounded-pill text-bg-secondary ms-2">'.(int)$sidebarBadges[$key].'</span>' : '';

  // Dark sidebar friendly styles; relies on /assets/css/admin.css to tweak active state on dark backgrounds.
  echo '<a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between'.
       $active.' bg-dark text-white border-secondary-subtle" href="'.h($item['href']).'">';
  echo '  <span><i class="'.h($item['icon']).' me-2"></i>'.h($item['label']).'</span>';
  echo '  '.$badge;
  echo '</a>';
}
echo '</nav>';