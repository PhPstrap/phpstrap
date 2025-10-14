<?php
/**
 * PhPstrap Admin — User Actions
 * - Activate (verify) users who haven't verified email
 * - Resend verification email
 * - Suspend / Unsuspend accounts
 * - Add credits
 * - Add premium membership (+1 month / +1 year)
 *
 * Bootstrap 5.3 + Font Awesome + /assets/css/admin.css
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

/* ------------------------------- App/Auth --------------------------------- */
initializeApp();
if (function_exists('requireAdminAuth')) { requireAdminAuth(); }
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
  header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'user-actions.php'));
  exit;
}

$admin = [
  'id'    => $_SESSION['admin_id']    ?? 1,
  'name'  => $_SESSION['admin_name']  ?? 'Administrator',
  'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
];

if (function_exists('logAdminActivity')) { try { logAdminActivity('user_actions_access', ['page' => 'user-actions']); } catch (Throwable $e) {} }

/* -------------------------------- Helpers --------------------------------- */
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

function appSetting($key, $default=null) {
  static $cache = null;
  if ($cache === null) {
    $cache = [];
    try {
      $pdo = getDbConnection();
      $stmt = $pdo->query("SELECT `key`, value FROM settings");
      while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $cache[$r['key']] = $r['value']; }
    } catch (Throwable $e) {}
  }
  return $cache[$key] ?? $default;
}

function baseUrlGuess() {
  // Prefer DB setting if present
  $site = appSetting('site_url');
  if ($site) return rtrim($site, '/');
  // Build from request
  $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $proto . '://' . $host;
}

/* ------------------------------ CSRF tokens ------------------------------- */
if (empty($_SESSION['csrf_user_actions'])) {
  $_SESSION['csrf_user_actions'] = bin2hex(random_bytes(16));
}
function csrf_token(){ return $_SESSION['csrf_user_actions']; }
function csrf_check($token){ return hash_equals($_SESSION['csrf_user_actions'] ?? '', (string)$token); }

/* --------------------------- Data access helpers -------------------------- */
function getUsersPaged($filter, $search, $limit, $offset) {
  try {
    $pdo = getDbConnection();
    $where = "WHERE 1=1";
    $params = [];
    if ($filter === 'unverified') { $where .= " AND (verified = 0 OR verified IS NULL)"; }
    elseif ($filter === 'suspended') { $where .= " AND (is_active = 0)"; }
    if ($search !== '') {
      $like = "%$search%";
      $where .= " AND (name LIKE ? OR email LIKE ? OR affiliate_id LIKE ?)";
      array_push($params, $like, $like, $like);
    }
    $sql = "SELECT id, name, email, verified, verified_at, last_verification_sent_at,
                   is_active, credits, membership_status, membership_expiry,
                   last_login_at, last_login_ip, created_at
            FROM users
            $where
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";
    array_push($params, (int)$limit, (int)$offset);
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { return []; }
}

function countUsers($filter, $search) {
  try {
    $pdo = getDbConnection();
    $where = "WHERE 1=1";
    $params = [];
    if ($filter === 'unverified') { $where .= " AND (verified = 0 OR verified IS NULL)"; }
    elseif ($filter === 'suspended') { $where .= " AND (is_active = 0)"; }
    if ($search !== '') {
      $like = "%$search%";
      $where .= " AND (name LIKE ? OR email LIKE ? OR affiliate_id LIKE ?)";
      array_push($params, $like, $like, $like);
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where"); $stmt->execute($params);
    return (int)$stmt->fetchColumn();
  } catch (Throwable $e) { return 0; }
}

/* ---------------------------- Action functions ---------------------------- */
function activateUser($userId) {
  try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // Ensure verification token cleared and mark verified
    $stmt = $pdo->prepare("UPDATE users
                           SET verified=1,
                               verified_at=NOW(),
                               verification_token=NULL,
                               is_active=1
                           WHERE id=?");
    $stmt->execute([(int)$userId]);

    $pdo->commit();
    if (function_exists('logAdminActivity')) logAdminActivity('user_verify_manual', ['user_id' => (int)$userId]);
    return true;
  } catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    return false;
  }
}

function suspendUser($userId) {
  try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?");
    $ok = $stmt->execute([(int)$userId]);
    if ($ok && function_exists('logAdminActivity')) logAdminActivity('user_suspend', ['user_id' => (int)$userId]);
    return $ok;
  } catch (Throwable $e) { return false; }
}
function unsuspendUser($userId) {
  try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE users SET is_active=1, locked_until=NULL, login_attempts=0 WHERE id=?");
    $ok = $stmt->execute([(int)$userId]);
    if ($ok && function_exists('logAdminActivity')) logAdminActivity('user_unsuspend', ['user_id' => (int)$userId]);
    return $ok;
  } catch (Throwable $e) { return false; }
}

