<?php
/**
 * Reusable Admin Sidebar (grouped + collapsible)
 * Usage (in any admin page):
 *   $appVersion = $system_info['app_version'] ?? '1.0.0';
 *   $adminName  = $admin['name'] ?? 'Admin';
 *   include __DIR__ . '/includes/admin-sidebar.php';
 */

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('phps_current_admin_page_key')) {
    function phps_current_admin_page_key(): string {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $base = strtolower(basename($path));
        $key  = preg_replace('/\.php$/i', '', $base);
        if ($key === '' || $key === 'index') $key = 'dashboard';
        return $key;
    }
}

if (!function_exists('phps_load_admin_menu')) {
    function phps_load_admin_menu(): array {
        // Allow an override file to define renderAdminMenuStructured() or ADMIN_MENU_STRUCTURE
        $paths = [
            __DIR__ . '/../admin-menu.php',
            __DIR__ . '/admin-menu.php',
            dirname(__DIR__) . '/includes/admin-menu.php',
        ];
        foreach ($paths as $p) { if (is_file($p)) { @require_once $p; } }

        if (function_exists('renderAdminMenuStructured')) {
            $m = renderAdminMenuStructured();
            if (is_array($m) && $m) return $m;
        }
        foreach (['ADMIN_MENU_STRUCTURE','ADMIN_MENU','admin_menu'] as $var) {
            if (isset($$var) && is_array($$var) && $$var) return $$var;
        }

        // Fallback (only if target files likely exist)
        $maybe = fn($file) => is_file(__DIR__ . '/../' . $file);

        $invitesItem = $maybe('invites.php')
            ? [['key'=>'invites','title'=>'Invites','icon'=>'fa-solid fa-ticket','url'=>'invites.php']]
            : [];

        return [
            [
                'key'   => 'overview',
                'title' => 'Overview',
                'icon'  => 'fa-solid fa-gauge',
                'items' => [
                    ['key'=>'dashboard','title'=>'Dashboard','icon'=>'fa-solid fa-gauge','url'=>'dashboard.php'],
                ],
            ],
            [
                'key'   => 'maintenance',
                'title' => 'Maintenance',
                'icon'  => 'fa-solid fa-broom',
                'items' => [
                    ['key'=>'cleanup','title'=>'System Cleanup','icon'=>'fa-solid fa-broom','url'=>'cleanup.php','badge'=>'New'],
                    // Add later when you have a page:
                    // ['key'=>'admin-logs','title'=>'Admin Logs','icon'=>'fa-regular fa-clipboard','url'=>'admin-logs.php'],
                ],
            ],
            [
                'key'   => 'users',
                'title' => 'Users',
                'icon'  => 'fa-solid fa-users',
                'items' => array_merge(
                    [['key'=>'users','title'=>'All Users','icon'=>'fa-solid fa-users','url'=>'users.php']],
                    $invitesItem
                ),
            ],
            [
                'key'   => 'system',
                'title' => 'System',
                'icon'  => 'fa-solid fa-gear',
                'items' => [
                    ['key'=>'modules','title'=>'Modules','icon'=>'fa-solid fa-puzzle-piece','url'=>'modules.php'],
                    ['key'=>'settings','title'=>'Settings','icon'=>'fa-solid fa-gear','url'=>'settings.php'],
                ],
            ],
            [
                'key'   => 'account',
                'title' => 'Account',
                'icon'  => 'fa-regular fa-user',
                'items' => [
                    ['key'=>'logout','title'=>'Logout','icon'=>'fa-solid fa-right-from-bracket','url'=>'logout.php'],
                ],
            ],
        ];
    }
}

if (!function_exists('phps_render_admin_sidebar')) {
    function phps_render_admin_sidebar(array $menu, string $activeKey, string $appVersion, string $adminName): string {
        // prune non-existing items if a key 'exists' is supplied
        foreach ($menu as &$g) {
            if (!empty($g['items'])) {
                $g['items'] = array_values(array_filter($g['items'], function($it){
                    return !isset($it['exists']) || $it['exists'];
                }));
            }
        }
        unset($g);

        ob_start(); ?>
        <aside class="admin-sidebar bg-dark text-white">
          <div class="p-3 border-bottom border-secondary-subtle d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
              <i class="fa-solid fa-shield-halved me-2"></i>
              <strong>PhPstrap Admin</strong>
            </div>
            <button class="btn btn-sm btn-outline-light d-lg-none" id="btnSidebarToggle" aria-label="Toggle sidebar">
              <i class="fa-solid fa-bars"></i>
            </button>
          </div>

          <div class="px-3 py-2 small text-secondary">
            v<?= h($appVersion) ?> · <?= h($adminName ?: 'Admin') ?>
          </div>

          <nav class="sidebar-groups" id="sidebarGroups" data-bs-theme="dark">
            <?php foreach ($menu as $i => $group):
              $gid = 'grp-' . preg_replace('/[^a-z0-9_-]/i','', $group['key'] ?? ('g'.$i));
              $anyActive = false;
              foreach ($group['items'] as $it) { if (($it['key'] ?? '') === $activeKey) { $anyActive = true; break; } }
              ?>
              <div class="sidebar-group">
                <button class="group-toggle btn w-100 d-flex align-items-center justify-content-between"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?= $gid ?>"
                        aria-expanded="<?= $anyActive ? 'true':'false' ?>"
                        aria-controls="<?= $gid ?>"
                        data-group-key="<?= $gid ?>">
                  <span class="d-flex align-items-center">
                    <i class="<?= h($group['icon'] ?? 'fa-solid fa-circle') ?> me-2"></i>
                    <span class="text-start"><?= h($group['title'] ?? 'Group') ?></span>
                  </span>
                  <i class="fa-solid fa-chevron-right chev <?= $anyActive ? 'rot':'' ?>"></i>
                </button>

                <div id="<?= $gid ?>" class="collapse <?= $anyActive ? 'show':'' ?>">
                  <div class="nav nav-pills flex-column">
                    <?php foreach ($group['items'] as $it):
                        $active = (($it['key'] ?? '') === $activeKey);
                        $url    = $it['url'] ?? '#'; ?>
                      <a class="nav-link d-flex align-items-center <?= $active ? 'active' : 'text-white-50' ?>"
                         href="<?= h($url) ?>">
                        <i class="<?= h($it['icon'] ?? 'fa-regular fa-circle') ?> me-2"></i>
                        <span class="flex-grow-1"><?= h($it['title'] ?? 'Item') ?></span>
                        <?php if (!empty($it['badge'])): ?>
                          <span class="badge bg-primary-subtle text-primary-emphasis ms-auto"><?= h($it['badge']) ?></span>
                        <?php endif; ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </nav>
        </aside>
        <?php
        return ob_get_clean();
    }
}

/* ---- Resolve inputs and render ---- */
$__activeKey  = isset($activeKey) ? (string)$activeKey : phps_current_admin_page_key();
$__menu       = isset($menu) && is_array($menu) ? $menu : phps_load_admin_menu();
$__appVersion = isset($appVersion) ? (string)$appVersion : ($system_info['app_version'] ?? '1.0.0');
$__adminName  = isset($adminName) ? (string)$adminName : ($admin['name'] ?? 'Admin');

echo phps_render_admin_sidebar($__menu, $__activeKey, $__appVersion, $__adminName);