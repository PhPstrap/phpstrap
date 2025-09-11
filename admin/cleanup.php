<?php
/**
 * PhPstrap Admin — System Cleanup (unified layout)
 * - Sidebar include: /admin/includes/admin-sidebar.php
 * - Styles: Bootstrap 5.3 + Font Awesome + /assets/css/admin.css
 * - Tools: delete password reset tokens, trim admin logs, safe user deletes, invite maintenance
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
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

/* Auth include (support multiple paths) */
foreach (['admin-auth.php','includes/admin-auth.php','../includes/admin-auth.php'] as $p) {
    if (file_exists($p)) { safeInclude($p, false); break; }
}

/* ------------------------------ App/Auth ---------------------------------- */
if (function_exists('initializeApp')) {
    try { initializeApp(); } catch (Throwable $e) { if (session_status()===PHP_SESSION_NONE) @session_start(); }
} else {
    if (session_status()===PHP_SESSION_NONE) @session_start();
}

if (function_exists('requireAdminAuth')) { requireAdminAuth(); }
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'cleanup.php'));
    exit;
}

$admin = [
    'id'    => $_SESSION['admin_id']    ?? 1,
    'name'  => $_SESSION['admin_name']  ?? 'Administrator',
    'email' => $_SESSION['admin_email'] ?? '',
];

/* ---------------------------- DB connection ------------------------------- */
$db_available = false;
$pdo = null;
try { $pdo = getDbConnection(); $db_available = $pdo instanceof PDO; } catch (Throwable $e) { $db_available = false; }

function tableExists(PDO $pdo, string $table): bool {
    try {
        $q = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        return $q && $q->rowCount() > 0;
    } catch (Throwable $e) { return false; }
}

/* ------------------------------- Settings --------------------------------- */
$system_info = ['app_version' => '1.0.0', 'site_name' => 'PhPstrap Admin'];
try {
    if ($pdo) {
        $st = $pdo->query("SELECT `key`,`value` FROM settings WHERE `key` IN ('app_version','site_name')");
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            if ($row['key'] === 'app_version') $system_info['app_version'] = $row['value'] ?: $system_info['app_version'];
            if ($row['key'] === 'site_name')   $system_info['site_name']   = $row['value'] ?: $system_info['site_name'];
        }
    }
} catch (Throwable $e) {}