function addCredits($userId, $amount) {
  $amount = (float)$amount;
  if (!is_finite($amount) || abs($amount) < 0.0001) return false;
  try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id=?");
    $ok = $stmt->execute([$amount, (int)$userId]);
    if ($ok && function_exists('logAdminActivity')) logAdminActivity('user_add_credits', ['user_id' => (int)$userId, 'amount' => $amount]);
    return $ok;
  } catch (Throwable $e) { return false; }
}

function extendMembership($userId, $months) {
  $months = (int)$months;
  if ($months <= 0) return false;
  try {
    $pdo = getDbConnection();
    // If membership_expiry in future, extend from then, else from now
    $sql = "UPDATE users
            SET membership_status = 'premium',
                membership_expiry = CASE
                  WHEN membership_expiry IS NULL OR membership_expiry < NOW()
                    THEN DATE_ADD(NOW(), INTERVAL :m MONTH)
                  ELSE DATE_ADD(membership_expiry, INTERVAL :m2 MONTH)
                END
            WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([':m'=>$months, ':m2'=>$months, ':id'=>(int)$userId]);
    if ($ok && function_exists('logAdminActivity')) logAdminActivity('user_extend_membership', ['user_id' => (int)$userId, 'months' => $months]);
    return $ok;
  } catch (Throwable $e) { return false; }
}

/* ---------------------- Verification email (resend) ----------------------- */
/**
 * Attempts to (re)send verification email.
 * - Ensures a verification_token exists, generating one if needed.
 * - Uses app mailer if available (sendEmail), else falls back to mail().
 * - Updates last_verification_sent_at.
 */
