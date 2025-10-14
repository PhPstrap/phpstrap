<?php
/**
 * PhPstrap Admin — Module Logs (from modules table only)
 * - No dependency on module_logs/admin_activity_log
 * - Detects modules columns dynamically and adapts
 * - Bootstrap 5.3 + Font Awesome + /assets/css/admin.css
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

/* ------------------------------- Includes --------------------------------- */
require_once '../config/database.php';
require_once '../config/app.php';

// Auth include (support multiple paths)
$auth_paths = ['admin-auth.php', 'includes/admin-auth.php', '../includes/admin-auth.php'];
foreach ($auth_paths as $path) { if (file_exists($path)) { require_once $path; break; } }

/* ------------------------------ App/Auth ---------------------------------- */
initializeApp();
if (function_exists('requireAdminAuth')) { requireAdminAuth(); }
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
  header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'module-logs.php'));
  exit;
}

$admin = [
  'id'    => $_SESSION['admin_id']    ?? 1,
  'name'  => $_SESSION['admin_name']  ?? 'Administrator',
  'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
];

if (function_exists('logAdminActivity')) { try { logAdminActivity('logs_access', ['page' => 'module-logs-modtable']); } catch (Throwable $e) {} }

/* ------------------------------ Helpers ----------------------------------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function timeAgo($datetime) {
  if (empty($datetime)) return '—';
  $ts = strtotime((string)$datetime);
  if ($ts === false) return h((string)$datetime);
  $diff = time() - $ts;
  if ($diff < 60) return 'just now';
  if ($diff < 3600) return floor($diff/60) . ' min ago';
  if ($diff < 86400) return floor($diff/3600) . ' hrs ago';
  if ($diff < 2592000) return floor($diff/86400) . ' days ago';
  return date('M j, Y g:i A', $ts);
}
function badgeOnOff($val, $on='Enabled', $off='Disabled'){
  return ((int)$val === 1)
    ? '<span class="badge text-bg-success">'.$on.'</span>'
    : '<span class="badge text-bg-secondary">'.$off.'</span>';
}
function yesNoBadge($val, $yes='Yes', $no='No'){
  return ((int)$val === 1)
    ? '<span class="badge text-bg-success">'.$yes.'</span>'
    : '<span class="badge text-bg-secondary">'.$no.'</span>';
}

/* ------------------------------ DB/meta ----------------------------------- */
$db_available = false; $pdo = null;
try { $pdo = getDbConnection(); $db_available = $pdo instanceof PDO; } catch (Throwable $e) {}

$settings = (function(){
  try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT `key`, value FROM settings");
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $out[$row['key']] = $row['value'];
    return $out;
  } catch (Throwable $e) { return []; }
})();
$system_info = ['app_version' => $settings['app_version'] ?? '1.0.0'];

/* ----------------------- Introspect modules columns ----------------------- */
$cols = [];
$has = [
  'id'=>false,'name'=>false,'slug'=>false,'version'=>false,'enabled'=>false,
  'installed_at'=>false,'updated_at'=>false,'created_at'=>false,
  'last_error'=>false,'description'=>false,'author'=>false,'homepage'=>false,
  'license'=>false,'category'=>false
];
try {
  if ($pdo) {
    $rs = $pdo->query("SHOW COLUMNS FROM modules");
    while ($c = $rs->fetch(PDO::FETCH_ASSOC)) {
      $col = strtolower($c['Field'] ?? '');
      $cols[] = $col;
      if (isset($has[$col])) $has[$col] = true;
      // common aliases
      if ($col === 'is_enabled') $has['enabled'] = true;
      if ($col === 'installedon' || $col === 'installed') $has['installed_at'] = true;
      if ($col === 'updatedon') $has['updated_at'] = true;
      if ($col === 'createdon') $has['created_at'] = true;
      if ($col === 'error' || $col === 'last_error_message') $has['last_error'] = true;
    }
  }
} catch (Throwable $e) {}

/* ------------------------------ Parameters -------------------------------- */
$show_opts = [10,20,30,50,100];
$show = (int)($_GET['show'] ?? 10);
if (!in_array($show, $show_opts, true)) $show = 10;

$page      = max(1, (int)($_GET['page'] ?? 1));
$per_opts  = [10,20,30,50,100];
$per_page  = (int)($_GET['per_page'] ?? 20);
if (!in_array($per_page, $per_opts, true)) $per_page = 20;
$offset    = ($page - 1) * $per_page;

