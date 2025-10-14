<?php
/**
 * PhPstrap Admin — Affiliate Logs
 * - Overview + detailed tabs for Clicks and Signups
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
  header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'affiliate-logs.php'));
  exit;
}
$admin = [
  'id'    => $_SESSION['admin_id']    ?? 1,
  'name'  => $_SESSION['admin_name']  ?? 'Administrator',
  'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
];
if (function_exists('logAdminActivity')) { try { logAdminActivity('logs_access', ['page' => 'affiliate-logs']); } catch (Throwable $e) {} }

/* ------------------------------ Helpers ----------------------------------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function timeAgo($datetime) {
  if (empty($datetime)) return '—';
  $ts = strtotime((string)$datetime);
  if ($ts === false) return h((string)$datetime);
  $diff = time() - $ts;
  if ($diff < 60)   return 'just now';
  if ($diff < 3600) return floor($diff/60) . ' min ago';
  if ($diff < 86400) return floor($diff/3600) . ' hrs ago';
  if ($diff < 2592000) return floor($diff/86400) . ' days ago';
  return date('M j, Y g:i A', $ts);
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

/* ------------------------------ Params ------------------------------------ */
// Overview “Show N”
$show_opts = [10,20,30,50,100];
$show = (int)($_GET['show'] ?? 10);
if (!in_array($show, $show_opts, true)) $show = 10;

// Tab: clicks | signups (default overview shows both)
$tab = $_GET['tab'] ?? 'overview';

// Clicks table filters
$c_page      = max(1, (int)($_GET['c_page'] ?? 1));
$c_per_opts  = [10,20,30,50,100];
$c_per_page  = (int)($_GET['c_per_page'] ?? 20);
if (!in_array($c_per_page, $c_per_opts, true)) $c_per_page = 20;
$c_offset    = ($c_page - 1) * $c_per_page;
$c_search    = trim($_GET['c_search'] ?? ''); // affiliate name/email, ip, referrer, browser
$c_date      = $_GET['c_date'] ?? '';         // YYYY-MM-DD (click_time)
$c_device    = $_GET['c_device'] ?? '';       // desktop|mobile|tablet
$c_converted = $_GET['c_converted'] ?? '';    // '', '1', '0'

// Signups table filters
$s_page      = max(1, (int)($_GET['s_page'] ?? 1));
$s_per_opts  = [10,20,30,50,100];
$s_per_page  = (int)($_GET['s_per_page'] ?? 20);
if (!in_array($s_per_page, $s_per_opts, true)) $s_per_page = 20;
$s_offset    = ($s_page - 1) * $s_per_page;
$s_search    = trim($_GET['s_search'] ?? ''); // affiliate/referred name/email
$s_date      = $_GET['s_date'] ?? '';         // YYYY-MM-DD (signup_time)
$s_status    = $_GET['s_status'] ?? '';       // pending|approved|paid|cancelled
$s_paid      = $_GET['s_paid'] ?? '';         // '', '1' (paid_at not null), '0'

/* -------------------------- Data (schema-backed) --------------------------- */
/* Tables/columns used (from your schema):
   - affiliate_clicks: user_id, click_time, ip_address, referrer, country, browser, device_type, conversion_value, converted, converted_at  :contentReference[oaicite:1]{index=1}
   - affiliate_signups: user_id, referred_user_id, signup_time, commission_amount, commission_rate, status, paid_at  :contentReference[oaicite:2]{index=2}
   - users: id, name, email                                                                   :contentReference[oaicite:3]{index=3}
*/

function buildClicksWhere(&$params, $date, $search, $device, $converted) {
  $where = "WHERE 1=1";
  if ($date) { $where .= " AND DATE(c.click_time) = ?"; $params[] = $date; }
  if ($device && in_array($device, ['desktop','mobile','tablet'], true)) {
    $where .= " AND c.device_type = ?";
    $params[] = $device;
  }
  if ($converted === '1' || $converted === '0') {
    $where .= " AND c.converted = ?";
    $params[] = (int)$converted;
  }
  if ($search !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR c.ip_address LIKE ? OR c.referrer LIKE ? OR c.browser LIKE ?)";
    $term = "%$search%"; array_push($params, $term,$term,$term,$term,$term);
  }
  return $where;
}

