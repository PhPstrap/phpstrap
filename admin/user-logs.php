<?php
/**
 * PhPstrap Admin — User Logs
 * - Overview cards: last N logins, unverified, verified
 * - Full users table with search, filters, pagination, and per-page selector
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
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'user-logs.php'));
    exit;
}

$admin = [
    'id'    => $_SESSION['admin_id']    ?? 1,
    'name'  => $_SESSION['admin_name']  ?? 'Administrator',
    'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
];

if (function_exists('logAdminActivity')) { try { logAdminActivity('logs_access', ['page' => 'user-logs']); } catch (Throwable $e) {} }

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

function badgeYesNo($bool, $yes='Verified', $no='Unverified'){
    return $bool
        ? '<span class="badge text-bg-success">'.$yes.'</span>'
        : '<span class="badge text-bg-secondary">'.$no.'</span>';
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

/* ------------------------------ Parameters -------------------------------- */
// Overview “N” selector for cards
$show_options = [10,20,30,50,100];
$show = (int)($_GET['show'] ?? 10);
if (!in_array($show, $show_options, true)) $show = 10;

// Full table params
$per_page_options = [10,20,30,50,100];
$per_page = (int)($_GET['per_page'] ?? 20);
if (!in_array($per_page, $per_page_options, true)) $per_page = 20;

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$q = trim($_GET['q'] ?? ''); // search (name/email/ip/company)
$filter_verified = $_GET['verified'] ?? '';    // '', '1', '0'
$filter_active   = $_GET['active']   ?? '';    // '', '1', '0'

