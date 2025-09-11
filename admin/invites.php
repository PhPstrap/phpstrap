<?php
/**
 * PhPstrap Admin — Invite Management (Bootstrap-forward)
 * - Uses external sidebar include (admin/includes/admin-sidebar.php)
 * - Relies on /assets/css/admin.css for small admin styling
 * - Keeps existing invite behaviors (create/toggle/delete/cleanup)
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ------------------------------- Includes -----------------------------------
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

// ------------------------------ App/bootstrap --------------------------------
if (function_exists('initializeApp')) {
    try { initializeApp(); } catch (Throwable $e) { if (session_status()===PHP_SESSION_NONE) @session_start(); }
} else {
    if (session_status()===PHP_SESSION_NONE) @session_start();
}

// ------------------------------ Auth check -----------------------------------
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
    $current   = $_SERVER['REQUEST_URI'] ?? 'invites.php';
    if (strpos($current, 'login.php') === false) $login_url .= '?redirect=' . urlencode($current);
    header("Location: $login_url"); exit;
}

// --------------------------- DB connection & meta ----------------------------
$db_available = false;
function getDatabaseConnectionLocal() {
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
$pdo = getDatabaseConnectionLocal();

$system_info = ['app_version' => '1.0.0'];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
        $stmt->execute(['app_version']);
        if ($v = $stmt->fetchColumn()) $system_info['app_version'] = $v;
    } catch (Throwable $e) {}
}

$admin = [
    'id'    => $_SESSION['admin_id']    ?? 1,
    'name'  => $_SESSION['admin_name']  ?? 'Administrator',
    'email' => $_SESSION['admin_email'] ?? ''
];

// -------------------------------- Utilities ----------------------------------
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$csrfKey = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'PhPstrap_csrf_token';
if (empty($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION[$csrfKey];

// --------------------------- Invite settings/helpers --------------------------
function getInviteSettings($pdo) {
    $defaults = [
        'invite_only_mode'          => '0',
        'invites_per_week'          => '1',
        'invite_expiry_days'        => '30',
        'invite_auto_approve'       => '1',
        'invite_max_uses'           => '1',
        'invite_allow_user_creation'=> '1',
        'invite_admin_only'         => '0'
    ];
    if (!$pdo) return $defaults;
    try {
        $keys = array_keys($defaults);
        $ph   = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT `key`,`value`,`default_value` FROM settings WHERE `key` IN ($ph)");
        $stmt->execute($keys);
        $settings = $defaults;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $val = ($row['value'] !== null && $row['value'] !== '') ? $row['value'] : $row['default_value'];
            $settings[$row['key']] = $val;
        }
        return $settings;
    } catch (Throwable $e) {
        error_log("invite settings err: ".$e->getMessage());
        return $defaults;
    }
}
$invite_settings = getInviteSettings($pdo);

// A slightly stronger code generator (8 chars, non-ambiguous Base32-ish)
function generateInviteCode($pdo, $length = 8) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $attempts = 0;
    do {
        $code = '';
        $bytes = random_bytes($length);
        $alen  = strlen($alphabet);
        for ($i=0; $i<$length; $i++) {
            $code .= $alphabet[ord($bytes[$i]) % $alen];
        }
        $stmt = $pdo->prepare("SELECT id FROM invites WHERE code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetchColumn() ? true : false;
        $attempts++;
    } while ($exists && $attempts < 10);
    return $exists ? null : $code;
}

function createInvite($pdo, $generated_by, $max_uses, $expiry_days, $custom_message = '', $email = '', $invite_type = 'registration') {
    $code = generateInviteCode($pdo);
    if (!$code) return false;
    try {
        $expires_at = null;
        if ((int)$expiry_days > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));
        }
        $stmt = $pdo->prepare("
            INSERT INTO invites (code, generated_by, email, max_uses, invite_type, custom_message, expires_at, created_at, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ");
        return $stmt->execute([$code, $generated_by, $email, (int)$max_uses, $invite_type, $custom_message, $expires_at]) ? $code : false;
    } catch (Throwable $e) {
        error_log("create invite err: ".$e->getMessage());
        return false;
    }
}

function getAllInvites($pdo, $limit = 50, $offset = 0, $filter = '') {
    if (!$pdo) return [];
    try {
        $where = '';
        $bind  = [];
        if ($filter !== '') {
            $where = " WHERE (i.code LIKE ? OR i.custom_message LIKE ? OR i.email LIKE ? OR u.name LIKE ?)";
            $f = "%{$filter}%";
            $bind = [$f,$f,$f,$f];
        }
        $sql = "
            SELECT 
                i.*,
                u.name  AS creator_name,
                u.email AS creator_email,
                u2.name AS used_by_name,
                u2.email AS used_by_email,
                CASE 
                    WHEN i.expires_at IS NOT NULL AND i.expires_at < NOW() THEN 'expired'
                    WHEN i.max_uses > 0 AND i.uses_count >= i.max_uses THEN 'exhausted'
                    WHEN i.is_active = 1 THEN 'available'
                    ELSE 'inactive'
                END AS effective_status
            FROM invites i
            LEFT JOIN users u  ON i.generated_by = u.id
            LEFT JOIN users u2 ON i.used_by     = u2.id
            {$where}
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $bind[] = (int)$limit;
        $bind[] = (int)$offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("fetch invites err: ".$e->getMessage());
        return [];
    }
}

function getInviteStats($pdo) {
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_invites,
                SUM(CASE WHEN is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND (max_uses = 0 OR uses_count < max_uses) THEN 1 ELSE 0 END) as active_invites,
                SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < NOW() THEN 1 ELSE 0 END) as expired_invites,
                SUM(CASE WHEN max_uses > 0 AND uses_count >= max_uses THEN 1 ELSE 0 END) as exhausted_invites,
                SUM(uses_count) as total_uses,
                COUNT(DISTINCT generated_by) as unique_creators,
                COUNT(CASE WHEN used_by IS NOT NULL THEN 1 END) as used_invites
            FROM invites
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log("invite stats err: ".$e->getMessage());
        return [];
    }
}

function getRecentInviteUsage($pdo, $limit = 20) {
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                i.*,
                u.name  as creator_name,
                u2.name as used_by_name,
                u2.email as used_by_email
            FROM invites i
            LEFT JOIN users u  ON i.generated_by = u.id
            LEFT JOIN users u2 ON i.used_by     = u2.id
            WHERE i.used_by IS NOT NULL
            ORDER BY i.used_at DESC
            LIMIT ?
        ");
        $stmt->execute([(int)$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("recent usage err: ".$e->getMessage());
        return [];
    }
}

// --------------------------------- Actions -----------------------------------
$message = '';
$error   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST[$csrfKey]) || !hash_equals($_SESSION[$csrfKey] ?? '', $_POST[$csrfKey])) {
        $message = 'Invalid security token. Please try again.';
        $error   = true;
    } else {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'create_invite':
                $max_uses      = (int)($_POST['max_uses']     ?? $invite_settings['invite_max_uses'] ?? 1);
                $expiry_days   = (int)($_POST['expiry_days']  ?? $invite_settings['invite_expiry_days'] ?? 30);
                $custom_message= trim($_POST['custom_message'] ?? '');
                $email         = trim($_POST['email']          ?? '');
                $invite_type   = $_POST['invite_type']         ?? 'registration';

                if ($max_uses < 0) $max_uses = 0;
                if ($expiry_days < 0 || $expiry_days > 365) $expiry_days = 30;

                $code = $pdo ? createInvite($pdo, $admin['id'], $max_uses, $expiry_days, $custom_message, $email, $invite_type) : false;
                if ($code) { $message = "Invite code '<code>".h($code)."</code>' created successfully!"; $error = false; }
                else { $message = "Failed to create invite code. Please try again."; $error = true; }
            break;

            case 'toggle_status':
                $invite_id = (int)($_POST['invite_id'] ?? 0);
                $new_status= (int)($_POST['new_status'] ?? 0);
                if ($pdo && $invite_id > 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE invites SET is_active = ? WHERE id = ?");
                        $ok = $stmt->execute([$new_status, $invite_id]);
                        $message = $ok ? "Invite status updated." : "Failed to update invite status.";
                        $error   = !$ok;
                    } catch (Throwable $e) { $message = "Error updating invite status."; $error = true; }
                }
            break;

            case 'delete_invite':
                $invite_id = (int)($_POST['invite_id'] ?? 0);
                if ($pdo && $invite_id > 0) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM invites WHERE id = ?");
                        $ok = $stmt->execute([$invite_id]);
                        $message = $ok ? "Invite deleted successfully." : "Failed to delete invite.";
                        $error   = !$ok;
                    } catch (Throwable $e) { $message = "Error deleting invite."; $error = true; }
                }
            break;

            case 'cleanup_expired':
                if ($pdo) {
                    try {
                        $stmt = $pdo->query("UPDATE invites SET is_active = 0 WHERE expires_at IS NOT NULL AND expires_at < NOW() AND is_active = 1");
                        $affected = $stmt->rowCount();
                        $message = "Deactivated {$affected} expired invite(s).";
                        $error   = false;
                    } catch (Throwable $e) { $message = "Error cleaning up expired invites."; $error = true; }
                }
            break;
        }
    }
}

// --------------------------------- Fetch data --------------------------------
$filter   = $_GET['filter'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$invites       = $pdo ? getAllInvites($pdo, $per_page, $offset, $filter) : [];
$stats         = $pdo ? getInviteStats($pdo) : [];
$recent_usage  = $pdo ? getRecentInviteUsage($pdo, 10) : [];

// ------------------------------------ View -----------------------------------
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invites · PhPstrap Admin</title>

<link href="/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet"> <!-- new small admin css -->
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
  $activeKey     = 'invites';
  $sidebarBadges = []; // e.g. ['invites' => 3]
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
            <h1 class="h4 mb-0">Invite Management</h1>
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

      <?php if ($message): ?>
        <div class="alert alert-<?= $error ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
          <i class="fa-solid fa-<?= $error ? 'triangle-exclamation' : 'circle-check' ?> me-1"></i>
          <?= $message ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$db_available): ?>
        <div class="alert alert-warning">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          Database connection unavailable; invite tools are disabled.
        </div>
      <?php endif; ?>

      <!-- Stats -->
      <section class="mb-4">
        <div class="row g-3">
          <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm">
              <div class="card-body text-center py-4">
                <div class="fs-2 fw-bold"><?= (int)($stats['total_invites'] ?? 0) ?></div>
                <div class="text-body-secondary">Total Invites</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm">
              <div class="card-body text-center py-4">
                <div class="fs-2 fw-bold text-success"><?= (int)($stats['active_invites'] ?? 0) ?></div>
                <div class="text-body-secondary">Active</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm">
              <div class="card-body text-center py-4">
                <div class="fs-2 fw-bold"><?= (int)($stats['total_uses'] ?? 0) ?></div>
                <div class="text-body-secondary">Total Uses</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm">
              <div class="card-body text-center py-4">
                <div class="fs-2 fw-bold text-warning"><?= (int)($stats['used_invites'] ?? 0) ?></div>
                <div class="text-body-secondary">Used Invites</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div class="row g-3">
        <!-- Create -->
        <div class="col-lg-4">
          <div class="card shadow-sm">
            <div class="card-header bg-body-tertiary fw-semibold">
              <i class="fa-solid fa-plus me-2"></i>Create New Invite
            </div>
            <div class="card-body">
              <form method="post" class="vstack gap-3">
                <input type="hidden" name="action" value="create_invite">
                <input type="hidden" name="<?= h($csrfKey) ?>" value="<?= h($CSRF) ?>">

                <div>
                  <label class="form-label">Invite Type</label>
                  <select class="form-select" name="invite_type">
                    <option value="registration">Registration</option>
                    <option value="premium">Premium</option>
                    <option value="admin">Admin</option>
                  </select>
                </div>

                <div>
                  <label class="form-label">Maximum Uses</label>
                  <input type="number" class="form-control" name="max_uses"
                         value="<?= h($invite_settings['invite_max_uses'] ?? 1) ?>" min="0" max="1000">
                  <div class="form-text">0 = unlimited uses</div>
                </div>

                <div>
                  <label class="form-label">Expires in (days)</label>
                  <input type="number" class="form-control" name="expiry_days"
                         value="<?= h($invite_settings['invite_expiry_days'] ?? 30) ?>" min="0" max="365">
                  <div class="form-text">0 = never expires</div>
                </div>

                <div>
                  <label class="form-label">Email (optional)</label>
                  <input type="email" class="form-control" name="email" placeholder="Specific email for this invite">
                </div>

                <div>
                  <label class="form-label">Custom Message (optional)</label>
                  <textarea class="form-control" name="custom_message" rows="2" placeholder="Optional message or note"></textarea>
                </div>

                <button class="btn btn-primary">
                  <i class="fa-solid fa-plus me-1"></i>Create Invite
                </button>
              </form>
            </div>
          </div>

          <div class="card shadow-sm">
            <div class="card-header bg-body-tertiary fw-semibold">
              <i class="fa-solid fa-wrench me-2"></i>Quick Actions
            </div>
            <div class="card-body">
              <form method="post" class="mb-2">
                <input type="hidden" name="<?= h($csrfKey) ?>" value="<?= h($CSRF) ?>">
                <input type="hidden" name="action" value="cleanup_expired">
                <button class="btn btn-warning w-100" onclick="return confirm('Deactivate all expired invites?')">
                  <i class="fa-solid fa-broom me-1"></i>Cleanup Expired
                </button>
              </form>
              <div class="text-center small text-body-secondary">
                Invite Only Mode:
                <span class="badge <?= ($invite_settings['invite_only_mode'] ?? '0') === '1' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                  <?= ($invite_settings['invite_only_mode'] ?? '0') === '1' ? 'Enabled' : 'Disabled' ?>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- List -->
        <div class="col-lg-8">
          <div class="card shadow-sm">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
              <span class="fw-semibold"><i class="fa-solid fa-list me-2"></i>All Invites</span>
              <form method="get" class="d-flex">
                <input type="text" class="form-control form-control-sm me-2" name="filter" value="<?= h($filter) ?>" placeholder="Search invites...">
                <button class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
              </form>
            </div>
            <div class="card-body p-0">
              <?php if (empty($invites)): ?>
                <div class="text-center p-4">
                  <i class="fa-regular fa-envelope-open fa-3x text-body-secondary mb-2"></i>
                  <div class="text-body-secondary">No invites found</div>
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Creator</th>
                        <th>Uses</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($invites as $invite): ?>
                        <tr>
                          <td style="min-width:180px">
                            <span class="copy-code" role="button" onclick="copyInviteCode('<?= h($invite['code']) ?>', this)" title="Click to copy">
                              <i class="fa-regular fa-copy me-1"></i><?= h($invite['code']) ?>
                            </span>
                            <?php if (!empty($invite['custom_message'])): ?>
                              <div class="small text-body-secondary"><?= h($invite['custom_message']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($invite['email'])): ?>
                              <div class="small text-info"><?= h($invite['email']) ?></div>
                            <?php endif; ?>
                          </td>
                          <td><span class="badge text-bg-secondary"><?= h(ucfirst($invite['invite_type'])) ?></span></td>
                          <td class="small">
                            <?= h($invite['creator_name'] ?? 'System') ?>
                            <?php if (!empty($invite['creator_email'])): ?>
                              <div class="text-body-secondary"><?= h($invite['creator_email']) ?></div>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if ((int)$invite['max_uses'] === 0): ?>
                              <span class="badge text-bg-info"><?= (int)$invite['uses_count'] ?>/∞</span>
                            <?php else: ?>
                              <?php $exhausted = ((int)$invite['uses_count'] >= (int)$invite['max_uses']); ?>
                              <span class="badge text-bg-<?= $exhausted ? 'warning' : 'primary' ?>">
                                <?= (int)$invite['uses_count'] ?>/<?= (int)$invite['max_uses'] ?>
                              </span>
                            <?php endif; ?>
                            <?php if (!empty($invite['used_by_name'])): ?>
                              <div class="small text-success">Used by: <?= h($invite['used_by_name']) ?></div>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php
                              $status = $invite['effective_status'] ?? 'inactive';
                              $label  = ucfirst($status);
                              $map = [
                                'available' => 'success',
                                'expired'   => 'danger',
                                'exhausted' => 'warning',
                                'inactive'  => 'secondary'
                              ];
                              $cls = $map[$status] ?? 'secondary';
                            ?>
                            <span class="badge text-bg-<?= $cls ?>"><?= h($label) ?></span>
                          </td>
                          <td class="small">
                            <?php if (!empty($invite['expires_at'])): ?>
                              <?= date('M j, Y', strtotime($invite['expires_at'])) ?><br>
                              <span class="text-body-secondary"><?= date('H:i', strtotime($invite['expires_at'])) ?></span>
                            <?php else: ?>
                              <span class="text-body-secondary">Never</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <div class="btn-group btn-group-sm">
                              <?php if ((int)$invite['is_active'] === 1): ?>
                                <form method="post">
                                  <input type="hidden" name="<?= h($csrfKey) ?>" value="<?= h($CSRF) ?>">
                                  <input type="hidden" name="action" value="toggle_status">
                                  <input type="hidden" name="invite_id" value="<?= (int)$invite['id'] ?>">
                                  <input type="hidden" name="new_status" value="0">
                                  <button class="btn btn-outline-warning" title="Disable">
                                    <i class="fa-solid fa-pause"></i>
                                  </button>
                                </form>
                              <?php else: ?>
                                <form method="post">
                                  <input type="hidden" name="<?= h($csrfKey) ?>" value="<?= h($CSRF) ?>">
                                  <input type="hidden" name="action" value="toggle_status">
                                  <input type="hidden" name="invite_id" value="<?= (int)$invite['id'] ?>">
                                  <input type="hidden" name="new_status" value="1">
                                  <button class="btn btn-outline-success" title="Enable">
                                    <i class="fa-solid fa-play"></i>
                                  </button>
                                </form>
                              <?php endif; ?>

                              <form method="post" onsubmit="return confirm('Delete this invite?')">
                                <input type="hidden" name="<?= h($csrfKey) ?>" value="<?= h($CSRF) ?>">
                                <input type="hidden" name="action" value="delete_invite">
                                <input type="hidden" name="invite_id" value="<?= (int)$invite['id'] ?>">
                                <button class="btn btn-outline-danger" title="Delete">
                                  <i class="fa-solid fa-trash"></i>
                                </button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Usage -->
      <?php if (!empty($recent_usage)): ?>
      <section class="mt-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary fw-semibold">
            <i class="fa-solid fa-clock-rotate-left me-2"></i>Recent Invite Usage
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Used By</th>
                    <th>Creator</th>
                    <th>Used At</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_usage as $usage): ?>
                  <tr>
                    <td><code><?= h($usage['code']) ?></code></td>
                    <td><span class="badge text-bg-secondary"><?= h(ucfirst($usage['invite_type'])) ?></span></td>
                    <td class="small">
                      <?= h($usage['used_by_name']) ?><br>
                      <span class="text-body-secondary"><?= h($usage['used_by_email']) ?></span>
                    </td>
                    <td class="small"><?= h($usage['creator_name'] ?? 'System') ?></td>
                    <td class="small"><?= $usage['used_at'] ? date('M j, Y H:i', strtotime($usage['used_at'])) : 'N/A' ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
      <?php endif; ?>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Optional mobile sidebar toggle (add a button in header if desired)
function toggleSidebar(){ document.querySelector('.admin-sidebar')?.classList.toggle('show'); }

// Copy helper with brief visual feedback
function copyInviteCode(text, el){
  if (!text) return;
  const original = el.innerHTML;
  navigator.clipboard.writeText(text).then(() => {
    el.innerHTML = '<i class="fa-solid fa-check me-1 text-success"></i>Copied!';
    setTimeout(() => { el.innerHTML = original; }, 1500);
  }).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = text; document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    el.innerHTML = '<i class="fa-solid fa-check me-1 text-success"></i>Copied!';
    setTimeout(() => { el.innerHTML = original; }, 1500);
  });
}

// Auto-hide flash alerts
setTimeout(() => {
  document.querySelectorAll('.alert-dismissible').forEach(a => {
    try { new bootstrap.Alert(a).close(); } catch(e){}
  });
}, 5000);
</script>
</body>
</html>