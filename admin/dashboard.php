<?php
/**
 * PhPstrap Admin — Dashboard (Bootstrap-forward)
 * Uses the same layout/pattern as cleanup.php:
 * - Sidebar include: admin/includes/admin-sidebar.php
 * - Header with version + DB badge
 * - Cards using Bootstrap defaults + /assets/css/admin.css
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

/* ------------------------------- Includes --------------------------------- */
function safeInclude($file, $required = true) {
    if (file_exists($file)) {
        try { require_once $file; return true; }
        catch (Throwable $e) { error_log("Include error $file: ".$e->getMessage()); return false; }
    }
    if ($required) { error_log("Missing required include: $file"); }
    return false;
}

safeInclude('../config/database.php');
safeInclude('../config/app.php');

// Try common auth helpers (optional)
foreach (['admin-auth.php','includes/admin-auth.php','../includes/admin-auth.php','./admin-auth.php'] as $p) {
    if (file_exists($p)) { safeInclude($p, false); break; }
}

if (!defined('SITE_NAME'))       define('SITE_NAME', 'PhPstrap Admin');
if (!defined('CSRF_TOKEN_NAME')) define('CSRF_TOKEN_NAME', 'csrf_token');

/* ------------------------------ Bootstrap --------------------------------- */
if (function_exists('initializeApp')) {
    try { initializeApp(); } catch (Throwable $e) { if (session_status()===PHP_SESSION_NONE) @session_start(); }
} else {
    if (session_status()===PHP_SESSION_NONE) @session_start();
}

/* ------------------------------ Auth check -------------------------------- */
$is_authenticated = false;
if (!empty($_SESSION['admin_id']) || (!empty($_SESSION['admin_logged_in'])) ||
    (!empty($_SESSION['user_id']) && !empty($_SESSION['is_admin']))) {
    $is_authenticated = true;
}
if ($is_authenticated && function_exists('requireAdminAuth')) {
    try { requireAdminAuth(); } catch (Throwable $e) { $is_authenticated = false; }
}
if (!$is_authenticated) {
    $login_url = 'login.php';
    $current   = $_SERVER['REQUEST_URI'] ?? 'index.php';
    if (strpos($current, 'login.php') === false) $login_url .= '?redirect=' . urlencode($current);
    header("Location: $login_url"); exit;
}

/* ---------------------------- DB connection ------------------------------- */
$db_available = false;
function getDatabaseConnection() {
    global $db_available;
    try {
        if (function_exists('getDbConnection')) {
            $pdo = getDbConnection();
            if ($pdo instanceof PDO) { $db_available = true; return $pdo; }
        }
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
            $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, defined('DB_PASS')?DB_PASS:'', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $db_available = true; return $pdo;
        }
    } catch (Throwable $e) { error_log('DB error: '.$e->getMessage()); }
    $db_available = false;
    return null;
}
$pdo = getDatabaseConnection();

