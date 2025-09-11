<?php
/**
 * PhPstrap Admin — Logs (uses working queries from older template)
 * - External sidebar include: /admin/includes/admin-sidebar.php
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
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'logs.php'));
    exit;
}

$admin = [
    'id'    => $_SESSION['admin_id']    ?? 1,
    'name'  => $_SESSION['admin_name']  ?? 'Administrator',
    'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
];

if (function_exists('logAdminActivity')) { try { logAdminActivity('logs_access', ['page' => 'logs']); } catch (Throwable $e) {} }

/* ------------------------------ Helpers ----------------------------------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function timeAgo($datetime) {
    $ts = strtotime((string)$datetime);
    if ($ts === false) return 'Unknown';
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hrs ago';
    if ($diff < 2592000) return floor($diff/86400) . ' days ago';
    return date('M j, Y g:i A', $ts);
}

function formatLoginReason($reason) {
    $reasons = [
        'successful_login'       => 'Successful Login',
        'successful_admin_login' => 'Admin Login Success',
        'invalid_password'       => 'Invalid Password',
        'user_not_found'         => 'User Not Found',
        'account_locked'         => 'Account Locked',
        'account_disabled'       => 'Account Disabled',
        'too_many_attempts'      => 'Too Many Attempts',
        'email_not_verified'     => 'Email Not Verified'
    ];
    return $reasons[$reason] ?? ucwords(str_replace('_', ' ', (string)$reason));
}

function formatActivityAction($action) {
    $actions = [
        'dashboard_access' => 'accessed the dashboard',
        'logs_access'      => 'viewed activity logs',
        'user_create'      => 'created a user account',
        'user_update'      => 'updated a user account',
        'user_delete'      => 'deleted a user account',
        'settings_update'  => 'updated system settings',
        'login'            => 'logged in',
        'logout'           => 'logged out',
        'module_enable'    => 'enabled a module',
        'module_disable'   => 'disabled a module',
        'password_change'  => 'changed password'
    ];
    return $actions[$action] ?? str_replace('_', ' ', (string)$action);
}

function getActionIcon($action) {
    $icons = [
        'dashboard_access' => 'tachometer-alt',
        'logs_access'      => 'list-alt',
        'user_create'      => 'user-plus',
        'user_update'      => 'user-edit',
        'user_delete'      => 'user-times',
        'settings_update'  => 'cog',
        'login'            => 'sign-in-alt',
        'logout'           => 'sign-out-alt',
        'module_enable'    => 'power-off',
        'module_disable'   => 'power-off',
        'password_change'  => 'key'
    ];
    return $icons[$action] ?? 'info-circle';
}

/* ------------------------------ DB/meta ----------------------------------- */
$db_available = false;
$pdo = null;
try { $pdo = getDbConnection(); $db_available = $pdo instanceof PDO; } catch (Throwable $e) {}

$system_info = ['app_version' => '1.0.0'];
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT value FROM settings WHERE `key`='app_version' LIMIT 1");
        if ($v = $stmt->fetchColumn()) $system_info['app_version'] = $v;
    }
} catch (Throwable $e) {}

function getCurrentSettings() {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT `key`, value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $settings[$row['key']] = $row['value'];
        return $settings;
    } catch (Throwable $e) { return []; }
}
$settings = getCurrentSettings();

