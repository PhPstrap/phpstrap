<?php
/**
 * PhPstrap Admin — Users (matches Logs layout & shared admin.css)
 * - External sidebar include: /admin/includes/admin-sidebar.php
 * - Bootstrap 5.3 + Font Awesome + /assets/css/admin.css
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

/* ------------------------------- Includes --------------------------------- */
require_once '../config/database.php';
require_once '../config/app.php';
// Optional helpers (getSetting, getUserIP, logActivity, etc.)
$helpers_paths = ['../config/functions.php', 'config/functions.php'];
foreach ($helpers_paths as $hp) { if (file_exists($hp)) { require_once $hp; break; } }

// Auth include (support multiple paths)
$auth_paths = ['admin-auth.php', 'includes/admin-auth.php', '../includes/admin-auth.php'];
foreach ($auth_paths as $path) { if (file_exists($path)) { require_once $path; break; } }

/* ------------------------------ App/Auth ---------------------------------- */
initializeApp();
if (function_exists('requireAdminAuth')) { requireAdminAuth(); }
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
  header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'users.php'));
  exit;
}

$admin = [
  'id'    => $_SESSION['admin_id']    ?? 1,
  'name'  => $_SESSION['admin_name']  ?? 'Administrator',
  'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
];

if (!isset($_SESSION['PhPstrap_csrf_token'])) {
  $_SESSION['PhPstrap_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['PhPstrap_csrf_token'];

if (function_exists('logAdminActivity')) { try { logAdminActivity('users_access', ['page' => 'users']); } catch (Throwable $e) {} }

/* -------------------------------- Utils ----------------------------------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

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

/* ---------------------------- Data functions ------------------------------ */
function getUsers(PDO $pdo, string $search = '', int $limit = 20, int $offset = 0): array {
  $where  = '';
  $params = [];
  if ($search !== '') {
    $where = "WHERE name LIKE ? OR email LIKE ?";
    $params = ["%$search%","%$search%"];
  }
  $sql = "SELECT id, name, email, is_admin, is_active, membership_status,
                 last_login_at, created_at, login_attempts, credits, api_token,
                 verified, stripe_customer_id, affiliate_id
          FROM users
          $where
          ORDER BY created_at DESC
          LIMIT ? OFFSET ?";
  array_push($params, (int)$limit, (int)$offset);
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getUsersCount(PDO $pdo, string $search = ''): int {
  $where  = '';
  $params = [];
  if ($search !== '') {
    $where = "WHERE name LIKE ? OR email LIKE ?";
    $params = ["%$search%","%$search%"];
  }
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
  $stmt->execute($params);
  return (int)$stmt->fetchColumn();
}

function getUserStats(PDO $pdo): array {
  try {
    $total  = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $active = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
    $today  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE last_login_at >= CURDATE()")->fetchColumn();
    return compact('total','active','admins','today');
  } catch (Throwable $e) {
    return ['total'=>0,'active'=>0,'admins'=>0,'today'=>0];
  }
}

function createUser(PDO $pdo, array $data): array {
  try {
    $name     = trim($data['name'] ?? '');
    $email    = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $is_admin = !empty($data['is_admin']) ? 1 : 0;
    $is_active= !empty($data['is_active']) ? 1 : 0;

    if ($name==='' || $email==='' || $password==='') return ['success'=>false,'message'=>'Name, email, and password are required.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))   return ['success'=>false,'message'=>'Please enter a valid email address.'];
    if (strlen($password) < 6)                        return ['success'=>false,'message'=>'Password must be at least 6 characters long.'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) return ['success'=>false,'message'=>'Email address already exists.'];

    $affiliate_id = strtoupper(substr(md5($email . microtime(true)), 0, 8));

    $stmt = $pdo->prepare("INSERT INTO users (name,email,password,is_admin,is_active,affiliate_id,verified,created_at)
                           VALUES (?,?,?,?,?,?,1,NOW())");
    $ok = $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$is_admin,$is_active,$affiliate_id]);

    return $ok ? ['success'=>true,'message'=>'User created successfully.']
               : ['success'=>false,'message'=>'Failed to create user.'];
  } catch (Throwable $e) {
    error_log('createUser error: '.$e->getMessage());
    return ['success'=>false,'message'=>'Database error occurred.'];
  }
}

function updateUser(PDO $pdo, array $data): array {
  try {
    $user_id  = (int)($data['user_id'] ?? 0);
    $name     = trim($data['name'] ?? '');
    $email    = trim($data['email'] ?? '');
    $is_admin = !empty($data['is_admin']) ? 1 : 0;
    $is_active= !empty($data['is_active']) ? 1 : 0;

    if ($user_id<=0 || $name==='' || $email==='') return ['success'=>false,'message'=>'Invalid user data provided.'];
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) return ['success'=>false,'message'=>'Please enter a valid email address.'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email,$user_id]);
    if ($stmt->fetchColumn()) return ['success'=>false,'message'=>'Email address already exists.'];

    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, is_admin=?, is_active=?, updated_at=NOW() WHERE id=?");
    $ok = $stmt->execute([$name,$email,$is_admin,$is_active,$user_id]);

    if (!empty($data['password'])) {
      if (strlen($data['password']) < 6) return ['success'=>false,'message'=>'Password must be at least 6 characters long.'];
      $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
      $stmt->execute([password_hash($data['password'],PASSWORD_DEFAULT),$user_id]);
    }

    return $ok ? ['success'=>true,'message'=>'User updated successfully.']
               : ['success'=>false,'message'=>'Failed to update user.'];
  } catch (Throwable $e) {
    error_log('updateUser error: '.$e->getMessage());
    return ['success'=>false,'message'=>'Database error occurred.'];
  }
}

function deleteUser(PDO $pdo, $user_id): array {
  try {
    $user_id = (int)$user_id;
    if ($user_id<=0) return ['success'=>false,'message'=>'Invalid user ID.'];
    if ($user_id == ($_SESSION['admin_id'] ?? 0)) return ['success'=>false,'message'=>'Cannot delete your own account.'];

    $stmt = $pdo->prepare("SELECT name FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) return ['success'=>false,'message'=>'User not found.'];

    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
    $ok = $stmt->execute([$user_id]);
    return $ok ? ['success'=>true,'message'=>'User deleted successfully.']
               : ['success'=>false,'message'=>'Failed to delete user.'];
  } catch (Throwable $e) {
    error_log('deleteUser error: '.$e->getMessage());
    return ['success'=>false,'message'=>'Database error occurred.'];
  }
}

function toggleUserStatus(PDO $pdo, $user_id): array {
  try {
    $user_id = (int)$user_id;
    if ($user_id<=0) return ['success'=>false,'message'=>'Invalid user ID.'];
    $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $cur = $stmt->fetchColumn();
    if ($cur===false) return ['success'=>false,'message'=>'User not found.'];
    $new = $cur?0:1;
    $stmt = $pdo->prepare("UPDATE users SET is_active=? WHERE id=?");
    $ok = $stmt->execute([$new,$user_id]);
    return $ok ? ['success'=>true,'message'=> $new ? 'User activated successfully.' : 'User deactivated successfully.']
               : ['success'=>false,'message'=>'Failed to update user status.'];
  } catch (Throwable $e) {
    error_log('toggleUserStatus error: '.$e->getMessage());
    return ['success'=>false,'message'=>'Database error occurred.'];
  }
}

function loginAsUser($user_id): array {
  try {
    $user_id = (int)$user_id;
    if ($user_id<=0) return ['success'=>false,'message'=>'Invalid user ID.'];
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id,name,email,is_active,membership_status,credits,api_token,verified,is_admin,affiliate_id
                           FROM users WHERE id=? AND is_active=1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return ['success'=>false,'message'=>'User not found or inactive.'];

    // preserve admin session
    $_SESSION['impersonating_admin'] = [
      'admin_id'        => $_SESSION['admin_id'] ?? null,
      'admin_name'      => $_SESSION['admin_name'] ?? null,
      'admin_email'     => $_SESSION['admin_email'] ?? null,
      'admin_login_time'=> $_SESSION['admin_login_time'] ?? null,
      'admin_logged_in' => $_SESSION['admin_logged_in'] ?? null
    ];
    // clear admin flags
    unset($_SESSION['admin_id'],$_SESSION['admin_name'],$_SESSION['admin_email'],$_SESSION['admin_login_time'],$_SESSION['admin_logged_in']);

    // user session
    $_SESSION['loggedin']          = true;
    $_SESSION['user_id']           = $user['id'];
    $_SESSION['id']                = $user['id'];
    $_SESSION['name']              = $user['name'];
    $_SESSION['email']             = $user['email'];
    $_SESSION['membership_status'] = $user['membership_status'];
    $_SESSION['credits']           = $user['credits'];
    $_SESSION['api_token']         = $user['api_token'];
    $_SESSION['is_admin']          = $user['is_admin'];
    $_SESSION['impersonating']     = true;

    // update last login + log activity if helpers exist
    $user_ip    = function_exists('getUserIP') ? getUserIP() : ($_SERVER['REMOTE_ADDR'] ?? '');
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $up = $pdo->prepare("UPDATE users SET last_login_at=NOW(), last_login_ip=? WHERE id=?");
    $up->execute([$user_ip,$user['id']]);
    if (function_exists('logActivity')) {
      try { logActivity($user['email'],$user_ip,$user_agent,true,'admin_impersonation'); } catch (Throwable $e) {}
    }
    if (function_exists('logAdminActivity')) {
      try { logAdminActivity('login_as_user', ['target_user_id'=>$user_id,'target_user_email'=>$user['email']]); } catch (Throwable $e) {}
    }

    return ['success'=>true,'message'=>'Switched to user session.'];
  } catch (Throwable $e) {
    error_log('loginAsUser error: '.$e->getMessage());
    return ['success'=>false,'message'=>'Database error occurred.'];
  }
}

/* ------------------------------ Handle POST ------------------------------- */
$message = '';
$error   = false;

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  if (!isset($_POST['csrf_token']) || !hash_equals($csrf, $_POST['csrf_token'])) {
    $message = 'Invalid security token. Please try again.'; $error = true;
  } else {
    try {
      switch ($_POST['action']) {
        case 'create_user':
          $res = createUser($pdo, $_POST); $message=$res['message']; $error=!$res['success']; break;
        case 'update_user':
          $res = updateUser($pdo, $_POST); $message=$res['message']; $error=!$res['success']; break;
        case 'delete_user':
          $res = deleteUser($pdo, $_POST['user_id'] ?? 0); $message=$res['message']; $error=!$res['success']; break;
        case 'toggle_status':
          $res = toggleUserStatus($pdo, $_POST['user_id'] ?? 0); $message=$res['message']; $error=!$res['success']; break;
        default:
          $message = 'Invalid action specified.'; $error = true;
      }
      if (function_exists('logAdminActivity') && !empty($_POST['action']) && !$error) {
        try { logAdminActivity($_POST['action'], ['user_id'=>$_POST['user_id'] ?? null]); } catch (Throwable $e) {}
      }
    } catch (Throwable $e) {
      $message = 'An error occurred. Please try again.'; $error = true;
      error_log('Users POST error: '.$e->getMessage());
    }
  }
}

/* ------------------------------ Handle GET -------------------------------- */
if ($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['action'] ?? '') === 'login_as_user') {
  $res = loginAsUser($_GET['user_id'] ?? 0);
  if ($res['success']) {
    session_write_close(); session_start();
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    header('Location: '.$protocol.'://'.$host.'/dashboard/');
    exit;
  } else { $message=$res['message']; $error=true; }
}