/* ----------------------------- Utilities ---------------------------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (!isset($_SESSION['PhPstrap_csrf_token'])) { $_SESSION['PhPstrap_csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['PhPstrap_csrf_token'];

function parseDate($str) {
    if (!$str) return null;
    // Accept both "YYYY-MM-DD HH:MM" and "YYYY-MM-DDTHH:MM"
    $str = str_replace('T',' ',$str);
    $ts = strtotime($str);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

$alerts = [];
function addAlert($type, $msg) { global $alerts; $alerts[] = ['type'=>$type,'msg'=>$msg]; }

/* ---------------------------- POST Handlers ------------------------------- */
if ($pdo && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';
    $dryRun = !empty($_POST['dry_run']);

    if (!$csrf || !hash_equals($csrfToken, $csrf)) {
        addAlert('danger', 'Invalid security token. Please refresh and try again.');
    } else try {

        /* ---- Delete password reset tokens before date -------------------- */
        if ($action === 'delete_password_resets_before') {
            if (!tableExists($pdo, 'password_resets')) { addAlert('warning','Table password_resets not found.'); }
            else {
                $beforeStr  = trim($_POST['before'] ?? '');
                $onlyUnused = !empty($_POST['only_unused']);

                if ($beforeStr === '') {
                    addAlert('warning', 'Provide a "Before" date/time.');
                } else {
                    $before = parseDate($beforeStr);
                    if (!$before) { addAlert('danger','Invalid date.'); }
                    else {
                        $where = ["created_at < ?"];
                        $bind  = [$before];
                        if ($onlyUnused) { $where[] = "COALESCE(used,0) = 0"; }
                        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

                        $q = $pdo->prepare("SELECT COUNT(*) FROM password_resets $sqlWhere");
                        $q->execute($bind);
                        $count = (int)$q->fetchColumn();

                        if ($dryRun) addAlert('info', "Dry run: would delete $count password reset record(s).");
                        else {
                            $del = $pdo->prepare("DELETE FROM password_resets $sqlWhere");
                            $del->execute($bind);
                            addAlert('success', "Deleted $count password reset record(s).");
                        }
                    }
                }
            }
        }

        /* ---- Delete admin activity logs before date ---------------------- */
        if ($action === 'delete_admin_logs_before') {
            if (!tableExists($pdo, 'admin_activity_log')) { addAlert('warning','Table admin_activity_log not found.'); }
            else {
                $beforeStr = trim($_POST['before'] ?? '');
                if ($beforeStr === '') {
                    addAlert('warning', 'Provide a "Before" date/time.');
                } else {
                    $before = parseDate($beforeStr);
                    if (!$before) { addAlert('danger','Invalid date.'); }
                    else {
                        $where = "WHERE created_at < ?";
                        $bind  = [$before];

                        $q = $pdo->prepare("SELECT COUNT(*) FROM admin_activity_log $where");
                        $q->execute($bind);
                        $count = (int)$q->fetchColumn();

                        if ($dryRun) addAlert('info', "Dry run: would delete $count admin activity record(s).");
                        else {
                            $del = $pdo->prepare("DELETE FROM admin_activity_log $where");
                            $del->execute($bind);
                            addAlert('success', "Deleted $count admin activity record(s).");
                        }
                    }
                }
            }
        }

        /* ---- Delete users before date (with safeguards) ------------------ */
        if ($action === 'delete_users_before') {
            if (!tableExists($pdo, 'users')) { addAlert('warning','Table users not found.'); }
            else {
                $beforeStr       = trim($_POST['before'] ?? '');
                $excludeAdmins   = !empty($_POST['exclude_admins']); // default on
                $neverLoggedOnly = !empty($_POST['never_logged_in_only']);
                $confirmText     = trim($_POST['confirm_text'] ?? '');

                if ($beforeStr === '') {
                    addAlert('warning', 'Provide a "Created Before" date/time.');
                } elseif (strtoupper($confirmText) !== 'DELETE') {
                    addAlert('warning', 'Type DELETE in the confirmation box.');
                } else {
                    $before = parseDate($beforeStr);
                    if (!$before) { addAlert('danger','Invalid date.'); }
                    else {
                        $where = ["created_at < ?"];
                        $bind  = [$before];
                        if ($excludeAdmins) { $where[] = "COALESCE(is_admin,0) = 0"; }
                        if ($neverLoggedOnly){ $where[] = "last_login_at IS NULL"; }

                        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

                        $q = $pdo->prepare("SELECT COUNT(*) FROM users $sqlWhere");
                        $q->execute($bind);
                        $count = (int)$q->fetchColumn();

                        if ($dryRun) addAlert('info', "Dry run: would delete $count user account(s).");
                        else {
                            $del = $pdo->prepare("DELETE FROM users $sqlWhere");
                            $del->execute($bind);
                            addAlert('success', "Deleted $count user account(s).");
                        }
                    }
                }
            }
        }

        /* ---- Deactivate expired invites ---------------------------------- */
        if ($action === 'deactivate_expired_invites') {
            if (!tableExists($pdo, 'invites')) { addAlert('warning','Table invites not found.'); }
            else {
                $beforeStr = trim($_POST['before'] ?? '');
                $where = ["is_active = 1", "expires_at IS NOT NULL"];
                $bind  = [];
                if ($beforeStr !== '') {
                    $before = parseDate($beforeStr);
                    if ($before) { $where[] = "expires_at < ?"; $bind[] = $before; }
                } else { $where[] = "expires_at < NOW()"; }
                $sqlWhere = 'WHERE ' . implode(' AND ', $where);

                $q = $pdo->prepare("SELECT COUNT(*) FROM invites $sqlWhere");
                $q->execute($bind);
                $count = (int)$q->fetchColumn();

                if ($dryRun) addAlert('info', "Dry run: would deactivate $count expired invite(s).");
                else {
                    $upd = $pdo->prepare("UPDATE invites SET is_active = 0 $sqlWhere");
                    $upd->execute($bind);
                    addAlert('success', "Deactivated $count expired invite(s).");
                }
            }
        }

        /* ---- Purge used invites (keep unused active) --------------------- */
        if ($action === 'purge_used_invites') {
            if (!tableExists($pdo, 'invites')) { addAlert('warning','Table invites not found.'); }
            else {
                $inviterId  = trim($_POST['inviter_id'] ?? '');
                $beforeStr  = trim($_POST['before'] ?? '');
                $inviteType = trim($_POST['invite_type'] ?? '');

                if ($inviterId === '' && $beforeStr === '') {
                    addAlert('warning', 'Provide Inviter or Before date (at least one).');
                } else {
                    // Used = has used_by OR (max_uses set AND uses_count >= max_uses)
                    $where = ["(used_by IS NOT NULL OR (max_uses IS NOT NULL AND uses_count >= max_uses))"];
                    $bind  = [];

                    if ($inviterId !== '' && ctype_digit($inviterId)) { $where[] = "generated_by = ?"; $bind[] = (int)$inviterId; }
                    if ($beforeStr !== '') {
                        $before = parseDate($beforeStr);
                        if ($before) { $where[] = "COALESCE(used_at, created_at) < ?"; $bind[] = $before; }
                    }
                    if ($inviteType !== '') { $where[] = "invite_type = ?"; $bind[] = $inviteType; }

                    $sqlWhere = 'WHERE ' . implode(' AND ', $where);

                    $q = $pdo->prepare("SELECT COUNT(*) FROM invites $sqlWhere");
                    $q->execute($bind);
                    $count = (int)$q->fetchColumn();

                    if ($dryRun) addAlert('info', "Dry run: would delete $count used invite(s).");
                    else {
                        $del = $pdo->prepare("DELETE FROM invites $sqlWhere");
                        $del->execute($bind);
                        addAlert('success', "Deleted $count used invite(s). Unused invites remain active.");
                    }
                }
            }
        }

    } catch (Throwable $e) {
        addAlert('danger', 'Cleanup error: ' . h($e->getMessage()));
    }
}