$q         = trim($_GET['q'] ?? '');        // name/slug/version/author
$status    = $_GET['status'] ?? '';         // '', '1', '0' (enabled)
$date      = $_GET['date'] ?? '';           // filter on "activity timestamp" (installed/updated/created)

/* ------------------------------ Query pieces ------------------------------ */
function modulesActivityTimestampExpr($has) {
  // pick best available timestamp for “activity”
  $parts = [];
  if ($has['updated_at'])    $parts[] = "updated_at";
  if ($has['installed_at'])  $parts[] = "installed_at";
  if ($has['created_at'])    $parts[] = "created_at";
  if (empty($parts)) return "NULL";
  if (count($parts) === 1) return $parts[0];
  // COALESCE(updated_at, installed_at, created_at)
  return "COALESCE(" . implode(',', $parts) . ")";
}

function buildModulesWhere(&$params, $q, $status, $date, $has) {
  $where = "WHERE 1=1";
  if ($q !== '') {
    $like = "%$q%";
    $or = ["name LIKE ?","version LIKE ?"];
    $params[] = $like; $params[] = $like;
    if ($has['slug'])       { $or[] = "slug LIKE ?";       $params[] = $like; }
    if ($has['author'])     { $or[] = "author LIKE ?";     $params[] = $like; }
    if ($has['description']){ $or[] = "description LIKE ?";$params[] = $like; }
    $where .= " AND (" . implode(" OR ", $or) . ")";
  }
  if ($status === '1' || $status === '0') {
    $col = $has['enabled'] ? 'enabled' : null;
    if ($col) { $where .= " AND $col = ?"; $params[] = (int)$status; }
  }
  if ($date) {
    $expr = modulesActivityTimestampExpr($has);
    if ($expr !== "NULL") { $where .= " AND DATE($expr) = ?"; $params[] = $date; }
  }
  return $where;
}

/* ------------------------------ Data access -------------------------------- */
function countModulesAll($q,$status,$date,$has) {
  try {
    $pdo = getDbConnection(); $params = [];
    $where = buildModulesWhere($params,$q,$status,$date,$has);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM modules $where");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
  } catch (Throwable $e) { return 0; }
}

function fetchModulesPaged($limit,$offset,$q,$status,$date,$has) {
  try {
    $pdo = getDbConnection(); $params = [];
    $where = buildModulesWhere($params,$q,$status,$date,$has);
    $activityExpr = modulesActivityTimestampExpr($has) . " AS activity_at";
    $select = ["id","name","version"];
    if ($has['slug'])        $select[] = "slug";
    if ($has['enabled'])     $select[] = "enabled";
    if ($has['installed_at'])$select[] = "installed_at";
    if ($has['updated_at'])  $select[] = "updated_at";
    if ($has['created_at'])  $select[] = "created_at";
    if ($has['last_error'])  $select[] = "last_error";
    if ($has['author'])      $select[] = "author";
    if ($has['homepage'])    $select[] = "homepage";
    if ($has['license'])     $select[] = "license";
    if ($has['category'])    $select[] = "category";

    $sql = "SELECT " . implode(',', $select) . ", $activityExpr
            FROM modules
            $where
            ORDER BY " . ($has['updated_at'] ? "updated_at DESC" :
                           ($has['installed_at'] ? "installed_at DESC" :
                           ($has['created_at'] ? "created_at DESC" : "name ASC"))) . "
            LIMIT ? OFFSET ?";
    array_push($params, (int)$limit, (int)$offset);
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { return []; }
}

