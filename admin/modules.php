<?php
/**
 * PhPstrap Admin — Modules Management
 * (keeps original functionality; only layout/nav updated)
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

/* -------------------------------- Includes -------------------------------- */
require_once '../config/database.php';
require_once '../config/app.php';

// Auth include (support multiple paths)
$auth_paths = ['admin-auth.php', 'includes/admin-auth.php', '../includes/admin-auth.php'];
foreach ($auth_paths as $path) { if (file_exists($path)) { require_once $path; break; } }

// Module scanner include (support multiple paths)
$scanner_paths = ['includes/scan_modules.php', '../includes/scan_modules.php', 'scan_modules.php'];
$scanner_included = false;
foreach ($scanner_paths as $path) {
    if (file_exists($path)) { require_once $path; $scanner_included = true; break; }
}

/* ------------------------------ App/Auth ---------------------------------- */
initializeApp();
if (function_exists('requireAdminAuth')) { requireAdminAuth(); }
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'modules.php'));
    exit;
}

$admin = [
    'id'    => $_SESSION['admin_id']    ?? 1,
    'name'  => $_SESSION['admin_name']  ?? 'Administrator',
    'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
];

// CSRF token
if (!isset($_SESSION['PhPstrap_csrf_token'])) {
    $_SESSION['PhPstrap_csrf_token'] = bin2hex(random_bytes(32));
}

/* -------------------------------- Helpers --------------------------------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ------------------------------ POST actions ------------------------------ */
$message = '';
$error = false;
$current_tab = $_GET['tab'] ?? 'installed';
$scan_results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['PhPstrap_csrf_token'] ?? '', $_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $error = true;
    } else {
        try {
            $pdo = getDbConnection();
            switch ($_POST['action']) {
                case 'scan_modules':
                    if ($scanner_included && function_exists('scanAndRegisterModules')) {
                        $scan_results = scanAndRegisterModules();
                        if (!empty($scan_results['success'])) {
                            $registered = (int)($scan_results['registered'] ?? 0);
                            $message = $registered > 0
                                ? "Module scan completed! {$registered} new module(s) registered."
                                : "Module scan completed. No new modules found.";
                            $error = false;
                        } else {
                            $message = $scan_results['message'] ?? 'Module scan failed.';
                            $error = true;
                        }
                    } else {
                        $message = 'Module scanner not available.';
                        $error = true;
                    }
                    break;

                case 'toggle_module':
                    $result = toggleModule($pdo, $_POST['module_id']);
                    $message = $result['message'];
                    $error = empty($result['success']);
                    break;

                case 'install_module':
                    $result = installModule($pdo, $_POST);
                    $message = $result['message'];
                    $error = empty($result['success']);
                    break;

                case 'install_remote_module':
                    $result = installRemoteModule($pdo, $_POST);
                    $message = $result['message'];
                    $error = empty($result['success']);
                    break;

                case 'uninstall_module':
                    $result = uninstallModule($pdo, $_POST['module_id']);
                    $message = $result['message'];
                    $error = empty($result['success']);
                    break;

                case 'update_module':
                    $result = updateModule($pdo, $_POST['module_id']);
                    $message = $result['message'];
                    $error = empty($result['success']);
                    break;

                case 'update_settings':
                    $result = updateModuleSettings($pdo, $_POST);
                    $message = $result['message'];
                    $error = empty($result['success']);
                    break;

                case 'refresh_available':
                    $result = refreshAvailableModules();
                    $message = $result['message'];
                    $error = empty($result['success']);
                    break;

                default:
                    $message = 'Invalid action specified.';
                    $error = true;
            }

            // Log successful admin action
            if (function_exists('logAdminActivity') && !$error) {
                logAdminActivity('module_' . $_POST['action'], ['module_id' => $_POST['module_id'] ?? null]);
            }
        } catch (Throwable $e) {
            $message = 'An error occurred. Please try again.';
            $error = true;
            error_log('Modules management error: ' . $e->getMessage());
        }
    }
}