/* ---------------------------- Data functions ------------------------------ */
function fetchLastLogins(int $limit): array {
    try {
        $pdo = getDbConnection();
        $sql = "SELECT id, name, email, last_login_at, last_login_ip, verified, is_active
                FROM users
                WHERE last_login_at IS NOT NULL
                ORDER BY last_login_at DESC
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

function fetchUnverified(int $limit): array {
    try {
        $pdo = getDbConnection();
        $sql = "SELECT id, name, email, created_at, last_verification_sent_at, verified
                FROM users
                WHERE COALESCE(verified, 0) = 0
                ORDER BY created_at DESC
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

function fetchVerified(int $limit): array {
    try {
        $pdo = getDbConnection();
        // Prefer verified_at, fallback to created_at ordering
        $sql = "SELECT id, name, email, verified_at, created_at, verified
                FROM users
                WHERE COALESCE(verified, 0) = 1
                ORDER BY COALESCE(verified_at, created_at) DESC
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

function buildUsersWhere(array &$params, string $q, string $verified, string $active): string {
    $where = "WHERE 1=1";
    if ($q !== '') {
        $where .= " AND (name LIKE ? OR email LIKE ? OR company_name LIKE ? OR last_login_ip LIKE ?)";
        $term = "%$q%";
        array_push($params, $term, $term, $term, $term);
    }
    if ($verified === '1' || $verified === '0') {
        $where .= " AND COALESCE(verified,0) = ?";
        $params[] = (int)$verified;
    }
    if ($active === '1' || $active === '0') {
        $where .= " AND COALESCE(is_active,0) = ?";
        $params[] = (int)$active;
    }
    return $where;
}

function countUsers(string $q, string $verified, string $active): int {
    try {
        $pdo = getDbConnection();
        $params = [];
        $where = buildUsersWhere($params, $q, $verified, $active);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function fetchUsersPaged(int $limit, int $offset, string $q, string $verified, string $active): array {
    try {
        $pdo = getDbConnection();
        $params = [];
        $where = buildUsersWhere($params, $q, $verified, $active);
        $sql = "SELECT
                    id, name, email, company_name, membership_status,
                    verified, verified_at, is_active,
                    last_login_at, last_login_ip,
                    created_at, updated_at
                FROM users
                $where
                ORDER BY COALESCE(last_login_at, created_at) DESC
                LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/* ------------------------------ Fetch data -------------------------------- */
$overview_last_logins = fetchLastLogins($show);
$overview_unverified  = fetchUnverified($show);
$overview_verified    = fetchVerified($show);

$total_users = countUsers($q, $filter_verified, $filter_active);
$total_pages = max(1, (int)ceil(($total_users ?: 1) / $per_page));
$users = fetchUsersPaged($per_page, $offset, $q, $filter_verified, $filter_active);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>User Logs - <?= h($settings['site_name'] ?? 'PhPstrap Admin') ?></title>

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
        <i class="fa-solid fa-users-viewfinder me-2"></i>
        <strong>PhPstrap Admin</strong>
      </div>
      <div class="d-flex align-items-center gap-2">
        <div class="small text-secondary d-none d-lg-block">v<?= h($system_info['app_version']) ?></div>
        <button type="button" class="btn btn-sm btn-outline-light d-lg-none" id="btnSidebarToggle" aria-label="Close menu">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>

    <?php
      // Mark this page active in your nav
      $activeKey = 'user-logs';
      include __DIR__ . '/includes/admin-sidebar.php';
    ?>
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
              <h1 class="h4 mb-0">User Logs</h1>
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
          Database connection unavailable; user logs cannot be displayed.
        </div>
      <?php endif; ?>

      <!-- Overview controls -->
      <section class="mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa-solid fa-gauge me-2"></i>Overview</span>
            <form method="get" class="d-flex align-items-center gap-2">
              <!-- preserve table filters when tweaking the overview size -->
              <input type="hidden" name="q" value="<?= h($q) ?>">
              <input type="hidden" name="verified" value="<?= h($filter_verified) ?>">
              <input type="hidden" name="active" value="<?= h($filter_active) ?>">
              <input type="hidden" name="per_page" value="<?= (int)$per_page ?>">
              <label class="small text-body-secondary">Show</label>
              <select name="show" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ($show_options as $opt): ?>
                  <option value="<?= $opt ?>" <?= $opt===$show?'selected':'' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
              <span class="small text-body-secondary">per overview list</span>
            </form>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <!-- Last N Logins -->
              <div class="col-12 col-xl-4">
                <div class="border rounded-3 h-100">
                  <div class="p-3 border-bottom fw-semibold">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>Last <?= (int)$show ?> Logins
                  </div>
                  <div class="list-group list-group-flush">
                    <?php if (empty($overview_last_logins)): ?>
                      <div class="list-group-item small text-body-secondary">No recent logins.</div>
                    <?php else: foreach ($overview_last_logins as $u): ?>
                      <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                          <div>
                            <div class="fw-semibold"><?= h($u['name'] ?? '—') ?></div>
                            <div class="small text-body-secondary">
                              <i class="fa-regular fa-envelope me-1"></i><?= h($u['email'] ?? '—') ?>
                              <span class="ms-2"><i class="fa-solid fa-globe me-1"></i><?= h($u['last_login_ip'] ?? '—') ?></span>
                            </div>
                          </div>
                          <div class="text-end small">
                            <div><?= badgeYesNo((int)($u['verified'] ?? 0) === 1) ?></div>
                            <div class="text-body-tertiary"><?= h(timeAgo($u['last_login_at'] ?? '')) ?></div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; endif; ?>
                  </div>
                </div>
              </div>

              <!-- Last N Unverified -->
              <div class="col-12 col-xl-4">
                <div class="border rounded-3 h-100">
                  <div class="p-3 border-bottom fw-semibold">
                    <i class="fa-regular fa-circle-xmark me-2"></i>Last <?= (int)$show ?> Unverified
                  </div>
                  <div class="list-group list-group-flush">
                    <?php if (empty($overview_unverified)): ?>
                      <div class="list-group-item small text-body-secondary">No unverified users.</div>
                    <?php else: foreach ($overview_unverified as $u): ?>
                      <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                          <div>
                            <div class="fw-semibold"><?= h($u['name'] ?? '—') ?></div>
                            <div class="small text-body-secondary">
                              <i class="fa-regular fa-envelope me-1"></i><?= h($u['email'] ?? '—') ?>
                            </div>
                          </div>
                          <div class="text-end small">
                            <div><?= badgeYesNo(false) ?></div>
                            <div class="text-body-tertiary">Joined <?= h(timeAgo($u['created_at'] ?? '')) ?></div>
                            <?php if (!empty($u['last_verification_sent_at'])): ?>
                              <div class="text-body-tertiary">Verification sent <?= h(timeAgo($u['last_verification_sent_at'])) ?></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; endif; ?>
                  </div>
                </div>
              </div>

              <!-- Last N Verified -->
              <div class="col-12 col-xl-4">
                <div class="border rounded-3 h-100">
                  <div class="p-3 border-bottom fw-semibold">
                    <i class="fa-regular fa-circle-check me-2"></i>Last <?= (int)$show ?> Verified
                  </div>
                  <div class="list-group list-group-flush">
                    <?php if (empty($overview_verified)): ?>
                      <div class="list-group-item small text-body-secondary">No verified users yet.</div>
                    <?php else: foreach ($overview_verified as $u): ?>
                      <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                          <div>
                            <div class="fw-semibold"><?= h($u['name'] ?? '—') ?></div>
                            <div class="small text-body-secondary">
                              <i class="fa-regular fa-envelope me-1"></i><?= h($u['email'] ?? '—') ?>
                            </div>
                          </div>
                          <div class="text-end small">
                            <div><?= badgeYesNo(true) ?></div>
                            <div class="text-body-tertiary">Verified <?= h(timeAgo($u['verified_at'] ?? $u['created_at'] ?? '')) ?></div>
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

      <!-- Filters for full table -->
      <section class="mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary fw-semibold">
            <i class="fa-solid fa-filter me-2"></i>Filter Users
          </div>
          <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Name, email, company, IP…" value="<?= h($q) ?>">
              </div>
              <div class="col-sm-6 col-md-2">
                <label class="form-label">Verified</label>
                <select name="verified" class="form-select">
                  <option value=""  <?= $filter_verified===''?'selected':'' ?>>Any</option>
                  <option value="1" <?= $filter_verified==='1'?'selected':'' ?>>Verified</option>
                  <option value="0" <?= $filter_verified==='0'?'selected':'' ?>>Unverified</option>
                </select>
              </div>
              <div class="col-sm-6 col-md-2">
                <label class="form-label">Active</label>
                <select name="active" class="form-select">
                  <option value=""  <?= $filter_active===''?'selected':'' ?>>Any</option>
                  <option value="1" <?= $filter_active==='1'?'selected':'' ?>>Active</option>
                  <option value="0" <?= $filter_active==='0'?'selected':'' ?>>Inactive</option>
                </select>
              </div>
              <div class="col-sm-6 col-md-2">
                <label class="form-label">Per Page</label>
                <select name="per_page" class="form-select">
                  <?php foreach ($per_page_options as $opt): ?>
                    <option value="<?= $opt ?>" <?= $opt===$per_page?'selected':'' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-6 col-md-2">
                <label class="form-label d-block">&nbsp;</label>
                <button class="btn btn-primary w-100">
                  <i class="fa-solid fa-magnifying-glass me-1"></i> Apply
                </button>
              </div>
              <!-- keep overview 'show' consistent -->
              <input type="hidden" name="show" value="<?= (int)$show ?>">
            </form>
          </div>
        </div>
      </section>

      <!-- Users table -->
      <section class="mb-4">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa-solid fa-users me-2"></i>Users</span>
            <span class="badge text-bg-secondary"><?= number_format((int)$total_users) ?> total</span>
          </div>

          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Name / Email</th>
                  <th>Company</th>
                  <th>Membership</th>
                  <th>Verified</th>
                  <th>Active</th>
                  <th>Last Login</th>
                  <th>IP</th>
                  <th>Created</th>
                  <th>Updated</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($users)): ?>
                  <tr><td colspan="10" class="text-center text-body-secondary py-4">No users match your filters.</td></tr>
                <?php else: foreach ($users as $u): ?>
                  <tr>
                    <td class="text-body-secondary"><?= (int)$u['id'] ?></td>
                    <td>
                      <div class="fw-semibold"><?= h($u['name'] ?? '—') ?></div>
                      <div class="small text-body-secondary"><i class="fa-regular fa-envelope me-1"></i><?= h($u['email'] ?? '—') ?></div>
                    </td>
                    <td class="small"><?= h($u['company_name'] ?? '') ?></td>
                    <td class="small"><?= h($u['membership_status'] ?? '') ?></td>
                    <td><?= badgeYesNo((int)($u['verified'] ?? 0) === 1) ?></td>
                    <td><?= (int)($u['is_active'] ?? 0) === 1
                          ? '<span class="badge text-bg-success">Active</span>'
                          : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                    <td class="small">
                      <div><?= h(timeAgo($u['last_login_at'] ?? '')) ?></div>
                      <?php if (!empty($u['last_login_at'])): ?>
                        <div class="text-body-tertiary"><?= h(date('Y-m-d H:i', strtotime($u['last_login_at']))) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="small"><?= h($u['last_login_ip'] ?? '') ?></td>
                    <td class="small">
                      <div><?= h(timeAgo($u['created_at'] ?? '')) ?></div>
                      <?php if (!empty($u['created_at'])): ?>
                        <div class="text-body-tertiary"><?= h(date('Y-m-d H:i', strtotime($u['created_at']))) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="small">
                      <div><?= h(timeAgo($u['updated_at'] ?? '')) ?></div>
                      <?php if (!empty($u['updated_at'])): ?>
                        <div class="text-body-tertiary"><?= h(date('Y-m-d H:i', strtotime($u['updated_at']))) ?></div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
            <div class="card-body">
              <nav aria-label="Users pagination">
                <ul class="pagination justify-content-center mb-0">
                  <?php
                    $qsBase = function($p) use ($q,$filter_verified,$filter_active,$per_page,$show) {
                      return '?page='.$p
                        .'&q='.urlencode($q)
                        .'&verified='.urlencode($filter_verified)
                        .'&active='.urlencode($filter_active)
                        .'&per_page='.$per_page
                        .'&show='.$show;
                    };
                  ?>
                  <li class="page-item <?= $page<=1?'disabled':'' ?>">
                    <a class="page-link" href="<?= $qsBase(max(1,$page-1)) ?>" tabindex="-1">
                      <i class="fa-solid fa-chevron-left"></i>
                    </a>
                  </li>
                  <?php for ($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
                    <li class="page-item <?= $i===$page?'active':'' ?>">
                      <a class="page-link" href="<?= $qsBase($i) ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
                    <a class="page-link" href="<?= $qsBase(min($total_pages,$page+1)) ?>">
                      <i class="fa-solid fa-chevron-right"></i>
                    </a>
                  </li>
                </ul>
              </nav>
              <div class="text-center text-body-secondary small mt-2">
                Showing page <?= (int)$page ?> of <?= (int)$total_pages ?> (<?= number_format((int)$total_users) ?> total)
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
// Sidebar toggle (if you wire the buttons in your header)
document.getElementById('btnSidebarOpen')?.addEventListener('click', () => {
  document.querySelector('.admin-sidebar')?.classList.add('show');
});
document.getElementById('btnSidebarToggle')?.addEventListener('click', () => {
  document.querySelector('.admin-sidebar')?.classList.remove('show');
});
</script>
</body>
</html>