/* -------------------------------- View ------------------------------------ */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>System Cleanup - <?= h($system_info['site_name']) ?></title>

<link href="/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="../assets/css/admin.css?v=<?= urlencode($system_info['app_version'] ?? '1.0.0') ?>" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="d-flex">
  <aside class="admin-sidebar bg-dark text-white">
    <div class="p-3 border-bottom border-secondary-subtle">
      <div class="d-flex align-items-center">
        <i class="fa-solid fa-shield-halved me-2"></i>
        <strong><?= h($system_info['site_name'] ?? 'PhPstrap Admin') ?></strong>
      </div>
      <div class="small text-secondary mt-1">v<?= h($system_info['app_version'] ?? '1.0.0') ?></div>
    </div>
    <?php
      $activeKey     = 'logs';            // <-- set per page: 'logs', 'users', 'cleanup', 'settings', etc.
      $sidebarBadges = [];
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
            <h1 class="h4 mb-0">System Cleanup</h1>
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

      <?php foreach ($alerts as $a): ?>
        <div class="alert alert-<?= h($a['type']) ?> alert-dismissible fade show" role="alert">
          <?= $a['msg'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>

      <?php if (!$db_available): ?>
        <div class="alert alert-warning">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          Database connection unavailable; cleanup tools are disabled.
        </div>
      <?php endif; ?>

      <!-- Quick Cleanup -->
      <section class="mb-4">
        <div class="d-flex align-items-center mb-2">
          <h2 class="h5 mb-0">Quick Cleanup</h2>
          <span class="ms-2 badge text-bg-primary">Recommended</span>
        </div>
        <div class="row g-3">
          <!-- Password resets -->
          <div class="col-12 col-xl-6">
            <div class="card shadow-sm">
              <div class="card-header bg-body-tertiary d-flex align-items-center">
                <i class="fa-solid fa-key me-2 text-primary"></i>
                <strong>Delete Password Reset Tokens</strong>
              </div>
              <div class="card-body">
                <form method="post" class="row g-3" autocomplete="off">
                  <input type="hidden" name="action" value="delete_password_resets_before">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                  <div class="col-md-6">
                    <label class="form-label">Before <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" name="before" required>
                    <div class="form-text">Delete tokens older than this date/time.</div>
                  </div>

                  <div class="col-md-6 d-flex align-items-end justify-content-between flex-wrap gap-2">
                    <div class="form-check me-3">
                      <input class="form-check-input" type="checkbox" name="only_unused" id="onlyUnused" checked>
                      <label class="form-check-label" for="onlyUnused">Only unused</label>
                    </div>
                    <div class="form-check me-3">
                      <input class="form-check-input" type="checkbox" name="dry_run" id="prDry" checked>
                      <label class="form-check-label" for="prDry">Dry run</label>
                    </div>
                    <button class="btn btn-danger" <?= $db_available ? '' : 'disabled' ?>>
                      <i class="fa-solid fa-trash me-1"></i> Delete
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Admin logs -->
          <div class="col-12 col-xl-6">
            <div class="card shadow-sm">
              <div class="card-header bg-body-tertiary d-flex align-items-center">
                <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>
                <strong>Delete Admin Activity Logs</strong>
              </div>
              <div class="card-body">
                <form method="post" class="row g-3" autocomplete="off">
                  <input type="hidden" name="action" value="delete_admin_logs_before">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                  <div class="col-md-6">
                    <label class="form-label">Before <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" name="before" required>
                    <div class="form-text">Delete admin log records older than this date/time.</div>
                  </div>

                  <div class="col-md-6 d-flex align-items-end justify-content-between flex-wrap gap-2">
                    <div class="form-check me-3">
                      <input class="form-check-input" type="checkbox" name="dry_run" id="logDry" checked>
                      <label class="form-check-label" for="logDry">Dry run</label>
                    </div>
                    <button class="btn btn-danger" <?= $db_available ? '' : 'disabled' ?>>
                      <i class="fa-solid fa-eraser me-1"></i> Delete Logs
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

        </div>
      </section>

      <!-- Users (Advanced) -->
      <section class="mb-4">
        <h2 class="h5 mb-2">Users (Advanced)</h2>
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center">
            <i class="fa-solid fa-user-slash me-2 text-primary"></i>
            <strong>Delete Users (Safe)</strong>
          </div>
          <div class="card-body">
            <form method="post" class="row g-3" autocomplete="off">
              <input type="hidden" name="action" value="delete_users_before">
              <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

              <div class="col-md-4">
                <label class="form-label">Created Before <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control" name="before" required>
                <div class="form-text">Highly destructive — proceed with caution.</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Confirmation <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="confirm_text" placeholder="Type DELETE to confirm" required>
              </div>

              <div class="col-md-4 d-flex align-items-end justify-content-between flex-wrap gap-2">
                <div class="form-check me-3">
                  <input class="form-check-input" type="checkbox" name="exclude_admins" id="excludeAdmins" checked>
                  <label class="form-check-label" for="excludeAdmins">Exclude admins</label>
                </div>
                <div class="form-check me-3">
                  <input class="form-check-input" type="checkbox" name="never_logged_in_only" id="neverLogged">
                  <label class="form-check-label" for="neverLogged">Never logged in only</label>
                </div>
                <div class="form-check me-3">
                  <input class="form-check-input" type="checkbox" name="dry_run" id="userDry" checked>
                  <label class="form-check-label" for="userDry">Dry run</label>
                </div>
                <button class="btn btn-danger" <?= $db_available ? '' : 'disabled' ?>>
                  <i class="fa-solid fa-user-xmark me-1"></i> Delete Users
                </button>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- Invites -->
      <section class="mb-4">
        <div class="d-flex align-items-center mb-2">
          <h2 class="h5 mb-0">Invites</h2>
          <span class="ms-2 badge text-bg-secondary">Occasional</span>
        </div>
        <div class="row g-3">

          <!-- Deactivate expired invites -->
          <div class="col-12 col-xl-6">
            <div class="card shadow-sm">
              <div class="card-header bg-body-tertiary d-flex align-items-center">
                <i class="fa-regular fa-clock me-2 text-primary"></i>
                <strong>Deactivate Expired Invites</strong>
              </div>
              <div class="card-body">
                <form method="post" class="row g-3" autocomplete="off">
                  <input type="hidden" name="action" value="deactivate_expired_invites">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                  <div class="col-md-6">
                    <label class="form-label">Before (optional)</label>
                    <input type="datetime-local" class="form-control" name="before">
                    <div class="form-text">If blank, uses “now”. Sets <code>is_active=0</code> where <code>expires_at</code> has passed.</div>
                  </div>

                  <div class="col-md-6 d-flex align-items-end justify-content-between flex-wrap gap-2">
                    <div class="form-check me-3">
                      <input class="form-check-input" type="checkbox" name="dry_run" id="deactDry" checked>
                      <label class="form-check-label" for="deactDry">Dry run</label>
                    </div>
                    <button class="btn btn-warning" <?= $db_available ? '' : 'disabled' ?>>
                      <i class="fa-solid fa-ban me-1"></i> Deactivate
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Purge used invites -->
          <div class="col-12 col-xl-6">
            <div class="card shadow-sm">
              <div class="card-header bg-body-tertiary d-flex align-items-center">
                <i class="fa-solid fa-broom me-2 text-primary"></i>
                <strong>Purge Used Invites (keep unused active)</strong>
              </div>
              <div class="card-body">
                <form method="post" class="row g-3" id="form-purge-invites" autocomplete="off">
                  <input type="hidden" name="action" value="purge_used_invites">
                  <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                  <div class="col-md-4">
                    <label class="form-label">Inviter (User ID)</label>
                    <input type="number" min="1" class="form-control" name="inviter_id" id="inviter_id">
                    <div class="form-text">Provide inviter or a date — at least one required.</div>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Before</label>
                    <input type="datetime-local" class="form-control" name="before" id="invites_before">
                    <div class="form-text">Uses <code>used_at</code>, falls back to <code>created_at</code>.</div>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Invite Type</label>
                    <select class="form-select" name="invite_type">
                      <option value="">Any</option>
                      <option value="registration">Registration</option>
                      <option value="premium">Premium</option>
                      <option value="admin">Admin</option>
                    </select>
                  </div>

                  <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="form-check me-3">
                      <input class="form-check-input" type="checkbox" name="dry_run" id="purgeInvDry" checked>
                      <label class="form-check-label" for="purgeInvDry">Dry run</label>
                    </div>
                    <button class="btn btn-danger" id="btn-purge-invites" <?= $db_available ? '' : 'disabled' ?>>
                      <i class="fa-solid fa-trash-can me-1"></i> Purge Used Invites
                    </button>
                  </div>

                  <div class="col-12">
                    <small class="text-body-secondary">
                      Deletes only invites already used, or maxed out (where <code>max_uses</code> is set and reached).
                      Unused invites remain active.
                    </small>
                  </div>
                </form>
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
// Safety: require at least one filter for Purge Used Invites
document.addEventListener('DOMContentLoaded', function(){
  const inviter = document.getElementById('inviter_id');
  const before  = document.getElementById('invites_before');
  const purgeBtn= document.getElementById('btn-purge-invites');

  function updatePurgeBtn(){
    const hasInviter = inviter && inviter.value.trim() !== '';
    const hasBefore  = before && before.value.trim() !== '';
    if (purgeBtn) purgeBtn.disabled = !(hasInviter || hasBefore);
  }
  if (inviter && before) {
    inviter.addEventListener('input', updatePurgeBtn);
    before.addEventListener('input', updatePurgeBtn);
    updatePurgeBtn();
  }
});
</script>
</body>
</html>