function kpisFromModules($has) {
  $out = ['enabled'=>0,'disabled'=>0,'recent'=>[], 'errors'=>[]];
  try {
    $pdo = getDbConnection();
    // enabled/disabled counts
    if ($has['enabled']) {
      $out['enabled']  = (int)$pdo->query("SELECT COUNT(*) FROM modules WHERE enabled=1")->fetchColumn();
      $out['disabled'] = (int)$pdo->query("SELECT COUNT(*) FROM modules WHERE enabled=0")->fetchColumn();
    } else {
      $total = (int)$pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
      $out['enabled'] = $total; $out['disabled'] = 0;
    }
    // recent activity (pick best timestamp)
    $tsExpr = modulesActivityTimestampExpr($has);
    if ($tsExpr !== "NULL") {
      $stmt = $pdo->query("SELECT name, version, $tsExpr AS activity_at
                           FROM modules
                           WHERE $tsExpr IS NOT NULL
                           ORDER BY $tsExpr DESC
                           LIMIT 10");
      $out['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    // last errors (if present)
    if ($has['last_error']) {
      $stmt = $pdo->query("SELECT name, version, last_error,
                                  " . ($has['updated_at'] ? "updated_at" : ($has['created_at'] ? "created_at" : "NULL")) . " AS activity_at
                           FROM modules
                           WHERE last_error IS NOT NULL AND last_error <> ''
                           ORDER BY activity_at DESC
                           LIMIT 10");
      $out['errors'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  } catch (Throwable $e) {}
  return $out;
}

/* ------------------------------ Fetch data -------------------------------- */
$total = countModulesAll($q,$status,$date,$has);
$total_pages = max(1, (int)ceil(($total ?: 1) / $per_page));
$rows = fetchModulesPaged($per_page,$offset,$q,$status,$date,$has);
$kpis = kpisFromModules($has);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Module Logs - <?= h($settings['site_name'] ?? 'PhPstrap Admin') ?></title>

<link href="/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">

<div class="d-flex">
  <!-- Sidebar -->
  <aside class="admin-sidebar bg-dark text-white">
    <div class="p-3 border-bottom border-secondary-subtle d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <i class="fa-solid fa-puzzle-piece me-2"></i>
        <strong>PhPstrap Admin</strong>
      </div>
      <div class="d-flex align-items-center gap-2">
        <div class="small text-secondary d-none d-lg-block">v<?= h($system_info['app_version']) ?></div>
        <button type="button" class="btn btn-sm btn-outline-light d-lg-none" id="btnSidebarToggle" aria-label="Close menu">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>
    <?php $activeKey = 'module-logs'; include __DIR__ . '/includes/admin-sidebar.php'; ?>
  </aside>

  <!-- Content -->
  <main class="flex-grow-1">
    <header class="bg-white border-bottom">
      <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center">
            <button type="button" class="btn btn-outline-secondary d-lg-none me-3" id="btnSidebarOpen" aria-label="Open menu">
              <i class="fa-solid fa-bars"></i>
            </button>
            <div class="d-flex align-items-center gap-3">
              <h1 class="h4 mb-0">Module Logs</h1>
              <div class="small text-body-secondary mt-1">
                <i class="fa-regular fa-clock me-1"></i><?= date('l, F j, Y g:i A') ?>
                <span class="badge ms-2 <?= $db_available ? 'text-bg-success' : 'text-bg-danger' ?>">
                  <?= $db_available ? 'Database Connected' : 'Database Offline' ?>
                </span>
                <span class="badge text-bg-info ms-2">Source: modules</span>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="text-body-secondary">
              Welcome, <strong><?= h($admin['name']) ?></strong>
            </span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">
              <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </header>

    <div class="admin-content container-fluid">

      <?php if (!$db_available): ?>
        <div class="alert alert-warning my-3">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          Database connection unavailable; module logs cannot be displayed.
        </div>
      <?php endif; ?>

      <!-- Overview -->
      <section class="mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa-solid fa-gauge me-2"></i>Overview</span>
            <form method="get" class="d-flex align-items-center gap-2">
              <label class="small text-body-secondary">Show</label>
              <select name="show" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ($show_opts as $opt): ?>
                  <option value="<?= $opt ?>" <?= $opt===$show?'selected':'' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
              <span class="small text-body-secondary">per list</span>
              <!-- keep filters -->
              <input type="hidden" name="q" value="<?= h($q) ?>">
              <input type="hidden" name="status" value="<?= h($status) ?>">
              <input type="hidden" name="date" value="<?= h($date) ?>">
              <input type="hidden" name="per_page" value="<?= (int)$per_page ?>">
            </form>
          </div>
          <div class="card-body">
            <div class="row g-3">

              <!-- KPIs -->
              <div class="col-12">
                <div class="row g-3">
                  <div class="col-6 col-lg-3">
                    <div class="p-3 bg-white border rounded-3 h-100">
                      <div class="text-body-secondary small">Enabled</div>
                      <div class="h4 mb-0"><?= number_format($kpis['enabled']) ?></div>
                    </div>
                  </div>
                  <div class="col-6 col-lg-3">
                    <div class="p-3 bg-white border rounded-3 h-100">
                      <div class="text-body-secondary small">Disabled</div>
                      <div class="h4 mb-0"><?= number_format($kpis['disabled']) ?></div>
                    </div>
                  </div>
                  <?php if ($has['last_error']): ?>
                  <div class="col-6 col-lg-3">
                    <div class="p-3 bg-white border rounded-3 h-100">
                      <div class="text-body-secondary small">With Errors</div>
                      <div class="h4 mb-0"><?= number_format(count($kpis['errors'])) ?></div>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Recent Activity -->
              <div class="col-12 col-xl-6">
                <div class="border rounded-3 h-100">
                  <div class="p-3 border-bottom fw-semibold">
                    <i class="fa-solid fa-bolt me-2"></i>Recent Activity (<?= (int)min($show,10) ?>)
                  </div>
                  <div class="list-group list-group-flush">
                    <?php
                      $recent = array_slice($kpis['recent'] ?? [], 0, min($show,10));
                      if (empty($recent)):
                    ?>
                      <div class="list-group-item small text-body-secondary">No recent activity detected.</div>
                    <?php else: foreach ($recent as $r): ?>
                      <div class="list-group-item d-flex justify-content-between">
                        <div>
                          <div class="fw-semibold"><?= h($r['name'] ?? '—') ?></div>
                          <div class="small text-body-secondary">v<?= h($r['version'] ?? '') ?></div>
                        </div>
                        <div class="text-end small text-body-tertiary"><?= h(timeAgo($r['activity_at'] ?? '')) ?></div>
                      </div>
                    <?php endforeach; endif; ?>
                  </div>
                </div>
              </div>

              <!-- Recent Errors (if any) -->
              <?php if ($has['last_error']): ?>
              <div class="col-12 col-xl-6">
                <div class="border rounded-3 h-100">
                  <div class="p-3 border-bottom fw-semibold">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>Recent Errors (<?= (int)min($show,10) ?>)
                  </div>
                  <div class="list-group list-group-flush">
                    <?php
                      $errs = array_slice($kpis['errors'] ?? [], 0, min($show,10));
                      if (empty($errs)):
                    ?>
                      <div class="list-group-item small text-body-secondary">No recent errors.</div>
                    <?php else: foreach ($errs as $e): ?>
                      <div class="list-group-item">
                        <div class="fw-semibold"><?= h($e['name'] ?? '—') ?> <span class="text-body-secondary">v<?= h($e['version'] ?? '') ?></span></div>
                        <?php if (!empty($e['last_error'])): ?>
                          <div class="small text-body-tertiary mt-1"><?= h(mb_strimwidth((string)$e['last_error'], 0, 120, '…', 'UTF-8')) ?></div>
                        <?php endif; ?>
                        <div class="small text-body-tertiary mt-1"><?= h(timeAgo($e['activity_at'] ?? '')) ?></div>
                      </div>
                    <?php endforeach; endif; ?>
                  </div>
                </div>
              </div>
              <?php endif; ?>

            </div>
          </div>
        </div>
      </section>

      <!-- Filters -->
      <section class="mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary fw-semibold">
            <i class="fa-solid fa-filter me-2"></i>Filter Modules
          </div>
          <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Name, slug, version, author…" value="<?= h($q) ?>">
              </div>
              <div class="col-sm-6 col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value=""  <?= $status===''?'selected':'' ?>>Any</option>
                  <option value="1" <?= $status==='1'?'selected':'' ?>>Enabled</option>
                  <option value="0" <?= $status==='0'?'selected':'' ?>>Disabled</option>
                </select>
              </div>
              <div class="col-sm-6 col-md-2">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= h($date) ?>">
                <div class="form-text">Matches latest of updated/installed/created</div>
              </div>
              <div class="col-sm-6 col-md-2">
                <label class="form-label">Per Page</label>
                <select name="per_page" class="form-select">
                  <?php foreach ($per_opts as $opt): ?>
                    <option value="<?= $opt ?>" <?= $opt===$per_page?'selected':'' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <input type="hidden" name="show" value="<?= (int)$show ?>">
              <div class="col-12 col-md-2">
                <button class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> Apply</button>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- Table -->
      <section class="mb-4">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa-solid fa-list-check me-2"></i>Modules</span>
            <span class="badge text-bg-secondary"><?= number_format((int)$total) ?> total</span>
          </div>

          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Name</th>
                  <?php if ($has['slug']): ?><th>Slug</th><?php endif; ?>
                  <th>Version</th>
                  <?php if ($has['enabled']): ?><th>Status</th><?php endif; ?>
                  <?php if ($has['installed_at']): ?><th>Installed</th><?php endif; ?>
                  <?php if ($has['updated_at']): ?><th>Updated</th><?php endif; ?>
                  <?php if ($has['created_at']): ?><th>Created</th><?php endif; ?>
                  <th>Activity</th>
                  <?php if ($has['author']): ?><th>Author</th><?php endif; ?>
                  <?php if ($has['homepage']): ?><th>Homepage</th><?php endif; ?>
                  <?php if ($has['license']): ?><th>License</th><?php endif; ?>
                  <?php if ($has['category']): ?><th>Category</th><?php endif; ?>
                  <?php if ($has['last_error']): ?><th>Error</th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr><td colspan="20" class="text-center text-body-secondary py-4">No modules match your filters.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                  <tr>
                    <td class="fw-semibold"><?= h($r['name'] ?? '—') ?></td>
                    <?php if ($has['slug']): ?><td class="small"><?= h($r['slug'] ?? '') ?></td><?php endif; ?>
                    <td class="small">v<?= h($r['version'] ?? '') ?></td>
                    <?php if ($has['enabled']): ?><td><?= badgeOnOff($r['enabled'] ?? 0) ?></td><?php endif; ?>
                    <?php if ($has['installed_at']): ?>
                      <td class="small">
                        <div><?= h(timeAgo($r['installed_at'] ?? '')) ?></div>
                        <?php if (!empty($r['installed_at'])): ?>
                          <div class="text-body-tertiary"><?= h(date('Y-m-d H:i', strtotime($r['installed_at']))) ?></div>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                    <?php if ($has['updated_at']): ?>
                      <td class="small">
                        <div><?= h(timeAgo($r['updated_at'] ?? '')) ?></div>
                        <?php if (!empty($r['updated_at'])): ?>
                          <div class="text-body-tertiary"><?= h(date('Y-m-d H:i', strtotime($r['updated_at']))) ?></div>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                    <?php if ($has['created_at']): ?>
                      <td class="small">
                        <div><?= h(timeAgo($r['created_at'] ?? '')) ?></div>
                        <?php if (!empty($r['created_at'])): ?>
                          <div class="text-body-tertiary"><?= h(date('Y-m-d H:i', strtotime($r['created_at']))) ?></div>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                    <td class="small">
                      <?php if (!empty($r['activity_at'])): ?>
                        <div><?= h(timeAgo($r['activity_at'])) ?></div>
                        <div class="text-body-tertiary"><?= h(date('Y-m-d H:i', strtotime($r['activity_at']))) ?></div>
                      <?php else: ?>
                        <span class="text-body-secondary">—</span>
                      <?php endif; ?>
                    </td>
                    <?php if ($has['author']): ?><td class="small"><?= h($r['author'] ?? '') ?></td><?php endif; ?>
                    <?php if ($has['homepage']): ?>
                      <td class="small"><?= !empty($r['homepage']) ? '<a href="'.h($r['homepage']).'" target="_blank" rel="noopener">Link</a>' : '' ?></td>
                    <?php endif; ?>
                    <?php if ($has['license']): ?><td class="small"><?= h($r['license'] ?? '') ?></td><?php endif; ?>
                    <?php if ($has['category']): ?><td class="small"><?= h($r['category'] ?? '') ?></td><?php endif; ?>
                    <?php if ($has['last_error']): ?>
                      <td class="small"><?= h(mb_strimwidth((string)($r['last_error'] ?? ''), 0, 80, '…', 'UTF-8')) ?></td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($total_pages > 1): ?>
            <div class="card-body">
              <nav aria-label="Modules pagination">
                <ul class="pagination justify-content-center mb-0">
                  <?php
                    $qs = function($p) use ($q,$status,$date,$per_page,$show){
                      return '?'.http_build_query([
                        'page'=>$p,'q'=>$q,'status'=>$status,'date'=>$date,'per_page'=>$per_page,'show'=>$show
                      ]);
                    };
                  ?>
                  <li class="page-item <?= $page<=1?'disabled':'' ?>">
                    <a class="page-link" href="<?= $qs(max(1,$page-1)) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                  </li>
                  <?php for ($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
                    <li class="page-item <?= $i===$page?'active':'' ?>">
                      <a class="page-link" href="<?= $qs($i) ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
                    <a class="page-link" href="<?= $qs(min($total_pages,$page+1)) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                  </li>
                </ul>
              </nav>
              <div class="text-center text-body-secondary small mt-2">
                Showing page <?= (int)$page ?> of <?= (int)$total_pages ?> (<?= number_format((int)$total) ?> total)
              </div>
            </div>
          <?php endif; ?>

        </div>
      </section>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('btnSidebarOpen')?.addEventListener('click', () => {
  document.querySelector('.admin-sidebar')?.classList.add('show');
});
document.getElementById('btnSidebarToggle')?.addEventListener('click', () => {
  document.querySelector('.admin-sidebar')?.classList.remove('show');
});
</script>
</body>
</html>