/* ------------------------------- Settings --------------------------------- */
$system_info = [
    'app_version' => '1.0.0',
    'installation_date' => 'Unknown',
    'php_version' => PHP_VERSION,
    'mysql_version' => 'Unknown',
    'database_size' => 0,
    'table_count' => 0,
    'memory_usage' => memory_get_usage(true),
    'disk_free_space' => disk_free_space('.') ?: 0,
];
if ($pdo) {
    try {
        // MySQL version
        $system_info['mysql_version'] = (string)$pdo->query("SELECT VERSION()")->fetchColumn();

        // DB size / tables
        $system_info['database_size'] = (float)$pdo->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
            FROM information_schema.tables WHERE table_schema = DATABASE()
        ")->fetchColumn();

        $system_info['table_count'] = (int)$pdo->query("
            SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
        ")->fetchColumn();

        // App version + install date from settings
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $stmt->execute(['app_version']);
        if ($v = $stmt->fetchColumn()) $system_info['app_version'] = $v;

        $stmt->execute(['installation_date']);
        if ($d = $stmt->fetchColumn()) $system_info['installation_date'] = $d;
    } catch (Throwable $e) {}
}

$admin = [
    'id'    => $_SESSION['admin_id']   ?? 1,
    'name'  => $_SESSION['admin_name'] ?? 'Administrator',
    'email' => $_SESSION['admin_email']?? ''
];

/* ----------------------------- Utilities ---------------------------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function formatBytes($size, $precision = 2) {
    if ($size === 0) return '0 B';
    $units = ['B','KB','MB','GB','TB'];
    $pow = min(floor(log($size, 1024)), count($units)-1);
    return round($size / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}
function formatMoney($amount, $currency = 'USD') { return '$' . number_format((float)$amount, 2); }
function timeAgo($datetime) {
    $ts = strtotime($datetime);
    if ($ts === false) return 'Unknown';
    $diff = time()-$ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . ' minutes ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    return date('M j, Y', $ts);
}
function getActionIcon($action) {
    // Font Awesome 6 friendly names
    $icons = [
        'dashboard_access' => 'gauge',
        'user_create'      => 'user-plus',
        'user_update'      => 'user-pen',
        'user_delete'      => 'user-xmark',
        'settings_update'  => 'gear',
        'login'            => 'right-to-bracket',
        'logout'           => 'right-from-bracket',
        'module_enable'    => 'power-off',
        'module_disable'   => 'power-off',
        'password_change'  => 'key',
    ];
    return $icons[$action] ?? 'circle-info';
}
function formatActivityAction($action) {
    $map = [
        'dashboard_access' => 'accessed the dashboard',
        'user_create'      => 'created a user account',
        'user_update'      => 'updated a user account',
        'user_delete'      => 'deleted a user account',
        'settings_update'  => 'updated system settings',
        'login'            => 'logged in',
        'logout'           => 'logged out',
        'module_enable'    => 'enabled a module',
        'module_disable'   => 'disabled a module',
        'password_change'  => 'changed password',
    ];
    return $map[$action] ?? str_replace('_', ' ', $action);
}

/* ----------------------------- Data loaders -------------------------------- */
function getUserStatistics($pdo) {
    $stats = [
        'total_users'=>0,'active_users'=>0,'verified_users'=>0,'admin_users'=>0,
        'premium_users'=>0,'free_users'=>0,'lifetime_users'=>0,
        'users_today'=>0,'users_this_week'=>0,'users_this_month'=>0,
        'recent_logins'=>0,'pending_verification'=>0,'total_credits'=>0
    ];
    if (!$pdo) return $stats;
    try {
        $stats['total_users']   = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['active_users']  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
        $stats['verified_users']= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE verified=1")->fetchColumn();
        $stats['pending_verification']= (int)$pdo->query("SELECT COUNT(*) FROM users WHERE verified=0")->fetchColumn();
        $stats['admin_users']   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=1")->fetchColumn();

        $rows = $pdo->query("SELECT membership_status, COUNT(*) c FROM users GROUP BY membership_status")->fetchAll();
        foreach ($rows as $r) {
            if ($r['membership_status']==='premium')  $stats['premium_users']  = (int)$r['c'];
            if ($r['membership_status']==='free')     $stats['free_users']     = (int)$r['c'];
            if ($r['membership_status']==='lifetime') $stats['lifetime_users'] = (int)$r['c'];
        }

        $stats['users_today']       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        $stats['users_this_week']   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)")->fetchColumn();
        $stats['users_this_month']  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetchColumn();
        $stats['recent_logins']     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(last_login_at)=CURDATE()")->fetchColumn();
        $stats['total_credits']     = (float)($pdo->query("SELECT SUM(credits) FROM users")->fetchColumn() ?: 0);
    } catch (Throwable $e) { error_log("User stats error: ".$e->getMessage()); }
    return $stats;
}