/* ---------------------------- Data layer (unchanged) ---------------------- */
function getModules($status = 'all') {
    try {
        $pdo = getDbConnection();
        $where = ''; $params = [];
        if ($status !== 'all') { $where = 'WHERE status = ?'; $params[] = $status; }
        $stmt = $pdo->prepare("SELECT * FROM modules $where ORDER BY priority ASC, name ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { error_log('Error fetching modules: ' . $e->getMessage()); return []; }
}

function getModuleStats() {
    try {
        $pdo = getDbConnection();
        $total   = (int)$pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
        $enabled = (int)$pdo->query("SELECT COUNT(*) FROM modules WHERE enabled = 1")->fetchColumn();
        $active  = (int)$pdo->query("SELECT COUNT(*) FROM modules WHERE status = 'active'")->fetchColumn();
        $core    = (int)$pdo->query("SELECT COUNT(*) FROM modules WHERE is_core = 1")->fetchColumn();
        return compact('total','enabled','active','core');
    } catch (Throwable $e) {
        error_log('Error fetching module stats: ' . $e->getMessage());
        return ['total'=>0,'enabled'=>0,'active'=>0,'core'=>0];
    }
}

function getAvailableModules() {
    $cache_file = sys_get_temp_dir() . '/phpstrap_available_modules.json';
    $cache_duration = 3600;
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
        $cached = file_get_contents($cache_file);
        $modules = json_decode($cached, true);
        if (is_array($modules)) return filterAvailableModules($modules);
    }
    try {
        $context = stream_context_create(['http'=>['timeout'=>10,'user_agent'=>'PhPstrap/1.0']]);
        $json = @file_get_contents('https://phpstrap.com/modules/modules.json', false, $context);
        if ($json === false) { error_log('Failed to fetch available modules from URL'); return []; }
        $modules = json_decode($json, true);
        if (!is_array($modules)) { error_log('Invalid JSON in available modules response'); return []; }
        @file_put_contents($cache_file, $json);
        return filterAvailableModules($modules);
    } catch (Throwable $e) { error_log('Error fetching available modules: ' . $e->getMessage()); return []; }
}
function filterAvailableModules($modules) {
    $installed = array_column(getModules(), 'name');
    return array_values(array_filter($modules, fn($m) => !in_array($m['name'] ?? '', $installed, true)));
}
function refreshAvailableModules() {
    try {
        $cache_file = sys_get_temp_dir() . '/phpstrap_available_modules.json';
        if (file_exists($cache_file)) @unlink($cache_file);
        $mods = getAvailableModules();
        return ['success'=>true,'message'=>'Available modules refreshed successfully. Found '.count($mods).' module(s).'];
    } catch (Throwable $e) { return ['success'=>false,'message'=>'Failed to refresh available modules.']; }
}
function installRemoteModule($pdo, $data) {
    try {
        $name = trim($data['module_name'] ?? '');
        $title = trim($data['module_title'] ?? '');
        $version = trim($data['module_version'] ?? '1.0.0');
        $author = trim($data['module_author'] ?? '');
        $description = trim($data['module_description'] ?? '');
        $url = trim($data['module_url'] ?? '');
        if ($name === '' || $title === '') return ['success'=>false,'message'=>'Module name and title are required.'];
        $stmt = $pdo->prepare("SELECT id FROM modules WHERE name=?"); $stmt->execute([$name]);
        if ($stmt->fetchColumn()) return ['success'=>false,'message'=>'Module already exists.'];
        $stmt = $pdo->prepare("INSERT INTO modules (name,title,description,version,author,enabled,status,url,installed_at) VALUES (?,?,?,?,?,0,'inactive',?,NOW())");
        $ok = $stmt->execute([$name,$title,$description,$version,$author,$url]);
        if ($ok) {
            $cache_file = sys_get_temp_dir() . '/phpstrap_available_modules.json';
            if (file_exists($cache_file)) @unlink($cache_file);
            return ['success'=>true,'message'=>"Module '{$title}' installed successfully."];
        }
        return ['success'=>false,'message'=>'Failed to install module.'];
    } catch (Throwable $e) { error_log('Error installing remote module: '.$e->getMessage()); return ['success'=>false,'message'=>'Database error occurred.']; }
}
function toggleModule($pdo, $module_id) {
    try {
        $id = (int)$module_id; if ($id<=0) return ['success'=>false,'message'=>'Invalid module ID.'];
        $stmt = $pdo->prepare("SELECT name, enabled, is_core, dependencies FROM modules WHERE id=?");
        $stmt->execute([$id]); $module = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$module) return ['success'=>false,'message'=>'Module not found.'];
        if (!empty($module['is_core']) && !empty($module['enabled'])) return ['success'=>false,'message'=>'Core modules cannot be disabled.'];

        $new_enabled = !empty($module['enabled']) ? 0 : 1;
        $new_status  = $new_enabled ? 'active' : 'inactive';

        // Dependency check when enabling
        if ($new_enabled) {
            // -------- DEBUG (kept as-is from your original) --------
            error_log("SMTP DEBUG - Module: " . $module['name']);
            error_log("SMTP DEBUG - Dependencies raw: " . var_export($module['dependencies'], true));
            error_log("SMTP DEBUG - Dependencies type: " . gettype($module['dependencies']));
            $deps = json_decode($module['dependencies'], true);
            error_log("SMTP DEBUG - After JSON decode: " . var_export($deps, true));
            error_log("SMTP DEBUG - JSON error: " . json_last_error_msg());
            error_log("SMTP DEBUG - Is array: " . (is_array($deps) ? 'YES' : 'NO'));
            error_log("SMTP DEBUG - Dependencies null/empty: deps=" . ($deps ? 'NOT NULL' : 'NULL') . ", empty=" . (empty($deps) ? 'YES' : 'NO'));
            // -------------------------------------------------------
            if ($deps && is_array($deps)) {
                error_log("SMTP DEBUG - Entering dependency check loop, count: " . count($deps));
                foreach ($deps as $dep) {
                    error_log("SMTP DEBUG - Checking dependency: " . var_export($dep, true) . " (type: " . gettype($dep) . ")");
                    $s = $pdo->prepare("SELECT enabled FROM modules WHERE name=?");
                    $s->execute([$dep]); $dep_enabled = $s->fetchColumn();
                    error_log("SMTP DEBUG - Dependency '$dep' enabled status: " . ($dep_enabled ? 'YES' : 'NO'));
                    if (!$dep_enabled) {
                        error_log("SMTP DEBUG - DEPENDENCY CHECK FAILED for: " . var_export($dep, true));
                        return ['success'=>false,'message'=>"Dependency '$dep' is not enabled."];
                    }
                }
                error_log("SMTP DEBUG - All dependencies checked successfully");
            } else {
                error_log("SMTP DEBUG - Skipping dependency check - deps is empty or not array");
            }
        }
        error_log("SMTP DEBUG - About to update module status to: enabled=$new_enabled, status=$new_status");

        $u = $pdo->prepare("UPDATE modules SET enabled=?, status=?, updated_at=NOW() WHERE id=?");
        $ok = $u->execute([$new_enabled,$new_status,$id]);
        if ($ok) return ['success'=>true,'message'=>"Module '{$module['name']}' ".($new_enabled?'enabled':'disabled')." successfully."];
        return ['success'=>false,'message'=>'Failed to update module status.'];
    } catch (Throwable $e) {
        error_log('Error toggling module: '.$e->getMessage());
        error_log('Error stack trace: '.$e->getTraceAsString());
        return ['success'=>false,'message'=>'Database error occurred.'];
    }
}
function installModule($pdo, $data) {
    try {
        $name = trim($data['name'] ?? '');
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        $version = trim($data['version'] ?? '1.0.0');
        $author = trim($data['author'] ?? '');
        if ($name==='' || $title==='') return ['success'=>false,'message'=>'Module name and title are required.'];
        $stmt = $pdo->prepare("SELECT id FROM modules WHERE name=?"); $stmt->execute([$name]);
        if ($stmt->fetchColumn()) return ['success'=>false,'message'=>'Module already exists.'];
        $stmt = $pdo->prepare("INSERT INTO modules (name,title,description,version,author,enabled,status,installed_at) VALUES (?,?,?,?,?,0,'inactive',NOW())");
        $ok = $stmt->execute([$name,$title,$desc,$version,$author]);
        return $ok ? ['success'=>true,'message'=>'Module installed successfully.'] : ['success'=>false,'message'=>'Failed to install module.'];
    } catch (Throwable $e) { error_log('Error installing module: '.$e->getMessage()); return ['success'=>false,'message'=>'Database error occurred.']; }
}
function uninstallModule($pdo, $module_id) {
    try {
        $id = (int)$module_id; if ($id<=0) return ['success'=>false,'message'=>'Invalid module ID.'];
        $s = $pdo->prepare("SELECT name, is_core FROM modules WHERE id=?"); $s->execute([$id]); $m = $s->fetch(PDO::FETCH_ASSOC);
        if (!$m) return ['success'=>false,'message'=>'Module not found.'];
        if (!empty($m['is_core'])) return ['success'=>false,'message'=>'Core modules cannot be uninstalled.'];
        $d = $pdo->prepare("DELETE FROM modules WHERE id=?"); $ok = $d->execute([$id]);
        return $ok ? ['success'=>true,'message'=>"Module '{$m['name']}' uninstalled successfully."] : ['success'=>false,'message'=>'Failed to uninstall module.'];
    } catch (Throwable $e) { error_log('Error uninstalling module: '.$e->getMessage()); return ['success'=>false,'message'=>'Database error occurred.']; }
}
function updateModule($pdo, $module_id) {
    try {
        $id = (int)$module_id; if ($id<=0) return ['success'=>false,'message'=>'Invalid module ID.'];
        $u = $pdo->prepare("UPDATE modules SET last_check=NOW() WHERE id=?"); $u->execute([$id]);
        return ['success'=>true,'message'=>'Module update check completed.'];
    } catch (Throwable $e) { error_log('Error updating module: '.$e->getMessage()); return ['success'=>false,'message'=>'Database error occurred.']; }
}
function updateModuleSettings($pdo, $data) {
    try {
        $id = (int)($data['module_id'] ?? 0); if ($id<=0) return ['success'=>false,'message'=>'Invalid module ID.'];
        $s = $pdo->prepare("SELECT settings FROM modules WHERE id=?"); $s->execute([$id]); $current = $s->fetchColumn();
        $settings = json_decode((string)$current, true) ?: [];
        foreach ($data as $k=>$v) {
            if (strpos($k,'setting_')===0) { $key = substr($k,8); $settings[$key] = $v === 'on' ? true : $v; }
        }
        $u = $pdo->prepare("UPDATE modules SET settings=?, updated_at=NOW() WHERE id=?");
        $ok = $u->execute([json_encode($settings), $id]);
        return $ok ? ['success'=>true,'message'=>'Module settings updated successfully.'] : ['success'=>false,'message'=>'Failed to update module settings.'];
    } catch (Throwable $e) { error_log('Error updating module settings: '.$e->getMessage()); return ['success'=>false,'message'=>'Database error occurred.']; }
}