/* ---------------------------- Data functions ------------------------------ */
/* Keep the same working queries from the older template */
function getLoginLogs($limit, $offset, $date_filter = '', $search = '') {
    try {
        $pdo = getDbConnection();
        $sql = "SELECT * FROM login_logs WHERE 1=1";
        $params = [];
        if ($date_filter) { $sql .= " AND DATE(created_at) = ?"; $params[] = $date_filter; }
        if ($search) {
            $sql .= " AND (email LIKE ? OR ip_address LIKE ? OR reason LIKE ?)";
            $term = "%$search%"; array_push($params, $term,$term,$term);
        }
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        array_push($params, (int)$limit, (int)$offset);
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}

function getActivityLogs($limit, $offset, $date_filter = '', $search = '') {
    try {
        $pdo = getDbConnection();
        // table exists check (literal LIKE — known to work)
        $exists = $pdo->query("SHOW TABLES LIKE 'admin_activity_log'");
        if (!$exists || !$exists->rowCount()) return [];
        $sql = "SELECT al.*, u.name AS admin_name, u.email AS admin_email
                FROM admin_activity_log al
                LEFT JOIN users u ON al.admin_id = u.id
                WHERE 1=1";
        $params = [];
        if ($date_filter) { $sql .= " AND DATE(al.created_at) = ?"; $params[] = $date_filter; }
        if ($search) {
            $sql .= " AND (al.action LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR al.ip_address LIKE ?)";
            $term = "%$search%"; array_push($params, $term,$term,$term,$term);
        }
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        array_push($params, (int)$limit, (int)$offset);
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}

function getTotalLogCount($log_type, $date_filter = '', $search = '') {
    try {
        $pdo = getDbConnection();
        $total = 0;
        if ($log_type === 'all' || $log_type === 'login') {
            $sql = "SELECT COUNT(*) FROM login_logs WHERE 1=1";
            $params = [];
            if ($date_filter) { $sql .= " AND DATE(created_at) = ?"; $params[] = $date_filter; }
            if ($search) {
                $sql .= " AND (email LIKE ? OR ip_address LIKE ? OR reason LIKE ?)";
                $term = "%$search%"; array_push($params, $term,$term,$term);
            }
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            $total += (int)$stmt->fetchColumn();
        }
        if ($log_type === 'all' || $log_type === 'activity') {
            $exists = $pdo->query("SHOW TABLES LIKE 'admin_activity_log'");
            if ($exists && $exists->rowCount()) {
                $sql = "SELECT COUNT(*)
                        FROM admin_activity_log al
                        LEFT JOIN users u ON al.admin_id = u.id
                        WHERE 1=1";
                $params = [];
                if ($date_filter) { $sql .= " AND DATE(al.created_at) = ?"; $params[] = $date_filter; }
                if ($search) {
                    $sql .= " AND (al.action LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR al.ip_address LIKE ?)";
                    $term = "%$search%"; array_push($params, $term,$term,$term,$term);
                }
                $stmt = $pdo->prepare($sql); $stmt->execute($params);
                $total += (int)$stmt->fetchColumn();
            }
        }
        return $total;
    } catch (Throwable $e) { return 0; }
}

/* ------------------------------ Parameters -------------------------------- */
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = 50;
$offset      = ($page - 1) * $limit;
$log_type    = $_GET['type']  ?? 'all';
$date_filter = $_GET['date']  ?? '';
$search      = trim($_GET['search'] ?? '');

/* ------------------------------ Fetch logs -------------------------------- */
$login_logs    = ($log_type === 'all' || $log_type === 'login')    ? getLoginLogs($limit, $offset, $date_filter, $search) : [];
$activity_logs = ($log_type === 'all' || $log_type === 'activity') ? getActivityLogs($limit, $offset, $date_filter, $search) : [];
$total_records = getTotalLogCount($log_type, $date_filter, $search);
$total_pages   = max(1, (int)ceil(($total_records ?: 1) / $limit));

/* ------------------------------ Merge/sort -------------------------------- */
$all_logs = [];
foreach ($login_logs as $log)    { $all_logs[] = ['type'=>'login',    'timestamp'=>$log['created_at'] ?? '', 'data'=>$log]; }
foreach ($activity_logs as $log) { $all_logs[] = ['type'=>'activity', 'timestamp'=>$log['created_at'] ?? '', 'data'=>$log]; }
usort($all_logs, fn($a,$b) => strtotime($b['timestamp'] ?? 0) <=> strtotime($a['timestamp'] ?? 0));

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Activity Logs - <?= h($settings['site_name'] ?? 'PhPstrap Admin') ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet"> <!-- small admin CSS -->
</head>
<body class="bg-body-tertiary">

<div class="d-flex">
  <!-- Sidebar -->
  <aside class="admin-sidebar bg-dark text-white">
    <div class="p-3 border-bottom border-secondary-subtle">
      <div class="d-flex align-items-center">
        <i class="fa-solid fa-shield-halved me-2"></i>
        <strong>PhPstrap Admin</strong>
      </div>
      <div class="small text-secondary mt-1">v<?= h($system_info['app_version']) ?></div>
    </div>
<?php
  // External sidebar include
  $activeKey     = 'logs';
  $sidebarBadges = []; // e.g. ['logs' => 2]
  $appVersion    = $system_info['app_version'] ?? '1.0.0';
  $adminName     = $admin['name'] ?? 'Admin';
  include __DIR__ . '/includes/admin-sidebar.php';
?>
  </aside>

  <!-- Content -->
  <main class="flex-grow-1">
    <header class="bg-white border-bottom">
      <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h1 class="h4 mb-0">Activity Logs</h1>
            <div class="small text-body-secondary mt-1">
              <i class="fa-regular fa-clock me-1"></i><?= date('l, F j, Y g:i A') ?>
              <span class="badge ms-2 <?= $db_available ? 'text-bg-success' : 'text-bg-danger' ?>">
                <?= $db_available ? 'Database Connected' : 'Database Offline' ?>
              </span>
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

      <?php if (!$db_available): ?>
        <div class="alert alert-warning my-3">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          Database connection unavailable; logs cannot be displayed.
        </div>
      <?php endif; ?>

      <!-- Filters -->
      <section class="mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary fw-semibold">
            <i class="fa-solid fa-filter me-2"></i>Filter Logs
          </div>
          <div class="card-body">
            <form method="get" class="row g-3">
              <div class="col-sm-6 col-md-3">
                <label class="form-label">Log Type</label>
                <select name="type" class="form-select">
                  <option value="all"     <?= $log_type==='all'?'selected':'' ?>>All Logs</option>
                  <option value="login"   <?= $log_type==='login'?'selected':'' ?>>Login Attempts</option>
                  <option value="activity"<?= $log_type==='activity'?'selected':'' ?>>Admin Activity</option>
                </select>
              </div>
              <div class="col-sm-6 col-md-3">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= h($date_filter) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Email, action, IP..." value="<?= h($search) ?>">
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Filter</button>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- Logs -->
      <section class="mb-4">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa-solid fa-history me-2"></i>System Logs</span>
            <span class="badge text-bg-secondary"><?= number_format((int)$total_records) ?> records</span>
          </div>

          <div class="list-group list-group-flush">
            <?php if (empty($all_logs)): ?>
              <div class="list-group-item py-4 text-center text-body-secondary">
                <i class="fa-regular fa-circle-question me-1"></i>No logs found — adjust filters above.
              </div>
            <?php else: ?>
              <?php foreach ($all_logs as $entry): $log = $entry['data']; if ($entry['type']==='login'): ?>
                <div class="list-group-item">
                  <div class="d-flex align-items-start">
                    <div class="me-3">
                      <span class="badge rounded-pill <?= !empty($log['success']) ? 'text-bg-success' : 'text-bg-danger' ?>">
                        <i class="fa-solid fa-<?= !empty($log['success']) ? 'check' : 'xmark' ?>"></i>
                      </span>
                    </div>
                    <div class="flex-grow-1">
                      <div class="fw-semibold">
                        <?= h($log['email'] ?? 'unknown@user') ?> &middot; <?= h(formatLoginReason($log['reason'] ?? 'login')) ?>
                      </div>
                      <div class="small text-body-secondary mt-1">
                        <i class="fa-solid fa-globe me-1"></i>IP: <?= h($log['ip_address'] ?? 'N/A') ?>
                        <?php if (!empty($log['user_agent'])): ?>
                          <span class="ms-2"><i class="fa-solid fa-desktop me-1"></i><?= h(mb_strimwidth((string)$log['user_agent'],0,80,'…','UTF-8')) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="text-nowrap small text-body-tertiary ms-3"><?= h(timeAgo($entry['timestamp'] ?? 'now')) ?></div>
                  </div>
                </div>
              <?php else: ?>
                <div class="list-group-item">
                  <div class="d-flex align-items-start">
                    <div class="me-3">
                      <span class="badge rounded-pill text-bg-primary">
                        <i class="fa-solid fa-<?= h(getActionIcon($log['action'] ?? 'info-circle')) ?>"></i>
                      </span>
                    </div>
                    <div class="flex-grow-1">
                      <div class="fw-semibold">
                        <?= h($log['admin_name'] ?? 'System') ?> <?= h(formatActivityAction($log['action'] ?? 'activity')) ?>
                      </div>
                      <div class="small text-body-secondary mt-1">
                        <i class="fa-solid fa-globe me-1"></i>IP: <?= h($log['ip_address'] ?? 'N/A') ?>
                        <?php if (!empty($log['admin_email'])): ?>
                          <span class="ms-2"><i class="fa-solid fa-envelope me-1"></i><?= h($log['admin_email']) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="text-nowrap small text-body-tertiary ms-3"><?= h(timeAgo($entry['timestamp'] ?? 'now')) ?></div>
                  </div>
                </div>
              <?php endif; endforeach; ?>
            <?php endif; ?>
          </div>

          <?php if ($total_pages > 1): ?>
            <div class="card-body">
              <nav aria-label="Logs pagination">
                <ul class="pagination justify-content-center mb-0">
                  <?php
                    $qsBase = function($p) use ($log_type,$date_filter,$search) {
                      return '?page='.$p.'&type='.urlencode($log_type).'&date='.urlencode($date_filter).'&search='.urlencode($search);
                    };
                  ?>
                  <li class="page-item <?= $page<=1?'disabled':'' ?>">
                    <a class="page-link" href="<?= $qsBase(max(1,$page-1)) ?>" tabindex="-1"><i class="fa-solid fa-chevron-left"></i></a>
                  </li>
                  <?php for ($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
                    <li class="page-item <?= $i===$page?'active':'' ?>">
                      <a class="page-link" href="<?= $qsBase($i) ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
                    <a class="page-link" href="<?= $qsBase(min($total_pages,$page+1)) ?>"><i class="fa-solid fa-chevron-right"></i></a>
                  </li>
                </ul>
              </nav>
              <div class="text-center text-body-secondary small mt-2">
                Showing page <?= (int)$page ?> of <?= (int)$total_pages ?> (<?= number_format((int)$total_records) ?> total)
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
// Optional mobile sidebar toggle (wire a button in header if you add one)
function toggleSidebar(){ document.querySelector('.admin-sidebar')?.classList.toggle('show'); }

// Auto-refresh every 30s if on first page with no filters and type=all
<?php $auto = ($page === 1 && empty($date_filter) && empty($search) && $log_type === 'all'); ?>
if (<?= $auto ? 'true' : 'false' ?>) {
  setTimeout(() => { if (!document.hidden) location.reload(); }, 30000);
}
</script>
</body>
</html>