function getBusinessMetrics($pdo) {
    $m = [
        'total_affiliate_clicks'=>0,'total_signups_via_affiliate'=>0,
        'pending_withdrawals'=>0,'total_withdrawal_amount'=>0.0,
        'active_invites'=>0,'used_invites'=>0,
        'token_purchases'=>0,'total_revenue'=>0.0,
        'api_resellers'=>0,'password_resets_today'=>0,
    ];
    if (!$pdo) return $m;
    try {
        $m['total_affiliate_clicks']     = (int)$pdo->query("SELECT COUNT(*) FROM affiliate_clicks")->fetchColumn();
        $m['total_signups_via_affiliate']= (int)$pdo->query("SELECT COUNT(*) FROM affiliate_signups")->fetchColumn();
        $m['pending_withdrawals']        = (int)$pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
        $m['total_withdrawal_amount']    = (float)($pdo->query("SELECT SUM(amount) FROM withdrawals WHERE status='completed'")->fetchColumn() ?: 0);
        $m['active_invites']             = (int)$pdo->query("SELECT COUNT(*) FROM invites WHERE is_active=1 AND (expires_at IS NULL OR expires_at>NOW())")->fetchColumn();
        $m['used_invites']               = (int)$pdo->query("SELECT COUNT(*) FROM invites WHERE used_by IS NOT NULL")->fetchColumn();
        $m['token_purchases']            = (int)$pdo->query("SELECT COUNT(*) FROM token_purchases WHERE status='completed'")->fetchColumn();
        $m['total_revenue']              = (float)($pdo->query("SELECT SUM(price) FROM token_purchases WHERE status='completed'")->fetchColumn() ?: 0);
        $m['api_resellers']              = (int)$pdo->query("SELECT COUNT(*) FROM api_resellers WHERE is_active=1")->fetchColumn();
        $m['password_resets_today']      = (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    } catch (Throwable $e) { error_log("Biz metrics error: ".$e->getMessage()); }
    return $m;
}

function getSecurityInformation($pdo) {
    $s = [
        'active_tokens'=>0,'password_resets_today'=>0,'locked_accounts'=>0,
        'unverified_accounts'=>0,'admin_activities_today'=>0,'last_admin_activity'=>null,
        'api_tokens_active'=>0,'2fa_enabled_users'=>0,
    ];
    if (!$pdo) return $s;
    try {
        $s['active_tokens']           = (int)$pdo->query("SELECT COUNT(*) FROM user_tokens WHERE is_active=1 AND expires_at>NOW()")->fetchColumn();
        $s['password_resets_today']   = (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE DATE(created_at)=CURDATE() AND used=0")->fetchColumn();
        $s['locked_accounts']         = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE locked_until>NOW()")->fetchColumn();
        $s['unverified_accounts']     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE verified=0")->fetchColumn();
        $s['admin_activities_today']  = (int)$pdo->query("SELECT COUNT(*) FROM admin_activity_log WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        $s['last_admin_activity']     = (string)$pdo->query("SELECT created_at FROM admin_activity_log ORDER BY created_at DESC LIMIT 1")->fetchColumn();
        $s['api_tokens_active']       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE api_token IS NOT NULL")->fetchColumn();
        $s['2fa_enabled_users']       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE totp_enabled=1")->fetchColumn();
    } catch (Throwable $e) { error_log("Security info error: ".$e->getMessage()); }
    return $s;
}

function getRecentActivity($pdo, $limit = 10) {
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT aal.*, u.name AS admin_name
            FROM admin_activity_log aal
            LEFT JOIN users u ON aal.admin_id = u.id
            ORDER BY aal.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) { error_log("Recent activity error: ".$e->getMessage()); }
    return [];
}

/* ----------------------------- Load data ----------------------------------- */
$user_stats       = getUserStatistics($pdo);
$business_metrics = getBusinessMetrics($pdo);
$security_info    = getSecurityInformation($pdo);
$recent_logs      = getRecentActivity($pdo, 8);

/* -------------------------------- View ------------------------------------ */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard · PhPstrap Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet"> <!-- small admin css -->

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
  // mark Dashboard as active
  $activeKey = 'dashboard';
  // Optional badge counters (example): $sidebarBadges = ['users'=>3];
  include __DIR__ . '/includes/admin-sidebar.php';
?>
  </aside>

  <!-- Content -->
  <main class="flex-grow-1">
    <header class="bg-white border-bottom">
      <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h1 class="h4 mb-0">Dashboard Overview</h1>
            <div class="small text-body-secondary mt-1">
              <i class="fa-regular fa-clock me-1"></i><?= date('l, F j, Y g:i A') ?>
              <span class="badge ms-2 <?= $db_available ? 'text-bg-success' : 'text-bg-danger' ?>">
                <?= $db_available ? 'Database Connected' : 'Database Offline' ?>
              </span>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="text-body-secondary">Welcome, <strong><?= h($admin['name']) ?></strong></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">
              <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
            </a>
          </div>
        </div>
      </div>
    </header>

    <div class="admin-content container-fluid">

      <?php if (!$db_available): ?>
        <div class="alert alert-warning mt-3">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          Database connection unavailable; some stats may be missing.
        </div>
      <?php endif; ?>

      <!-- Key Metrics -->
      <section class="my-4">
        <div class="row g-3">
          <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm">
              <div class="card-body d-flex align-items-center">
                <div class="me-3 text-primary"><i class="fa-solid fa-users fa-lg"></i></div>
                <div>
                  <div class="text-secondary small text-uppercase">Total Users</div>
                  <div class="fs-4 fw-semibold"><?= number_format((int)$user_stats['total_users']) ?></div>
                  <?php if ($user_stats['users_this_week'] > 0): ?>
                    <div class="small text-success mt-1">
                      <i class="fa-solid fa-arrow-up"></i> +<?= (int)$user_stats['users_this_week'] ?> this week
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm">
              <div class="card-body d-flex align-items-center">
                <div class="me-3 text-success"><i class="fa-solid fa-user-check fa-lg"></i></div>
                <div>
                  <div class="text-secondary small text-uppercase">Active Users</div>
                  <div class="fs-4 fw-semibold"><?= number_format((int)$user_stats['active_users']) ?></div>
                  <div class="small text-body-secondary mt-1"><?= number_format((int)$user_stats['verified_users']) ?> verified</div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm">
              <div class="card-body d-flex align-items-center">
                <div class="me-3 text-info"><i class="fa-solid fa-sack-dollar fa-lg"></i></div>
                <div>
                  <div class="text-secondary small text-uppercase">Revenue</div>
                  <div class="fs-4 fw-semibold"><?= formatMoney($business_metrics['total_revenue']) ?></div>
                  <div class="small text-body-secondary mt-1"><?= number_format((int)$business_metrics['token_purchases']) ?> purchases</div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm">
              <div class="card-body d-flex align-items-center">
                <div class="me-3 text-warning"><i class="fa-solid fa-right-to-bracket fa-lg"></i></div>
                <div>
                  <div class="text-secondary small text-uppercase">Logins Today</div>
                  <div class="fs-4 fw-semibold"><?= number_format((int)$user_stats['recent_logins']) ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Detail Panels -->
      <section class="mb-4">
        <div class="row g-3">
          <!-- User analytics -->
          <div class="col-lg-4">
            <div class="card shadow-sm">
              <div class="card-header bg-white">
                <i class="fa-solid fa-users me-2 text-primary"></i>
                <strong>User Analytics</strong>
              </div>
              <div class="card-body">
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Total Users</span>
                  <span><?= number_format((int)$user_stats['total_users']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Active</span>
                  <span><?= number_format((int)$user_stats['active_users']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Verified</span>
                  <span><?= number_format((int)$user_stats['verified_users']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Admins</span>
                  <span><?= number_format((int)$user_stats['admin_users']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Premium</span>
                  <span><?= number_format((int)$user_stats['premium_users']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Free</span>
                  <span><?= number_format((int)$user_stats['free_users']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">New This Month</span>
                  <span><?= number_format((int)$user_stats['users_this_month']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                  <span class="text-secondary">2FA Enabled</span>
                  <span><?= number_format((int)$security_info['2fa_enabled_users']) ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Business metrics -->
          <div class="col-lg-4">
            <div class="card shadow-sm">
              <div class="card-header bg-white">
                <i class="fa-solid fa-chart-line me-2 text-primary"></i>
                <strong>Business Metrics</strong>
              </div>
              <div class="card-body">
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Total Revenue</span>
                  <span><?= formatMoney($business_metrics['total_revenue']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Token Purchases</span>
                  <span><?= number_format((int)$business_metrics['token_purchases']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Affiliate Clicks</span>
                  <span><?= number_format((int)$business_metrics['total_affiliate_clicks']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Affiliate Signups</span>
                  <span><?= number_format((int)$business_metrics['total_signups_via_affiliate']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                  <span class="text-secondary">Active Invites</span>
                  <span><?= number_format((int)$business_metrics['active_invites']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                  <span class="text-secondary">Withdrawals Paid</span>
                  <span><?= formatMoney($business_metrics['total_withdrawal_amount']) ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent admin activity -->
          <div class="col-lg-4">
            <div class="card shadow-sm">
              <div class="card-header bg-white">
                <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>
                <strong>Recent Admin Activity</strong>
              </div>
              <div class="list-group list-group-flush" style="max-height: 420px; overflow-y: auto;">
                <?php if (!empty($recent_logs)): ?>
                  <?php foreach ($recent_logs as $log): ?>
                    <div class="list-group-item d-flex">
                      <div class="me-3 text-primary">
                        <i class="fa-solid fa-<?= h(getActionIcon($log['action'] ?? '')) ?>"></i>
                      </div>
                      <div class="flex-grow-1">
                        <div class="small">
                          <strong><?= h($log['admin_name'] ?? 'System') ?></strong>
                          <?= h(formatActivityAction($log['action'] ?? 'activity')) ?>
                        </div>
                        <div class="text-body-secondary small mt-1">
                          <i class="fa-regular fa-clock me-1"></i><?= h(timeAgo($log['created_at'] ?? date('Y-m-d H:i:s'))) ?>
                          <?php if (!empty($log['ip_address'])): ?>
                            <span class="ms-2"><i class="fa-solid fa-globe me-1"></i><?= h($log['ip_address']) ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="list-group-item text-body-secondary small">No recent activity logged.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- System & Security -->
      <section class="mb-5">
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="card shadow-sm">
              <div class="card-header bg-white">
                <i class="fa-solid fa-server me-2 text-primary"></i>
                <strong>System Information</strong>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-sm-6">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                      <span class="text-secondary">PhPstrap Version</span>
                      <span><?= h($system_info['app_version']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                      <span class="text-secondary">Installed</span>
                      <span>
                        <?php $ts = strtotime($system_info['installation_date']); echo $ts ? date('M j, Y',$ts) : 'Unknown'; ?>
                      </span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                      <span class="text-secondary">PHP</span>
                      <span><?= h($system_info['php_version']) ?></span>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                      <span class="text-secondary">MySQL</span>
                      <span><?= h(explode('-', $system_info['mysql_version'])[0]) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                      <span class="text-secondary">DB Size</span>
                      <span><?= $system_info['database_size'] ? round((float)$system_info['database_size'],1).' MB' : 'N/A' ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                      <span class="text-secondary">Disk Free</span>
                      <span><?= formatBytes((int)$system_info['disk_free_space']) ?></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="card shadow-sm">
              <div class="card-header bg-white">
                <i class="fa-solid fa-shield-halved me-2 text-primary"></i>
                <strong>Security Overview</strong>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-sm-6">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                      <span class="text-secondary">Active Tokens</span>
                      <span><?= number_format((int)$security_info['active_tokens']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                      <span class="text-secondary">Resets Today</span>
                      <span><?= number_format((int)$security_info['password_resets_today']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                      <span class="text-secondary">Locked Accounts</span>
                      <span><?= number_format((int)$security_info['locked_accounts']) ?></span>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                      <span class="text-secondary">Unverified</span>
                      <span><?= number_format((int)$security_info['unverified_accounts']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                      <span class="text-secondary">Admin Actions Today</span>
                      <span><?= number_format((int)$security_info['admin_activities_today']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                      <span class="text-secondary">2FA Enabled</span>
                      <span><?= number_format((int)$security_info['2fa_enabled_users']) ?></span>
                    </div>
                    <?php if (!empty($security_info['last_admin_activity'])): ?>
                      <div class="text-body-secondary small mt-2">
                        <i class="fa-regular fa-clock me-1"></i>
                        Last admin activity: <?= h(timeAgo($security_info['last_admin_activity'])) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </section>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// (Optional) add a header toggle button later to collapse sidebar on mobile.
// Live time whisper update
setInterval(() => {
  const smalls = document.querySelectorAll('header .small.text-body-secondary');
  if (!smalls.length) return;
  const now = new Date().toLocaleTimeString('en-US');
  smalls[0].innerHTML = smalls[0].innerHTML.replace(/\d{1,2}:\d{2}:\d{2}\s[AP]M/, now);
}, 1000);
</script>
</body>
</html>