function clicksCount($date,$search,$device,$converted) {
  try {
    $pdo = getDbConnection(); $params = [];
    $where = buildClicksWhere($params,$date,$search,$device,$converted);
    $sql = "SELECT COUNT(*)
            FROM affiliate_clicks c
            LEFT JOIN users u ON u.id = c.user_id
            $where";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return (int)$stmt->fetchColumn();
  } catch (Throwable $e) { return 0; }
}

function clicksPaged($limit,$offset,$date,$search,$device,$converted) {
  try {
    $pdo = getDbConnection(); $params = [];
    $where = buildClicksWhere($params,$date,$search,$device,$converted);
    $sql = "SELECT c.*, u.name AS affiliate_name, u.email AS affiliate_email
            FROM affiliate_clicks c
            LEFT JOIN users u ON u.id = c.user_id
            $where
            ORDER BY c.click_time DESC
            LIMIT ? OFFSET ?";
    array_push($params, (int)$limit, (int)$offset);
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { return []; }
}

function buildSignupsWhere(&$params, $date, $search, $status, $paid) {
  $where = "WHERE 1=1";
  if ($date) { $where .= " AND DATE(s.signup_time) = ?"; $params[] = $date; }
  if ($status && in_array($status, ['pending','approved','paid','cancelled'], true)) {
    $where .= " AND s.status = ?"; $params[] = $status;
  }
  if ($paid === '1') { $where .= " AND s.paid_at IS NOT NULL"; }
  if ($paid === '0') { $where .= " AND s.paid_at IS NULL"; }
  if ($search !== '') {
    $where .= " AND (a.name LIKE ? OR a.email LIKE ? OR r.name LIKE ? OR r.email LIKE ?)";
    $term = "%$search%"; array_push($params, $term,$term,$term,$term);
  }
  return $where;
}

function signupsCount($date,$search,$status,$paid) {
  try {
    $pdo = getDbConnection(); $params = [];
    $where = buildSignupsWhere($params,$date,$search,$status,$paid);
    $sql = "SELECT COUNT(*)
            FROM affiliate_signups s
            LEFT JOIN users a ON a.id = s.user_id
            LEFT JOIN users r ON r.id = s.referred_user_id
            $where";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return (int)$stmt->fetchColumn();
  } catch (Throwable $e) { return 0; }
}

function signupsPaged($limit,$offset,$date,$search,$status,$paid) {
  try {
    $pdo = getDbConnection(); $params = [];
    $where = buildSignupsWhere($params,$date,$search,$status,$paid);
    $sql = "SELECT s.*, 
                   a.name AS affiliate_name, a.email AS affiliate_email,
                   r.name AS referred_name,  r.email AS referred_email
            FROM affiliate_signups s
            LEFT JOIN users a ON a.id = s.user_id
            LEFT JOIN users r ON r.id = s.referred_user_id
            $where
            ORDER BY s.signup_time DESC
            LIMIT ? OFFSET ?";
    array_push($params, (int)$limit, (int)$offset);
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { return []; }
}

function sumCommissions($status = null) {
  try {
    $pdo = getDbConnection();
    $sql = "SELECT COALESCE(SUM(commission_amount),0) FROM affiliate_signups";
    $params = [];
    if ($status && in_array($status, ['pending','approved','paid','cancelled'], true)) {
      $sql .= " WHERE status = ?"; $params[] = $status;
    }
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return (float)$stmt->fetchColumn();
  } catch (Throwable $e) { return 0.0; }
}

/* ----------------------------- Overview data ------------------------------ */
$recent_clicks   = clicksPaged($show, 0, '', '', '', '');
$recent_signups  = signupsPaged($show, 0, '', '', '', '');
$recent_converts = clicksPaged($show, 0, '', '', '', '1'); // converted = 1

$clicks_total    = clicksCount('', '', '', '');
$signups_total   = signupsCount('', '', '', '');
$sum_pending     = sumCommissions('pending');
$sum_approved    = sumCommissions('approved');
$sum_paid        = sumCommissions('paid');

/* ----------------------------- Tables (paged) ----------------------------- */
$c_total   = clicksCount($c_date,$c_search,$c_device,$c_converted);
$c_pages   = max(1, (int)ceil(($c_total ?: 1) / $c_per_page));
$clicks    = ($tab==='clicks') ? clicksPaged($c_per_page,$c_offset,$c_date,$c_search,$c_device,$c_converted) : [];

$s_total   = signupsCount($s_date,$s_search,$s_status,$s_paid);
$s_pages   = max(1, (int)ceil(($s_total ?: 1) / $s_per_page));
$signups   = ($tab==='signups') ? signupsPaged($s_per_page,$s_offset,$s_date,$s_search,$s_status,$s_paid) : [];

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Affiliate Logs - <?= h($settings['site_name'] ?? 'PhPstrap Admin') ?></title>

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
        <i class="fa-solid fa-handshake-angle me-2"></i>
        <strong>PhPstrap Admin</strong>
      </div>
      <div class="d-flex align-items-center gap-2">
        <div class="small text-secondary d-none d-lg-block">v<?= h($system_info['app_version']) ?></div>
        <button type="button" class="btn btn-sm btn-outline-light d-lg-none" id="btnSidebarToggle" aria-label="Close menu">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>
    <?php $activeKey = 'affiliate-logs'; include __DIR__ . '/includes/admin-sidebar.php'; ?>
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
              <h1 class="h4 mb-0">Affiliate Logs</h1>
              <div class="small text-body-secondary mt-1">
                <i class="fa-regular fa-clock me-1"></i><?= date('l, F j, Y g:i A') ?>
                <span class="badge ms-2 <?= $db_available ? 'text-bg-success' : 'text-bg-danger' ?>">
                  <?= $db_available ? 'Database Connected' : 'Database Offline' ?>
                </span>
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
          Database connection unavailable; affiliate logs cannot be displayed.
        </div>
      <?php endif; ?>

      <!-- Overview -->
      <section class="mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa-solid fa-gauge me-2"></i>Overview</span>
            <form method="get" class="d-flex align-items-center gap-2">
              <input type="hidden" name="tab" value="<?= h($tab) ?>">
              <label class="small text-body-secondary">Show</label>
              <select name="show" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ($show_opts as $opt): ?>
                  <option value="<?= $opt ?>" <?= $opt===$show?'selected':'' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
              <span class="small text-body-secondary">per list</span>
            </form>
          </div>
          <div class="card-body">
            <div class="row g-3">

              <!-- KPIs -->
              <div class="col-12">
                <div class="row g-3">
                  <div class="col-6 col-lg-3">
                    <div class="p-3 bg-white border rounded-3 h-100">
                      <div class="text-body-secondary small">Total Clicks</div>
                      <div class="h4 mb-0"><?= number_format($clicks_total) ?></div>
                    </div>
                  </div>
                  <div class="col-6 col-lg-3">
                    <div class="p-3 bg-white border rounded-3 h-100">
                      <div class="text-body-secondary small">Total Signups</div>
                      <div class="h4 mb-0"><?= number_format($signups_total) ?></div>
                    </div>
                  </div>
                  <div class="col-6 col-lg-2">
                    <div class="p-3 bg-white border rounded-3 h-100">
                      <div class="text-body-secondary small">Pending $</div>
                      <div class="h5 mb-0">$<?= number_format($sum_pending,2) ?></div>
                    </div>
                  </div>
                  <div class="col-6 col-lg-2">
                    <div class="p-3 bg-white border rounded-3 h-100">
                      <div class="text-body-secondary small">Approved $</div>
                      <div class="h5 mb-0">$<?= number_format($sum_approved,2) ?></div>
                    </div>
                  </div>
                  <div class="col-6 col-lg-2">
                    <div class="p-3 bg-white border rounded-3 h-100">
                      <div class="text-body-secondary small">Paid $</div>
                      <div class="h5 mb-0">$<?= number_format($sum_paid,2) ?></div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Recent Clicks -->
              <div class="col-12 col-xl-4">
                <div class="border rounded-3 h-100">
                  <div class="p-3 border-bottom fw-semibold">
                    <i class="fa-solid fa-mouse-pointer me-2"></i>Recent Clicks (<?= (int)$show ?>)
                  </div>
                  <div class="list-group list-group-flush">
                    <?php if (empty($recent_clicks)): ?>
                      <div class="list-group-item small text-body-secondary">No clicks yet.</div>
                    <?php else: foreach ($recent_clicks as $c): ?>
                      <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                          <div>
                            <div class="fw-semibold"><?= h($c['affiliate_name'] ?? '—') ?></div>
                            <div class="small text-body-secondary">
                              <i class="fa-regular fa-envelope me-1"></i><?= h($c['affiliate_email'] ?? '—') ?>
                              <?php if (!empty($c['ip_address'])): ?>
                                <span class="ms-2"><i class="fa-solid fa-globe me-1"></i><?= h($c['ip_address']) ?></span>
                              <?php endif; ?>
                            </div>
                            <?php if (!empty($c['referrer'])): ?>
                              <div class="small text-body-tertiary"><?= h(mb_strimwidth((string)$c['referrer'],0,80,'…','UTF-8')) ?></div>
                            <?php endif; ?>
                          </div>
                          <div class="text-end small">
                            <div><?= yesNoBadge($c['converted'] ?? 0, 'Converted', 'Click') ?></div>
                            <div class="text-body-tertiary"><?= h(timeAgo($c['click_time'] ?? '')) ?></div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; endif; ?>
                  </div>
                </div>
              </div>

              <!-- Recent Signups -->
              <div class="col-12 col-xl-4">
                <div class="border rounded-3 h-100">
                  <div class="p-3 border-bottom fw-semibold">
                    <i class="fa-solid fa-user-plus me-2"></i>Recent Signups (<?= (int)$show ?>)
                  </div>
                  <div class="list-group list-group-flush">
                    <?php if (empty($recent_signups)): ?>
                      <div class="list-group-item small text-body-secondary">No signups yet.</div>
                    <?php else: foreach ($recent_signups as $s): ?>
                      <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                          <div>
                            <div class="fw-semibold"><?= h($s['affiliate_name'] ?? '—') ?></div>
                            <div class="small text-body-secondary">
                              <i class="fa-regular fa-envelope me-1"></i><?= h($s['affiliate_email'] ?? '—') ?>
                            </div>
                            <div class="small">Referred: <?= h($s['referred_name'] ?? '—') ?> (<?= h($s['referred_email'] ?? '—') ?>)</div>
                          </div>
                          <div class="text-end small">
                            <div><span class="badge text-bg-info text-capitalize"><?= h($s['status'] ?? 'pending') ?></span></div>
                            <div class="text-body-tertiary"><?= h(timeAgo($s['signup_time'] ?? '')) ?></div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; endif; ?>
                  </div>
                </div>
              </div>

              <!-- Recent Conversions -->
              <div class="col-12 col-xl-4">
                <div class="border rounded-3 h-100">
                  <div class="p-3 border-bottom fw-semibold">
                    <i class="fa-solid fa-badge-dollar me-2"></i>Recent Conversions (<?= (int)$show ?>)
                  </div>
                  <div class="list-group list-group-flush">
                    <?php if (empty($recent_converts)): ?>
                      <div class="list-group-item small text-body-secondary">No conversions yet.</div>
                    <?php else: foreach ($recent_converts as $c): ?>
                      <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                          <div>
                            <div class="fw-semibold"><?= h($c['affiliate_name'] ?? '—') ?></div>
                            <div class="small text-body-secondary">
                              <i class="fa-regular fa-envelope me-1"></i><?= h($c['affiliate_email'] ?? '—') ?>
                            </div>
                            <?php if (!empty($c['conversion_value'])): ?>
                              <div class="small">Value: $<?= h(number_format((float)$c['conversion_value'],2)) ?></div>
                            <?php endif; ?>
                          </div>
                          <div class="text-end small">
                            <div class="badge text-bg-success">Converted</div>
                            <div class="text-body-tertiary">
                              <?= h(timeAgo($c['converted_at'] ?: $c['click_time'] ?? '')) ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; endif; ?>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </section>

      <!-- Tabs -->
      <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $tab==='overview'?'active':'' ?>" href="?tab=overview&show=<?= (int)$show ?>">Overview</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='clicks'?'active':'' ?>" href="?tab=clicks&show=<?= (int)$show ?>">Clicks</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='signups'?'active':'' ?>" href="?tab=signups&show=<?= (int)$show ?>">Signups</a></li>
      </ul>

      <?php if ($tab === 'clicks'): ?>
        <!-- Clicks Filters -->
        <section class="mb-3">
          <div class="card shadow-sm">
            <div class="card-header bg-body-tertiary fw-semibold">
              <i class="fa-solid fa-filter me-2"></i>Filter Clicks
            </div>
            <div class="card-body">
              <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="clicks">
                <div class="col-md-4">
                  <label class="form-label">Search</label>
                  <input type="text" name="c_search" class="form-control" placeholder="Affiliate, email, IP, referrer, browser…" value="<?= h($c_search) ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                  <label class="form-label">Date</label>
                  <input type="date" name="c_date" class="form-control" value="<?= h($c_date) ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                  <label class="form-label">Device</label>
                  <select name="c_device" class="form-select">
                    <option value="" <?= $c_device===''?'selected':'' ?>>Any</option>
                    <option value="desktop" <?= $c_device==='desktop'?'selected':'' ?>>Desktop</option>
                    <option value="mobile"  <?= $c_device==='mobile'?'selected':'' ?>>Mobile</option>
                    <option value="tablet"  <?= $c_device==='tablet'?'selected':'' ?>>Tablet</option>
                  </select>
                </div>
                <div class="col-sm-6 col-md-2">
                  <label class="form-label">Converted</label>
                  <select name="c_converted" class="form-select">
                    <option value=""  <?= $c_converted===''?'selected':'' ?>>Any</option>
                    <option value="1" <?= $c_converted==='1'?'selected':'' ?>>Yes</option>
                    <option value="0" <?= $c_converted==='0'?'selected':'' ?>>No</option>
                  </select>
                </div>
                <div class="col-sm-6 col-md-2">
                  <label class="form-label">Per Page</label>
                  <select name="c_per_page" class="form-select">
                    <?php foreach ($c_per_opts as $opt): ?>
                      <option value="<?= $opt ?>" <?= $opt===$c_per_page?'selected':'' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-md-2">
                  <button class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> Apply</button>
                </div>
              </form>
            </div>
          </div>
        </section>

        <!-- Clicks Table -->
        <section class="mb-4">
          <div class="card shadow-sm">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
              <span class="fw-semibold"><i class="fa-solid fa-mouse-pointer me-2"></i>Affiliate Clicks</span>
              <span class="badge text-bg-secondary"><?= number_format((int)$c_total) ?> total</span>
            </div>

            <div class="table-responsive">
              <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Affiliate</th>
                    <th>Click Time</th>
                    <th>IP / Country</th>
                    <th>Device</th>
                    <th>Browser</th>
                    <th>Referrer</th>
                    <th>Converted</th>
                    <th>Value</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($clicks)): ?>
                    <tr><td colspan="8" class="text-center text-body-secondary py-4">No clicks match your filters.</td></tr>
                  <?php else: foreach ($clicks as $c): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h($c['affiliate_name'] ?? '—') ?></div>
                        <div class="small text-body-secondary"><i class="fa-regular fa-envelope me-1"></i><?= h($c['affiliate_email'] ?? '—') ?></div>
                      </td>
                      <td class="small">
                        <div><?= h(timeAgo($c['click_time'] ?? '')) ?></div>
                        <?php if (!empty($c['click_time'])): ?>
                          <div class="text-body-tertiary"><?= h(date('Y-m-d H:i', strtotime($c['click_time']))) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="small">
                        <div><?= h($c['ip_address'] ?? '') ?></div>
                        <div class="text-body-tertiary"><?= h($c['country'] ?? '') ?></div>
                      </td>
                      <td class="small text-capitalize"><?= h($c['device_type'] ?? '') ?></td>
                      <td class="small"><?= h($c['browser'] ?? '') ?></td>
                      <td class="small"><?= h(mb_strimwidth((string)($c['referrer'] ?? ''), 0, 42, '…', 'UTF-8')) ?></td>
                      <td class="small">
                        <?= yesNoBadge($c['converted'] ?? 0) ?>
                        <?php if (!empty($c['converted_at'])): ?>
                          <div class="text-body-tertiary"><?= h(timeAgo($c['converted_at'])) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="small">$<?= h(number_format((float)($c['conversion_value'] ?? 0), 2)) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>

            <?php if ($c_pages > 1): ?>
              <div class="card-body">
                <nav aria-label="Clicks pagination">
                  <ul class="pagination justify-content-center mb-0">
                    <?php
                      $qs = function($p){
                        $keep = ['tab','show','c_search','c_date','c_device','c_converted','c_per_page'];
                        $params = [];
                        foreach ($keep as $k) if (isset($_GET[$k])) $params[$k] = $_GET[$k];
                        $params['c_page'] = $p;
                        return '?'.http_build_query($params);
                      };
                    ?>
                    <li class="page-item <?= $c_page<=1?'disabled':'' ?>">
                      <a class="page-link" href="<?= $qs(max(1,$c_page-1)) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                    </li>
                    <?php for ($i=max(1,$c_page-2); $i<=min($c_pages,$c_page+2); $i++): ?>
                      <li class="page-item <?= $i===$c_page?'active':'' ?>">
                        <a class="page-link" href="<?= $qs($i) ?>"><?= $i ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $c_page>=$c_pages?'disabled':'' ?>">
                      <a class="page-link" href="<?= $qs(min($c_pages,$c_page+1)) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                    </li>
                  </ul>
                </nav>
                <div class="text-center text-body-secondary small mt-2">
                  Showing page <?= (int)$c_page ?> of <?= (int)$c_pages ?> (<?= number_format((int)$c_total) ?> total)
                </div>
              </div>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($tab === 'signups'): ?>
        <!-- Signups Filters -->
        <section class="mb-3">
          <div class="card shadow-sm">
            <div class="card-header bg-body-tertiary fw-semibold">
              <i class="fa-solid fa-filter me-2"></i>Filter Signups
            </div>
            <div class="card-body">
              <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="signups">
                <div class="col-md-4">
                  <label class="form-label">Search</label>
                  <input type="text" name="s_search" class="form-control" placeholder="Affiliate/referred name or email…" value="<?= h($s_search) ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                  <label class="form-label">Date</label>
                  <input type="date" name="s_date" class="form-control" value="<?= h($s_date) ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                  <label class="form-label">Status</label>
                  <select name="s_status" class="form-select">
                    <option value="" <?= $s_status===''?'selected':'' ?>>Any</option>
                    <?php foreach (['pending','approved','paid','cancelled'] as $st): ?>
                      <option value="<?= $st ?>" <?= $s_status===$st?'selected':'' ?> class="text-capitalize"><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-sm-6 col-md-2">
                  <label class="form-label">Paid</label>
                  <select name="s_paid" class="form-select">
                    <option value=""  <?= $s_paid===''?'selected':'' ?>>Any</option>
                    <option value="1" <?= $s_paid==='1'?'selected':'' ?>>Yes</option>
                    <option value="0" <?= $s_paid==='0'?'selected':'' ?>>No</option>
                  </select>
                </div>
                <div class="col-sm-6 col-md-2">
                  <label class="form-label">Per Page</label>
                  <select name="s_per_page" class="form-select">
                    <?php foreach ($s_per_opts as $opt): ?>
                      <option value="<?= $opt ?>" <?= $opt===$s_per_page?'selected':'' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-md-2">
                  <button class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> Apply</button>
                </div>
              </form>
            </div>
          </div>
        </section>

        <!-- Signups Table -->
        <section class="mb-4">
          <div class="card shadow-sm">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
              <span class="fw-semibold"><i class="fa-solid fa-user-plus me-2"></i>Affiliate Signups</span>
              <span class="badge text-bg-secondary"><?= number_format((int)$s_total) ?> total</span>
            </div>

            <div class="table-responsive">
              <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Affiliate</th>
                    <th>Referred User</th>
                    <th>Signup Time</th>
                    <th>Status</th>
                    <th>Commission</th>
                    <th>Rate %</th>
                    <th>Paid</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($signups)): ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">No signups match your filters.</td></tr>
                  <?php else: foreach ($signups as $s): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h($s['affiliate_name'] ?? '—') ?></div>
                        <div class="small text-body-secondary"><i class="fa-regular fa-envelope me-1"></i><?= h($s['affiliate_email'] ?? '—') ?></div>
                      </td>
                      <td>
                        <div class="fw-semibold"><?= h($s['referred_name'] ?? '—') ?></div>
                        <div class="small text-body-secondary"><i class="fa-regular fa-envelope me-1"></i><?= h($s['referred_email'] ?? '—') ?></div>
                      </td>
                      <td class="small">
                        <div><?= h(timeAgo($s['signup_time'] ?? '')) ?></div>
                        <?php if (!empty($s['signup_time'])): ?>
                          <div class="text-body-tertiary"><?= h(date('Y-m-d H:i', strtotime($s['signup_time']))) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="small text-capitalize">
                        <span class="badge <?= ($s['status']==='paid')?'text-bg-success':(($s['status']==='approved')?'text-bg-primary':(($s['status']==='pending')?'text-bg-secondary':'text-bg-danger')) ?>">
                          <?= h($s['status'] ?? 'pending') ?>
                        </span>
                      </td>
                      <td class="small">$<?= h(number_format((float)($s['commission_amount'] ?? 0), 2)) ?></td>
                      <td class="small"><?= h(number_format((float)($s['commission_rate'] ?? 0), 2)) ?>%</td>
                      <td class="small">
                        <?= yesNoBadge(!empty($s['paid_at']), 'Paid', 'Unpaid') ?>
                        <?php if (!empty($s['paid_at'])): ?>
                          <div class="text-body-tertiary"><?= h(timeAgo($s['paid_at'])) ?></div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>

            <?php if ($s_pages > 1): ?>
              <div class="card-body">
                <nav aria-label="Signups pagination">
                  <ul class="pagination justify-content-center mb-0">
                    <?php
                      $qs = function($p){
                        $keep = ['tab','show','s_search','s_date','s_status','s_paid','s_per_page'];
                        $params = [];
                        foreach ($keep as $k) if (isset($_GET[$k])) $params[$k] = $_GET[$k];
                        $params['s_page'] = $p;
                        return '?'.http_build_query($params);
                      };
                    ?>
                    <li class="page-item <?= $s_page<=1?'disabled':'' ?>">
                      <a class="page-link" href="<?= $qs(max(1,$s_page-1)) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                    </li>
                    <?php for ($i=max(1,$s_page-2); $i<=min($s_pages,$s_page+2); $i++): ?>
                      <li class="page-item <?= $i===$s_page?'active':'' ?>">
                        <a class="page-link" href="<?= $qs($i) ?>"><?= $i ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $s_page>=$s_pages?'disabled':'' ?>">
                      <a class="page-link" href="<?= $qs(min($s_pages,$s_page+1)) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                    </li>
                  </ul>
                </nav>
                <div class="text-center text-body-secondary small mt-2">
                  Showing page <?= (int)$s_page ?> of <?= (int)$s_pages ?> (<?= number_format((int)$s_total) ?> total)
                </div>
              </div>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>

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

// Optional: auto-refresh Overview every 30s when tab=overview, no filters
<?php $auto = ($tab==='overview'); ?>
if (<?= $auto ? 'true' : 'false' ?>) {
  setTimeout(() => { if (!document.hidden) location.reload(); }, 30000);
}
</script>
</body>
</html>