/* ------------------------------ Params/Fetch ------------------------------ */
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page-1)*$limit;

$stats      = $db_available ? getUserStats($pdo) : ['total'=>0,'active'=>0,'admins'=>0,'today'=>0];
$total_rows = $db_available ? getUsersCount($pdo, $q) : 0;
$total_pages= max(1, (int)ceil(($total_rows ?: 1)/$limit));
$users      = $db_available ? getUsers($pdo, $q, $limit, $offset) : [];

/* --------------------------------- View ----------------------------------- */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Users - <?= h($settings['site_name'] ?? 'PhPstrap Admin') ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet">
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
  $activeKey     = 'users';
  $sidebarBadges = []; // e.g. ['users' => 3]
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
            <h1 class="h4 mb-0">Users</h1>
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
        <div class="alert alert-<?= $error?'danger':'success' ?> my-3">
          <i class="fa-solid fa-<?= $error ? 'triangle-exclamation' : 'circle-check' ?> me-2"></i>
          <?= h($message) ?>
        </div>
      <?php endif; ?>

      <?php if (!$db_available): ?>
        <div class="alert alert-warning my-3">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
          Database connection unavailable; users cannot be displayed.
        </div>
      <?php endif; ?>

      <!-- Stats -->
      <section class="mb-3">
        <div class="row g-3">
          <div class="col-sm-6 col-lg-3">
            <div class="card stat-card shadow-sm">
              <div class="card-body text-center">
                <div class="display-6 fw-semibold"><?= (int)$stats['total'] ?></div>
                <div class="text-body-secondary text-uppercase small">Total Users</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="card stat-card shadow-sm">
              <div class="card-body text-center">
                <div class="display-6 fw-semibold"><?= (int)$stats['active'] ?></div>
                <div class="text-body-secondary text-uppercase small">Active</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="card stat-card shadow-sm">
              <div class="card-body text-center">
                <div class="display-6 fw-semibold"><?= (int)$stats['admins'] ?></div>
                <div class="text-body-secondary text-uppercase small">Administrators</div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="card stat-card shadow-sm">
              <div class="card-body text-center">
                <div class="display-6 fw-semibold"><?= (int)$stats['today'] ?></div>
                <div class="text-body-secondary text-uppercase small">Active Today</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Filters + Actions -->
      <section class="mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa-solid fa-filter me-2"></i>Search & Actions</span>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
              <i class="fa-solid fa-user-plus me-1"></i>Add User
            </button>
          </div>
          <div class="card-body">
            <form method="get" class="row g-3">
              <div class="col-md-8">
                <label class="form-label">Search</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                  <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="Name or email…">
                </div>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-outline-primary w-100"><i class="fa-solid fa-search me-1"></i>Apply</button>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <a class="btn btn-outline-secondary w-100" href="?"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- Users Table -->
      <section class="mb-4">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa-solid fa-users me-2"></i>All Users</span>
            <span class="badge text-bg-secondary"><?= number_format((int)$total_rows) ?> total</span>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>User</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th class="text-nowrap">Last Login</th>
                  <th class="text-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($users)): ?>
                <tr><td colspan="6" class="text-center text-body-secondary py-4">
                  <i class="fa-regular fa-circle-question me-1"></i>No users found.
                </td></tr>
              <?php else: foreach ($users as $u): ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="avatar-circle bg-primary text-white fw-semibold me-2">
                        <?= h(mb_strtoupper(mb_substr($u['name'] ?: 'U', 0, 1))) ?>
                      </div>
                      <div>
                        <div class="fw-semibold"><?= h($u['name']) ?></div>
                        <div class="small text-body-secondary">ID: <?= (int)$u['id'] ?></div>
                      </div>
                    </div>
                  </td>
                  <td><?= h($u['email']) ?></td>
                  <td>
                    <?php if (!empty($u['is_admin'])): ?>
                      <span class="badge text-bg-danger">Administrator</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">User</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($u['is_active'])): ?>
                      <span class="badge text-bg-success">Active</span>
                    <?php else: ?>
                      <span class="badge text-bg-warning text-dark">Inactive</span>
                    <?php endif; ?>
                    <?php if ((int)$u['login_attempts'] >= 5): ?>
                      <span class="badge text-bg-danger ms-1">Locked</span>
                    <?php endif; ?>
                  </td>
                  <td class="small text-nowrap">
                    <?php if (!empty($u['last_login_at'])): ?>
                      <?= h(date('M j, Y g:i A', strtotime($u['last_login_at']))) ?>
                    <?php else: ?>
                      <span class="text-body-secondary">Never</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-nowrap">
                    <!-- Login as user -->
                    <?php if (empty($u['is_admin']) && !empty($u['is_active'])): ?>
                      <a href="?action=login_as_user&user_id=<?= (int)$u['id'] ?>"
                         class="btn btn-primary btn-sm"
                         title="Login as user"
                         onclick="return confirm('Login as this user? You will be redirected to their dashboard.');">
                        <i class="fa-solid fa-right-to-bracket"></i>
                      </a>
                    <?php endif; ?>

                    <!-- Edit -->
                    <button class="btn btn-outline-primary btn-sm"
                            data-bs-toggle="modal" data-bs-target="#editUserModal"
                            title="Edit user"
                            onclick='loadUserData(<?= json_encode($u, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>
                      <i class="fa-solid fa-pen"></i>
                    </button>

                    <!-- Toggle -->
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <button class="btn btn-outline-<?= !empty($u['is_active']) ? 'warning' : 'success' ?> btn-sm"
                              title="<?= !empty($u['is_active']) ? 'Deactivate' : 'Activate' ?>">
                        <i class="fa-solid fa-<?= !empty($u['is_active']) ? 'pause' : 'play' ?>"></i>
                      </button>
                    </form>

                    <!-- Delete (not yourself) -->
                    <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                      <form method="post" class="d-inline" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button class="btn btn-outline-danger btn-sm" title="Delete user">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    <?php endif; ?>
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
                    $qsBase = function($p) use ($q) {
                      return '?page='.$p.'&q='.urlencode($q);
                    };
                  ?>
                  <li class="page-item <?= $page<=1?'disabled':'' ?>">
                    <a class="page-link" href="<?= $qsBase(max(1,$page-1)) ?>"><i class="fa-solid fa-chevron-left"></i></a>
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
                Showing page <?= (int)$page ?> of <?= (int)$total_pages ?> (<?= number_format((int)$total_rows) ?> total)
              </div>
            </div>
          <?php endif; ?>

        </div>
      </section>

    </div>
  </main>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="create_user">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" class="form-control" name="name" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-control" name="email" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" class="form-control" name="password" required minlength="6">
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_admin" id="addIsAdmin">
          <label class="form-check-label" for="addIsAdmin">Administrator privileges</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" id="addIsActive" checked>
          <label class="form-check-label" for="addIsActive">Active account</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-user-pen me-2"></i>Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" id="editUserId">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" class="form-control" name="name" id="editName" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-control" name="email" id="editEmail" required>
        </div>
        <div class="mb-3">
          <label class="form-label">New Password <span class="text-body-secondary small">(leave blank to keep current)</span></label>
          <input type="password" class="form-control" name="password" id="editPassword" minlength="6">
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="is_admin" id="editIsAdmin">
          <label class="form-check-label" for="editIsAdmin">Administrator privileges</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
          <label class="form-check-label" for="editIsActive">Active account</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Update User</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fill edit modal
function loadUserData(u) {
  document.getElementById('editUserId').value   = u.id;
  document.getElementById('editName').value     = u.name || '';
  document.getElementById('editEmail').value    = u.email || '';
  document.getElementById('editIsAdmin').checked= !!(+u.is_admin);
  document.getElementById('editIsActive').checked= !!(+u.is_active);
  document.getElementById('editPassword').value = '';
}

// Optional mobile sidebar toggle (wire a button in header if you add one)
function toggleSidebar(){ document.querySelector('.admin-sidebar')?.classList.toggle('show'); }
</script>
</body>
</html>