function resendVerification($userId) {
  try {
    $pdo = getDbConnection();
    // fetch user
    $stmt = $pdo->prepare("SELECT id, email, name, verification_token, verified FROM users WHERE id=? LIMIT 1");
    $stmt->execute([(int)$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) return false;

    if (!filter_var($u['email'] ?? '', FILTER_VALIDATE_EMAIL)) return false;
    if (!($u['verified'] ?? 0)) {
      // create token if missing
      if (empty($u['verification_token'])) {
        $token = bin2hex(random_bytes(24));
        $stmt2 = $pdo->prepare("UPDATE users SET verification_token=? WHERE id=?");
        $stmt2->execute([$token, (int)$userId]);
        $u['verification_token'] = $token;
      }
      $verifyUrl = rtrim(baseUrlGuess(), '/') . "/verify.php?token=" . urlencode($u['verification_token']);

      $siteName = appSetting('site_name', 'PhPstrap');
      $subject = "Verify your $siteName account";
      $body = "Hi " . ($u['name'] ?: 'there') . ",\n\n"
            . "Please verify your account by clicking the link below:\n\n"
            . $verifyUrl . "\n\n"
            . "If you didn’t create an account, you can ignore this message.\n\n"
            . "— $siteName";

      $sent = false;
      if (function_exists('sendEmail')) {
        try { $sent = (bool)sendEmail($u['email'], $subject, nl2br($body)); } catch (Throwable $e) { $sent = false; }
      } else {
        // minimal fallback
        $headers = "MIME-Version: 1.0\r\nContent-type: text/plain; charset=UTF-8\r\n";
        @mail($u['email'], $subject, $body, $headers);
        $sent = true; // assume OK
      }

      $stmt3 = $pdo->prepare("UPDATE users SET last_verification_sent_at=NOW() WHERE id=?");
      $stmt3->execute([(int)$userId]);

      if (function_exists('logAdminActivity')) logAdminActivity('user_resend_verification', ['user_id' => (int)$userId, 'email'=>$u['email']]);
      return $sent;
    }
    return true; // already verified; nothing to send
  } catch (Throwable $e) { return false; }
}

/* ------------------------------- Parameters -------------------------------- */
$db_available = false; $pdo = null;
try { $pdo = getDbConnection(); $db_available = $pdo instanceof PDO; } catch (Throwable $e) {}

$site_name = appSetting('site_name', 'PhPstrap Admin');

$filter     = $_GET['filter'] ?? 'unverified'; // unverified|suspended|all
$search     = trim($_GET['q'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_opts   = [10,20,30,50,100];
$per_page   = (int)($_GET['per_page'] ?? 20);
if (!in_array($per_page, $per_opts, true)) $per_page = 20;
$offset     = ($page - 1) * $per_page;

/* --------------------------------- Actions --------------------------------- */
$flash = ['type'=>null,'msg'=>null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action  = $_POST['action']  ?? '';
  $uid     = (int)($_POST['user_id'] ?? 0);
  $csrf    = $_POST['csrf']    ?? '';
  $ok = false;

  if (!csrf_check($csrf)) {
    $flash = ['type'=>'danger', 'msg'=>'Invalid CSRF token. Please refresh and try again.'];
  } elseif ($uid <= 0) {
    $flash = ['type'=>'danger', 'msg'=>'Invalid user.'];
  } else {
    switch ($action) {
      case 'activate':
        $ok = activateUser($uid);
        $flash = ['type'=>$ok?'success':'danger', 'msg'=>$ok?'User activated and marked verified.':'Failed to activate user.'];
        break;

      case 'resend_verification':
        $ok = resendVerification($uid);
        $flash = ['type'=>$ok?'success':'danger', 'msg'=>$ok?'Verification email sent (or already verified).':'Failed to send verification email.'];
        break;

      case 'suspend':
        $ok = suspendUser($uid);
        $flash = ['type'=>$ok?'warning':'danger', 'msg'=>$ok?'User suspended.':'Failed to suspend user.'];
        break;

      case 'unsuspend':
        $ok = unsuspendUser($uid);
        $flash = ['type'=>$ok?'success':'danger', 'msg'=>$ok?'User unsuspended.':'Failed to unsuspend user.'];
        break;

      case 'add_credits':
        $amount = (float)($_POST['amount'] ?? 0);
        $ok = addCredits($uid, $amount);
        $flash = ['type'=>$ok?'success':'danger', 'msg'=>$ok?('Added credits: ' . number_format($amount, 2)): 'Failed to add credits.'];
        break;

      case 'add_premium_1m':
        $ok = extendMembership($uid, 1);
        $flash = ['type'=>$ok?'success':'danger', 'msg'=>$ok?'Added 1 month of premium.':'Failed to update membership.'];
        break;

      case 'add_premium_1y':
        $ok = extendMembership($uid, 12);
        $flash = ['type'=>$ok?'success':'danger', 'msg'=>$ok?'Added 1 year of premium.':'Failed to update membership.'];
        break;

      default:
        $flash = ['type'=>'danger','msg'=>'Unknown action.'];
    }
  }

  // preserve filter/search paging on redirect
  $qs = http_build_query([
    'filter'=>$filter, 'q'=>$search, 'page'=>$page, 'per_page'=>$per_page
  ]);
  // Use PRG to avoid duplicate posts
  $_SESSION['flash_user_actions'] = $flash;
  header("Location: user-actions.php?$qs");
  exit;
}

// get flash
if (isset($_SESSION['flash_user_actions'])) {
  $flash = $_SESSION['flash_user_actions'];
  unset($_SESSION['flash_user_actions']);
}

/* ----------------------------- Fetch user list ---------------------------- */
$total_records = countUsers($filter, $search);
$total_pages   = max(1, (int)ceil(($total_records ?: 1) / $per_page));
$users         = getUsersPaged($filter, $search, $per_page, $offset);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>User Actions - <?= h($site_name) ?></title>

<link href="/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet">
<style>
.badge-yes { background:#28a745; }
.badge-no  { background:#6c757d; }
.table thead th { white-space: nowrap; }
.action-btns .btn { margin: 2px 2px; }
.credits-input { width: 110px; }
</style>
</head>
<body class="bg-body-tertiary">

<div class="d-flex">
  <!-- Sidebar -->
  <aside class="admin-sidebar bg-dark text-white">
    <div class="p-3 border-bottom border-secondary-subtle d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <i class="fa-solid fa-user-gear me-2"></i>
        <strong>PhPstrap Admin</strong>
      </div>
      <button type="button" class="btn btn-sm btn-outline-light d-lg-none" id="btnSidebarToggle" aria-label="Close menu">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <?php $activeKey = 'user-actions'; include __DIR__ . '/includes/admin-nav.php'; ?>
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
              <h1 class="h4 mb-0">User Actions</h1>
              <div class="small text-body-secondary mt-1">
                <i class="fa-regular fa-clock me-1"></i><?= date('l, F j, Y g:i A') ?>
                <span class="badge ms-2 <?= $db_available ? 'text-bg-success' : 'text-bg-danger' ?>">
                  <?= $db_available ? 'Database Connected' : 'Database Offline' ?>
                </span>
              </div>
            </div>
          </div>
          <div class="text-body-secondary">
            Welcome, <strong><?= h($admin['name']) ?></strong>
          </div>
        </div>
      </div>
    </header>

    <div class="admin-content container-fluid">

      <?php if ($flash['type'] && $flash['msg']): ?>
        <div class="alert alert-<?= h($flash['type']) ?> my-3">
          <i class="fa-solid fa-circle-info me-2"></i><?= h($flash['msg']) ?>
        </div>
      <?php endif; ?>

      <?php if (!$db_available): ?>
        <div class="alert alert-warning my-3">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          Database connection unavailable; user actions cannot be displayed.
        </div>
      <?php endif; ?>

      <!-- Filters -->
      <section class="mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary fw-semibold">
            <i class="fa-solid fa-filter me-2"></i>Filter Users
          </div>
          <div class="card-body">
            <form method="get" class="row g-3">
              <div class="col-sm-6 col-md-3">
                <label class="form-label">Filter</label>
                <select name="filter" class="form-select">
                  <option value="unverified" <?= $filter==='unverified'?'selected':'' ?>>Unverified</option>
                  <option value="suspended"  <?= $filter==='suspended'?'selected':'' ?>>Suspended</option>
                  <option value="all"        <?= $filter==='all'?'selected':'' ?>>All</option>
                </select>
              </div>
              <div class="col-sm-6 col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Name, email, affiliate id..." value="<?= h($search) ?>">
              </div>
              <div class="col-sm-6 col-md-3">
                <label class="form-label">Per Page</label>
                <select name="per_page" class="form-select">
                  <?php foreach ($per_opts as $opt): ?>
                    <option value="<?= $opt ?>" <?= $opt===$per_page?'selected':'' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-6 col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>Apply</button>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- Table -->
      <section class="mb-4">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa-solid fa-users-gear me-2"></i>Manage Users</span>
            <span class="badge text-bg-secondary"><?= number_format((int)$total_records) ?> total</span>
          </div>

          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>User</th>
                  <th>Status</th>
                  <th>Membership</th>
                  <th>Credits</th>
                  <th>Verification</th>
                  <th>Last Login</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($users)): ?>
                <tr><td colspan="7" class="text-center text-body-secondary py-4">No users match your filters.</td></tr>
              <?php else: foreach ($users as $u): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h($u['name'] ?: '—') ?></div>
                    <div class="text-body-secondary small"><?= h($u['email'] ?: '—') ?></div>
                    <div class="text-body-tertiary small">ID: <?= (int)$u['id'] ?> · Joined <?= h(timeAgo($u['created_at'])) ?></div>
                  </td>
                  <td class="small">
                    <div><?= ($u['is_active'] ?? 1) ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Suspended</span>' ?></div>
                    <div class="mt-1"><?= ($u['verified'] ?? 0) ? '<span class="badge text-bg-success">Verified</span>' : '<span class="badge text-bg-warning text-dark">Unverified</span>' ?></div>
                  </td>
                  <td class="small">
                    <div class="fw-semibold text-capitalize"><?= h($u['membership_status'] ?: '—') ?></div>
                    <div class="text-body-tertiary"><?= !empty($u['membership_expiry']) ? ('Exp: ' . h(date('Y-m-d', strtotime($u['membership_expiry'])))) : '' ?></div>
                  </td>
                  <td class="small">
                    <?= number_format((float)($u['credits'] ?? 0), 2) ?>
                  </td>
                  <td class="small">
                    <div>Sent: <?= h($u['last_verification_sent_at'] ? timeAgo($u['last_verification_sent_at']) : '—') ?></div>
                    <div>At: <?= h($u['verified_at'] ? date('Y-m-d', strtotime($u['verified_at'])) : '—') ?></div>
                  </td>
                  <td class="small">
                    <div><?= h($u['last_login_at'] ? timeAgo($u['last_login_at']) : '—') ?></div>
                    <div class="text-body-tertiary"><?= h($u['last_login_ip'] ?: '') ?></div>
                  </td>
                  <td class="text-nowrap action-btns">
                    <!-- Activate -->
                    <?php if (!($u['verified'] ?? 0)): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="action" value="activate">
                      <button class="btn btn-success btn-sm" title="Activate (mark verified)">
                        <i class="fa-solid fa-check me-1"></i>Activate
                      </button>
                    </form>
                    <?php endif; ?>

                    <!-- Resend verification -->
                    <?php if (!($u['verified'] ?? 0)): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="action" value="resend_verification">
                      <button class="btn btn-outline-primary btn-sm" title="Resend verification email">
                        <i class="fa-solid fa-envelope me-1"></i>Resend
                      </button>
                    </form>
                    <?php endif; ?>

                    <!-- Suspend/Unsuspend -->
                    <?php if (($u['is_active'] ?? 1) == 1): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="action" value="suspend">
                      <button class="btn btn-outline-warning btn-sm" title="Suspend">
                        <i class="fa-solid fa-user-slash me-1"></i>Suspend
                      </button>
                    </form>
                    <?php else: ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="action" value="unsuspend">
                      <button class="btn btn-outline-success btn-sm" title="Unsuspend">
                        <i class="fa-solid fa-user-check me-1"></i>Unsuspend
                      </button>
                    </form>
                    <?php endif; ?>

                    <!-- Add credits -->
                    <form method="post" class="d-inline-flex align-items-center ms-1">
                      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="action" value="add_credits">
                      <input type="number" step="0.01" min="-100000" name="amount" class="form-control form-control-sm credits-input" placeholder="+0.00">
                      <button class="btn btn-outline-secondary btn-sm ms-1" title="Add credits">
                        <i class="fa-solid fa-plus me-1"></i>Add
                      </button>
                    </form>

                    <!-- Membership +1m / +1y -->
                    <form method="post" class="d-inline ms-1">
                      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="action" value="add_premium_1m">
                      <button class="btn btn-outline-info btn-sm" title="Add 1 month premium">
                        +1m
                      </button>
                    </form>
                    <form method="post" class="d-inline ms-1">
                      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <input type="hidden" name="action" value="add_premium_1y">
                      <button class="btn btn-outline-info btn-sm" title="Add 1 year premium">
                        +1y
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($total_pages > 1): ?>
            <div class="card-body">
              <nav aria-label="Users pagination">
                <ul class="pagination justify-content-center mb-0">
                  <?php
                    $qs = function($p) use ($filter,$search,$per_page){
                      return '?'.http_build_query([
                        'page'=>$p,'filter'=>$filter,'q'=>$search,'per_page'=>$per_page
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
                Showing page <?= (int)$page ?> of <?= (int)$total_pages ?> (<?= number_format((int)$total_records) ?> total)
              </div>
            </div>
          <?php endif; ?>

        </div>
      </section>

      <div class="alert alert-info my-3">
        <i class="fa-solid fa-lightbulb me-2"></i>
        Tip: “Activate” sets <code>verified=1</code>, <code>verified_at=NOW()</code>, clears <code>verification_token</code> and sets <code>is_active=1</code>.
        “Suspend” toggles <code>is_active</code> only; it doesn’t alter membership/credits.
      </div>

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