<?php
/**
 * Reusable Admin Sidebar NAV-ONLY (grouped + collapsible)
 * Usage (inside your page's <aside class="admin-sidebar"> ... </aside>):
 *
 *   // in dashboard.php (or any admin page)
 *   // $activeKey  = 'dashboard'; // optional: you can pass, or let it auto-detect
 *   require __DIR__ . '/includes/admin-nav.php';
 *   echo phps_render_admin_nav(
 *       phps_load_admin_menu(),
 *       isset($activeKey) ? $activeKey : null
 *   );
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

/** ---------- NEW: smarter matching helpers ---------- */
if (!function_exists('phps_normalize_path')) {
    function phps_normalize_path(string $path): string {
        // strip scheme/host if present
        $path = preg_replace('#^https?://[^/]+#i', '', $path);
        // keep only path
        $path = parse_url($path, PHP_URL_PATH) ?: '';
        // collapse multiple slashes and trim trailing slash (keep / for root)
        $path = preg_replace('#/+#', '/', $path);
        $path = rtrim($path, '/');
        // get basename and remove .php
        $base = strtolower(basename($path));
        $base = preg_replace('/\.php$/i', '', $base);
        // treat empty/index as dashboard
        if ($base === '' || $base === 'index') $base = 'dashboard';
        return $base;
    }
}

if (!function_exists('phps_guess_active_key')) {
    /**
     * Determine the active key by comparing the current URL with menu item URLs.
     * Falls back to query param "page" if used (e.g., index.php?page=settings),
     * then to old phps_current_admin_page_key().
     */
    function phps_guess_active_key(array $menu, ?string $fallback = null): string {
        // 0) Router-style ?page=xxx support (optional; harmless if unused)
        if (!empty($_GET['page'])) {
            $page = strtolower(preg_replace('/[^a-z0-9_-]+/i', '', (string)$_GET['page']));
            if ($page !== '') return $page;
        }

        // 1) Normalize current request
        $current = $_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '');
        $curKey  = phps_normalize_path($current);

        // 2) If curKey maps directly to a menu key, we're done
        foreach ($menu as $group) {
            if (empty($group['items'])) continue;
            foreach ($group['items'] as $it) {
                $key = strtolower((string)($it['key'] ?? ''));
                if ($key && $key === $curKey) return $key;
            }
        }

        // 3) Compare normalized basenames of item URLs vs current
        foreach ($menu as $group) {
            if (empty($group['items'])) continue;
            foreach ($group['items'] as $it) {
                $url = (string)($it['url'] ?? '');
                if ($url === '') continue;
                $urlKey = phps_normalize_path($url);
                if ($urlKey === $curKey) {
                    return strtolower((string)($it['key'] ?? $urlKey));
                }
            }
        }

        // 4) fallback
        if (!empty($fallback)) return $fallback;
        return phps_current_admin_page_key();
    }
}
/** ---------- end new helpers ---------- */

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
                    ['key'=>'dashboard','title'=>'Overview','icon'=>'fa-solid fa-gauge','url'=>'dashboard.php'],
                ],
            ],
            [
                'key'   => 'maintenance',
                'title' => 'Maintenance',
                'icon'  => 'fa-solid fa-broom',
                'items' => [
                    ['key'=>'cleanup','title'=>'System Cleanup','icon'=>'fa-solid fa-broom','url'=>'cleanup.php','badge'=>'New'],
                    ['key'=>'logs','title'=>'Admin Logs','icon'=>'fa-regular fa-clipboard','url'=>'logs.php'],
                    ['key'=>'update','title'=>'Check for updates','icon'=>'fa-regular fa-clipboard','url'=>'update.php'],
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

if (!function_exists('phps_render_admin_nav')) {
    /**
     * Render NAV ONLY (no <aside>, no header). The outer wrapper should be
     * provided by the page (so mobile off-canvas works cleanly).
     */
    function phps_render_admin_nav(array $menu, ?string $activeKey = null): string {
        // prune non-existing items if a key 'exists' is supplied
        foreach ($menu as &$g) {
            if (!empty($g['items'])) {
                $g['items'] = array_values(array_filter($g['items'], function($it){
                    return !isset($it['exists']) || $it['exists'];
                }));
            }
        }
        unset($g);

        // smarter active detection (falls back neatly)
        $activeKey = $activeKey ?: phps_guess_active_key($menu, phps_current_admin_page_key());

        ob_start(); ?>
        <nav class="sidebar-groups" id="sidebarGroups" data-bs-theme="dark">
            <?php foreach ($menu as $i => $group):
                $gid = 'grp-' . preg_replace('/[^a-z0-9_-]/i','', $group['key'] ?? ('g'.$i));
                $anyActive = false;
                foreach ($group['items'] as $it) { if (($it['key'] ?? '') === $activeKey) { $anyActive = true; break; } }
                ?>
                <div class="sidebar-group">
                    <button class="group-toggle btn w-100 d-flex align-items-center justify-content-between"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#<?= $gid ?>"
                            aria-expanded="<?= $anyActive ? 'true':'false' ?>"
                            aria-controls="<?= $gid ?>"
                            data-group-key="<?= $gid ?>">
                      <span class="d-flex align-items-center">
                        <i class="<?= h($group['icon'] ?? 'fa-solid fa-circle') ?> me-2" aria-hidden="true"></i>
                        <span class="text-start"><?= h($group['title'] ?? 'Group') ?></span>
                      </span>
                      <i class="fa-solid fa-chevron-right chev <?= $anyActive ? 'rot':'' ?>" aria-hidden="true"></i>
                    </button>

                    <div id="<?= $gid ?>" class="collapse <?= $anyActive ? 'show':'' ?>">
                        <div class="nav nav-pills flex-column">
                            <?php foreach ($group['items'] as $it):
                                $isActive = (($it['key'] ?? '') === $activeKey);
                                $url      = $it['url'] ?? '#'; ?>
                                <a class="nav-link d-flex align-items-center <?= $isActive ? 'active' : 'text-white-50' ?>"
                                   href="<?= h($url) ?>">
                                    <i class="<?= h($it['icon'] ?? 'fa-regular fa-circle') ?> me-2" aria-hidden="true"></i>
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
        <?php
        return ob_get_clean();
    }
}

/* ---- Resolve inputs and echo NAV ---- */
$__menu      = isset($menu) && is_array($menu) ? $menu : phps_load_admin_menu();
// allow auto-detect by default; you can still pass $activeKey from the page
$__activeKey = isset($activeKey) ? (string)$activeKey : null;

echo phps_render_admin_nav($__menu, $__activeKey);