/* ----------------------------- Fetch page data ---------------------------- */
$modules           = getModules();
$stats             = getModuleStats();
$available_modules = getAvailableModules();

// Optional simple system info (for sidebar header if desired)
$system_info = ['app_version'=>'1.0.0'];
try {
    $pdo = getDbConnection();
    $v = $pdo->query("SELECT value FROM settings WHERE `key`='app_version' LIMIT 1")->fetchColumn();
    if ($v) $system_info['app_version'] = $v;
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Modules - PhPstrap Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet"><!-- shared admin styles -->
<style>
/* Page-specific polish (cards/buttons) */
.module-card{background:#fff;border-radius:15px;border:2px solid #e9ecef;padding:1.25rem;margin-bottom:1rem;transition:.2s}
.module-card:hover{border-color:#667eea;box-shadow:0 8px 20px rgba(0,0,0,.06);transform:translateY(-2px)}
.module-card.enabled{border-color:#28a745;background:linear-gradient(145deg,#fff 0%,#f8fff9 100%)}
.module-card.core{border-color:#ffc107;background:linear-gradient(145deg,#fff 0%,#fffdf5 100%)}
.module-card.available{border-color:#17a2b8;background:linear-gradient(145deg,#fff 0%,#f5fcfd 100%)}
.tabs-nav .nav-link{color:#6c757d;border:none;border-radius:0}
.tabs-nav .nav-link.active{color:#667eea;border-bottom:2px solid #667eea}
.stat-card{background:#fff;border-radius:15px;padding:1.25rem;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,.05)}
.stat-number{font-weight:700;font-size:1.5rem}
</style>
</head>
<body class="bg-body-tertiary">
<div class="d-flex">
  <!-- Sidebar (shared include) -->
  <aside class="admin-sidebar bg-dark text-white">
    <div class="p-3 border-bottom border-secondary-subtle">
      <div class="d-flex align-items-center">
        <i class="fa-solid fa-shield-halved me-2"></i><strong>PhPstrap Admin</strong>
      </div>
      <div class="small text-secondary mt-1">v<?= h($system_info['app_version']) ?></div>
    </div>
<?php
  // Provide variables the include may use
  $activeKey     = 'modules';
  $sidebarBadges = [];
  $appVersion    = $system_info['app_version'] ?? '1.0.0';
  $adminName     = $admin['name'] ?? 'Admin';
  include __DIR__ . '/includes/admin-sidebar.php';
?>
  </aside>

  <!-- Main Content -->
  <main class="flex-grow-1">
    <!-- Header -->
    <header class="bg-white border-bottom">
      <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h1 class="h4 mb-0">Modules</h1>
            <div class="small text-body-secondary mt-1">
              <i class="fa-solid fa-puzzle-piece me-1"></i>Module Management
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="text-body-secondary">Welcome, <strong><?= h($admin['name']) ?></strong></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
          </div>
        </div>
      </div>
    </header>

    <div class="admin-content container-fluid">

      <!-- Alerts -->
      <?php if ($message): ?>
        <div class="alert alert-<?= $error ? 'danger' : 'success' ?> alert-dismissible fade show my-3">
          <i class="fa-solid fa-<?= $error ? 'triangle-exclamation' : 'circle-check' ?> me-2"></i><?= h($message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Optional scan results detail -->
      <?php if ($scan_results): ?>
        <div class="alert <?= !empty($scan_results['success']) ? 'alert-success' : 'alert-danger' ?> my-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <strong><i class="fa-solid fa-magnifying-glass me-1"></i>Module scan:</strong>
              <?= h($scan_results['message'] ?? 'Completed.') ?>
              <?php if (isset($scan_results['registered'])): ?>
                <span class="ms-2 badge text-bg-success"><?= (int)$scan_results['registered'] ?> registered</span>
              <?php endif; ?>
              <?php if (!empty($scan_results['skipped'])): ?>
                <span class="ms-2 badge text-bg-secondary"><?= count((array)$scan_results['skipped']) ?> skipped</span>
              <?php endif; ?>
            </div>
            <button type="button" class="btn-close" onclick="this.closest('.alert').remove()"></button>
          </div>
          <?php if (!empty($scan_results['errors'])): ?>
            <ul class="mb-0 mt-2 small">
              <?php foreach ((array)$scan_results['errors'] as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Stats -->
      <section class="my-3">
        <div class="row g-3">
          <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="stat-number"><?= (int)$stats['total'] ?></div><div class="text-body-secondary">Total Modules</div></div></div>
          <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="stat-number"><?= (int)$stats['enabled'] ?></div><div class="text-body-secondary">Enabled</div></div></div>
          <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="stat-number"><?= (int)$stats['active'] ?></div><div class="text-body-secondary">Active</div></div></div>
          <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="stat-number"><?= (int)$stats['core'] ?></div><div class="text-body-secondary">Core</div></div></div>
        </div>
      </section>

      <!-- Library Card -->
      <section class="my-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
            <div class="fw-semibold"><i class="fa-solid fa-puzzle-piece me-2"></i>Module Library</div>
            <div class="d-flex gap-2">
              <?php if ($scanner_included): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= h($_SESSION['PhPstrap_csrf_token']) ?>">
                  <input type="hidden" name="action" value="scan_modules">
                  <button class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i>Scan Modules</button>
                </form>
              <?php endif; ?>
              <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#installModuleModal"><i class="fa-solid fa-plus me-1"></i>Install Module</button>
            </div>
          </div>

          <div class="card-body">
            <!-- Tabs -->
            <ul class="nav nav-tabs tabs-nav mb-3">
              <li class="nav-item"><a class="nav-link <?= $current_tab==='installed'?'active':'' ?>" href="?tab=installed"><i class="fa-solid fa-list me-1"></i>Installed</a></li>
              <li class="nav-item"><a class="nav-link <?= $current_tab==='available'?'active':'' ?>" href="?tab=available"><i class="fa-solid fa-download me-1"></i>Available</a></li>
              <li class="nav-item"><a class="nav-link <?= $current_tab==='marketplace'?'active':'' ?>" href="?tab=marketplace"><i class="fa-solid fa-store me-1"></i>Marketplace</a></li>
            </ul>

            <!-- Installed -->
            <?php if ($current_tab === 'installed'): ?>
              <div class="row">
                <?php foreach ($modules as $m): ?>
                  <div class="col-lg-6 col-xl-4">
                    <div class="module-card <?= !empty($m['enabled']) ? 'enabled':'' ?> <?= !empty($m['is_core']) ? 'core':'' ?>">
                      <div class="d-flex justify-content-between">
                        <div class="me-3">
                          <h5 class="mb-1"><?= h($m['title']) ?></h5>
                          <div class="small text-body-secondary">
                            v<?= h($m['version']) ?>
                            <?php if (!empty($m['is_core'])): ?><span class="badge text-bg-warning ms-2">Core</span><?php endif; ?>
                            <span class="badge ms-1 <?= !empty($m['enabled'])?'text-bg-success':'text-bg-secondary' ?>"><?= !empty($m['enabled'])?'Enabled':'Disabled' ?></span>
                          </div>
                        </div>
                      </div>

                      <p class="mt-2 mb-2 text-body-secondary"><?= h($m['description'] ?: 'No description available.') ?></p>

                      <div class="small text-body-tertiary">
                        <?php if (!empty($m['author'])): ?><i class="fa-regular fa-user me-1"></i>By <?= h($m['author']) ?><?php endif; ?>
                        <?php if (!empty($m['installed_at'])): ?><br><i class="fa-regular fa-calendar me-1"></i>Installed <?= date('M j, Y', strtotime($m['installed_at'])) ?><?php endif; ?>
                      </div>

                      <div class="mt-3 d-flex flex-wrap gap-2">
                        <?php if (empty($m['is_core']) || empty($m['enabled'])): ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['PhPstrap_csrf_token']) ?>">
                            <input type="hidden" name="action" value="toggle_module">
                            <input type="hidden" name="module_id" value="<?= (int)$m['id'] ?>">
                            <button class="btn btn-sm <?= !empty($m['enabled']) ? 'btn-warning' : 'btn-success' ?>">
                              <i class="fa-solid fa-<?= !empty($m['enabled']) ? 'pause' : 'play' ?> me-1"></i><?= !empty($m['enabled']) ? 'Disable' : 'Enable' ?>
                            </button>
                          </form>
                        <?php endif; ?>

                        <?php if (!empty($m['enabled']) && !empty($m['settings'])): ?>
                          <button class="btn btn-outline-primary btn-sm"
                                  data-bs-toggle="modal"
                                  data-bs-target="#settingsModal"
                                  onclick='loadModuleSettings(<?= (int)$m["id"] ?>, <?= json_encode($m, JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_TAG) ?>)'>
                            <i class="fa-solid fa-gear me-1"></i>Settings
                          </button>
                        <?php endif; ?>

                        <form method="post" class="d-inline">
                          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['PhPstrap_csrf_token']) ?>">
                          <input type="hidden" name="action" value="update_module">
                          <input type="hidden" name="module_id" value="<?= (int)$m['id'] ?>">
                          <button class="btn btn-outline-info btn-sm"><i class="fa-solid fa-rotate me-1"></i>Update</button>
                        </form>

                        <?php if (empty($m['is_core'])): ?>
                          <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to uninstall this module?')">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['PhPstrap_csrf_token']) ?>">
                            <input type="hidden" name="action" value="uninstall_module">
                            <input type="hidden" name="module_id" value="<?= (int)$m['id'] ?>">
                            <button class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-trash me-1"></i>Uninstall</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>

                <?php if (empty($modules)): ?>
                  <div class="col-12 text-center py-5 text-body-secondary">
                    <i class="fa-solid fa-puzzle-piece fa-3x mb-3"></i>
                    <h5>No modules installed</h5>
                    <p>Scan for modules or install one manually.</p>
                    <div class="d-flex justify-content-center gap-2">
                      <?php if ($scanner_included): ?>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['PhPstrap_csrf_token']) ?>">
                          <input type="hidden" name="action" value="scan_modules">
                          <button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass me-1"></i>Scan for Modules</button>
                        </form>
                      <?php endif; ?>
                      <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#installModuleModal"><i class="fa-solid fa-plus me-1"></i>Install Module</button>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

            <!-- Available -->
            <?php elseif ($current_tab === 'available'): ?>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <h6 class="mb-0">Available Modules</h6>
                  <small class="text-body-secondary">Browse and install modules from the PhPstrap repository</small>
                </div>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= h($_SESSION['PhPstrap_csrf_token']) ?>">
                  <input type="hidden" name="action" value="refresh_available">
                  <button class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-rotate me-1"></i>Refresh List</button>
                </form>
              </div>

              <div class="row">
                <?php if (!empty($available_modules)): ?>
                  <?php foreach ($available_modules as $am): ?>
                    <div class="col-lg-6 col-xl-4">
                      <div class="module-card available">
                        <h5 class="mb-1"><?= h($am['title']) ?></h5>
                        <div class="small text-body-secondary mb-2">v<?= h($am['version']) ?> <span class="badge text-bg-info ms-1">Available</span></div>
                        <p class="text-body-secondary mb-2"><?= h($am['description'] ?: 'No description available.') ?></p>
                        <div class="small text-body-tertiary mb-2">
                          <?php if (!empty($am['author'])): ?><i class="fa-regular fa-user me-1"></i>By <?= h($am['author']) ?><?php endif; ?>
                          <?php if (!empty($am['url'])): ?><br><i class="fa-solid fa-link me-1"></i><a class="link-info" target="_blank" href="<?= h($am['url']) ?>">View Source</a><?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                          <button class="btn btn-success btn-sm"
                                  data-bs-toggle="modal"
                                  data-bs-target="#installRemoteModuleModal"
                                  onclick="loadRemoteModuleData('<?= h($am['name']) ?>','<?= h($am['title']) ?>','<?= h($am['version']) ?>','<?= h($am['author']) ?>','<?= h($am['description']) ?>','<?= h($am['url']) ?>')">
                            <i class="fa-solid fa-download me-1"></i>Install
                          </button>
                          <?php if (!empty($am['url'])): ?>
                            <a class="btn btn-outline-info btn-sm" target="_blank" href="<?= h($am['url']) ?>"><i class="fa-solid fa-up-right-from-square me-1"></i>Details</a>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="col-12 text-center py-5 text-body-secondary">
                    <i class="fa-solid fa-download fa-3x mb-3"></i>
                    <h5>No Available Modules</h5>
                    <p>All modules may already be installed, or the repository couldn’t be reached.</p>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['PhPstrap_csrf_token']) ?>">
                      <input type="hidden" name="action" value="refresh_available">
                      <button class="btn btn-primary"><i class="fa-solid fa-rotate me-1"></i>Try Again</button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>

            <!-- Marketplace -->
            <?php else: ?>
              <div class="text-center py-5 text-body-secondary">
                <i class="fa-solid fa-store fa-3x mb-3"></i>
                <h5>Module Marketplace</h5>
                <p>Discover premium modules and extensions.</p>
                <button class="btn btn-primary"><i class="fa-solid fa-up-right-from-square me-1"></i>Visit Marketplace</button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

    </div>
  </main>
</div>

<!-- Install Module Modal -->
<div class="modal fade" id="installModuleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <div class="modal-header">
        <h5 class="modal-title">Install New Module</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['PhPstrap_csrf_token']) ?>">
        <input type="hidden" name="action" value="install_module">
        <div class="mb-3">
          <label class="form-label">Module Name</label>
          <input type="text" class="form-control" name="name" required>
          <small class="text-body-secondary">Unique identifier (lowercase, no spaces)</small>
        </div>
        <div class="mb-3">
          <label class="form-label">Display Title</label>
          <input type="text" class="form-control" name="title" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="3"></textarea>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Version</label>
            <input type="text" class="form-control" name="version" value="1.0.0">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Author</label>
            <input type="text" class="form-control" name="author">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Install Module</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Install Remote Module Modal -->
<div class="modal fade" id="installRemoteModuleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <div class="modal-header">
        <h5 class="modal-title">Install Remote Module</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['PhPstrap_csrf_token']) ?>">
        <input type="hidden" name="action" value="install_remote_module">
        <input type="hidden" name="module_name" id="remote_module_name">
        <input type="hidden" name="module_title" id="remote_module_title">
        <input type="hidden" name="module_version" id="remote_module_version">
        <input type="hidden" name="module_author" id="remote_module_author">
        <input type="hidden" name="module_description" id="remote_module_description">
        <input type="hidden" name="module_url" id="remote_module_url">

        <div class="text-center">
          <i class="fa-solid fa-download fa-3x text-primary mb-3"></i>
          <h6 id="remote_install_title" class="mb-2"></h6>
          <p id="remote_install_description" class="text-body-secondary mb-3"></p>
          <div class="row text-start">
            <div class="col-6"><strong>Version:</strong> <span id="remote_install_version"></span></div>
            <div class="col-6"><strong>Author:</strong> <span id="remote_install_author"></span></div>
          </div>
        </div>

        <div class="alert alert-info mt-3">
          <i class="fa-regular fa-circle-question me-1"></i>
          This installs the module in your database. Upload the module files to your modules directory separately.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-success" type="submit"><i class="fa-solid fa-download me-1"></i>Install Module</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post">
      <div class="modal-header">
        <h5 class="modal-title">Module Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['PhPstrap_csrf_token']) ?>">
        <input type="hidden" name="action" value="update_settings">
        <input type="hidden" name="module_id" id="settingsModuleId">
        <div id="settingsContent"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary" type="submit">Save Settings</button>
      </div>
    </form>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle (add a button in header if you want)
function toggleSidebar(){ document.querySelector('.admin-sidebar')?.classList.toggle('show'); }

// Load module settings (kept compatible with your previous structure)
function loadModuleSettings(moduleId, moduleData){
  document.getElementById('settingsModuleId').value = moduleId;
  const settingsContent = document.getElementById('settingsContent');
  const settings = (() => { try { return JSON.parse(moduleData.settings || '{}'); } catch(e){ return {}; } })();
  let html = '<h6 class="mb-3">Configure ' + (moduleData.title || 'Module') + '</h6>';
  const keys = Object.keys(settings || {});
  if (keys.length === 0) {
    html += '<p class="text-body-secondary mb-0">This module has no configurable settings.</p>';
  } else {
    for (const k of keys) {
      const label = k.replace(/_/g,' ').replace(/\b\w/g, l => l.toUpperCase());
      const val = settings[k];
      html += '<div class="mb-3">';
      html += '<label class="form-label">'+label+'</label>';
      if (typeof val === 'boolean') {
        html += '<div class="form-check">';
        html += '<input class="form-check-input" type="checkbox" name="setting_'+k+'" id="setting_'+k+'" '+(val?'checked':'')+'>';
        html += '<label class="form-check-label" for="setting_'+k+'">Enable this option</label>';
        html += '</div>';
      } else {
        html += '<input type="text" class="form-control" name="setting_'+k+'" value="'+String(val).replaceAll('"','&quot;')+'">';
      }
      html += '</div>';
    }
  }
  settingsContent.innerHTML = html;
}

// Load remote module data into modal
function loadRemoteModuleData(name, title, version, author, description, url){
  document.getElementById('remote_module_name').value = name || '';
  document.getElementById('remote_module_title').value = title || '';
  document.getElementById('remote_module_version').value = version || '1.0.0';
  document.getElementById('remote_module_author').value = author || '';
  document.getElementById('remote_module_description').value = description || '';
  document.getElementById('remote_module_url').value = url || '';

  document.getElementById('remote_install_title').textContent = title || '';
  document.getElementById('remote_install_description').textContent = description || '';
  document.getElementById('remote_install_version').textContent = version || '';
  document.getElementById('remote_install_author').textContent = author || '';
}
</script>
</body>
</html>