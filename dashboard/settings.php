<?php
// dashboard/settings.php — Profile (name/email) & Password settings with admin-controlled flags
require_once '../config/app.php';
require_once '../config/functions.php';

session_start();

// Require login
if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
    header('Location: ../login/');
    exit();
}

/* =======================
   Helpers
   ======================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_check($token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}

/**
 * Fetch specific settings by keys from the `settings` table and cast by `type`.
 * @param PDO $pdo
 * @param array $keys
 * @return array
 */
function fetch_settings_by_keys(PDO $pdo, array $keys): array {
    if (!$keys) return [];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $sql = "SELECT `key`,`value`,`type`,`default_value` FROM settings WHERE `key` IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($keys);
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $k = $row['key'];
        $type = $row['type'] ?: 'string';
        $valRaw = $row['value'];
        $defRaw = $row['default_value'];
        $raw = ($valRaw !== null && $valRaw !== '') ? $valRaw : $defRaw;

        switch ($type) {
            case 'boolean':
                $v = is_string($raw)
                    ? in_array(strtolower(trim($raw)), ['1','true','on','yes'], true)
                    : (bool)$raw;
                break;
            case 'integer':
                $v = (int)$raw;
                break;
            case 'json':
            case 'array':
                $v = json_decode((string)$raw, true);
                if ($v === null && json_last_error() !== JSON_ERROR_NONE) $v = [];
                break;
            case 'text':
            case 'string':
            default:
                $v = (string)$raw;
                break;
        }
        $out[$k] = $v;
    }
    return $out;
}

/* =======================
   Load user
   ======================= */
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        session_destroy();
        header('Location: ../login/');
        exit();
    }
} catch (PDOException $e) {
    logError("Settings load user error: " . $e->getMessage());
    header('Location: ../login/');
    exit();
}

/* =======================
   Load settings/flags
   ======================= */
try {
    $keys = [
        // theming / general
        'site_name','theme_color','secondary_color','site_icon','site_url',
        'affiliate_program_enabled','api_enabled',

        // support singular/plural admin flags
        'allow_username_changes','allow_username_change',
        'allow_email_changes','allow_email_change',
    ];
    $settings = fetch_settings_by_keys($pdo, $keys);
} catch (PDOException $e) {
    logError("Settings load table error: " . $e->getMessage());
    $settings = [];
}

$site_name   = $settings['site_name']     ?? 'PhPstrap';
$theme_color = $settings['theme_color']   ?? '#0d6efd';

// enable flags (singular OR plural)
$ALLOW_NAME  = !empty($settings['allow_username_changes']) || !empty($settings['allow_username_change']);
$ALLOW_EMAIL = !empty($settings['allow_email_changes'])   || !empty($settings['allow_email_change']);

/* =======================
   POST handling
   ======================= */
$flashes = [];
$push = function($type,$msg) use (&$flashes){ $flashes[] = ['type'=>$type,'msg'=>$msg]; };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!csrf_check($token)) {
        $push('danger', 'Security check failed. Please try again.');
    } else {
        // PROFILE (name/email)
        if ($action === 'profile') {
            $new_name  = trim((string)($_POST['name']  ?? $user['name']));
            $new_email = trim((string)($_POST['email'] ?? $user['email']));

            $do_update = false;
            $updates   = [];
            $params    = [];

            if ($ALLOW_NAME && $new_name !== $user['name']) {
                if ($new_name === '' || mb_strlen($new_name) < 2 || mb_strlen($new_name) > 100) {
                    $push('warning', 'Name must be between 2 and 100 characters.');
                } else {
                    $updates[] = "name = ?";
                    $params[]  = $new_name;
                    $do_update = true;
                }
            }

            $email_will_change = false;
            $new_verif_token   = null;

            if ($ALLOW_EMAIL && $new_email !== $user['email']) {
                if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                    $push('warning', 'Please enter a valid email address.');
                } else {
                    try {
                        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
                        $chk->execute([$new_email, $user['id']]);
                        if ($chk->fetch()) {
                            $push('warning', 'That email address is already in use.');
                        } else {
                            $updates[] = "email = ?";
                            $params[]  = $new_email;

                            // reset verification state on email change
                            $updates[] = "verified = 0";
                            $new_verif_token = bin2hex(random_bytes(32));
                            $updates[] = "verification_token = ?";
                            $params[]  = $new_verif_token;
                            $updates[] = "verified_at = NULL";
                            $updates[] = "last_verification_sent_at = NULL";

                            $email_will_change = true;
                            $do_update = true;
                        }
                    } catch (PDOException $e) {
                        logError("Settings email uniqueness check error: " . $e->getMessage());
                        $push('danger', 'Could not validate email uniqueness. Please try again.');
                    }
                }
            }

            if ($do_update && $updates) {
                try {
                    $sql = "UPDATE users SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = ?";
                    $params[] = $user['id'];
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    // refresh user row
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user;

                    $push('success', 'Profile updated successfully.');
                    if ($email_will_change) {
                        if (function_exists('sendVerificationEmail')) {
                            try { sendVerificationEmail($user['email'], $new_verif_token); }
                            catch (Throwable $e) { logError("sendVerificationEmail failed: ".$e->getMessage()); }
                        } else {
                            $push('info', 'We sent a verification link to your new email (or will shortly). Please verify to finish the change.');
                        }
                    }
                } catch (PDOException $e) {
                    logError("Settings profile update error: " . $e->getMessage());
                    $push('danger', 'Could not update your profile at this time.');
                }
            } else {
                $push('info', 'No changes to update.');
            }
        }

        // PASSWORD
        if ($action === 'password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password     = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if ($new_password !== $confirm_password) {
                $push('warning', 'New passwords do not match.');
            } elseif (strlen($new_password) < 8) {
                $push('warning', 'Password must be at least 8 characters.');
            } elseif (!password_verify($current_password, $user['password'])) {
                $push('danger', 'Your current password is incorrect.');
            } else {
                $hasUpper = preg_match('/[A-Z]/', $new_password);
                $hasLower = preg_match('/[a-z]/', $new_password);
                $hasNum   = preg_match('/\d/',   $new_password);
                if (!($hasUpper && $hasLower && $hasNum)) {
                    $push('warning', 'Use upper & lower case letters and at least one number.');
                } else {
                    try {
                        $hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$hash, $user['id']]);
                        $push('success', 'Your password has been updated.');
                    } catch (PDOException $e) {
                        logError("Settings update password error: " . $e->getMessage());
                        $push('danger', 'Could not update password at this time.');
                    }
                }
            }
        }
    }
}

// highlight Settings in the nav + ensure include before rendering main
$currentPage = 'settings';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Settings — <?= htmlspecialchars($site_name) ?></title>

  <!-- Bootstrap 5 CSS -->
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root { --primary-color: <?= htmlspecialchars($theme_color) ?>; }
    body { background: #f5f7fb; }
    .navbar .navbar-brand { font-weight: 700; color: var(--primary-color); }
    .card { border: none; border-radius: 1rem; box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,.05); }
  </style>
</head>
<body>
  <div class="container-fluid">
    <div class="row g-0">

      <?php
      // IMPORTANT: same placement as dashboard index — renders sidebar (lg) + offcanvas (mobile)
      include __DIR__ . '/includes/nav.php';
      ?>

      <main class="col-12 col-lg-10">
        <!-- Topbar (same as dashboard index) -->
        <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
          <div class="container-fluid">
            <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
              <i class="bi bi-list"></i>
            </button>
            <a class="navbar-brand" href="../"><?= htmlspecialchars($site_name) ?></a>
            <div class="ms-auto d-flex align-items-center">
              <div class="rounded-circle d-flex align-items-center justify-content-center me-2 text-white"
                   style="width:40px;height:40px;background:var(--primary-color);font-weight:700;">
                <?= strtoupper(substr($user['name'] ?? $user['email'], 0, 1)) ?>
              </div>
              <div class="small">
                <div class="fw-semibold"><?= htmlspecialchars($user['name'] ?? $user['email']) ?></div>
                <div class="text-muted"><?= htmlspecialchars($user['email']) ?></div>
              </div>
            </div>
          </div>
        </nav>

        <!-- Content -->
        <div class="p-4">
          <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show" role="alert">
              <?= $f['msg'] ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endforeach; ?>

          <div class="row g-3">
            <!-- Profile (name/email) -->
            <div class="col-12 col-xl-7">
              <div class="card">
                <div class="card-header d-flex align-items-center">
                  <strong><i class="bi bi-person-gear me-2"></i>Profile</strong>
                  <?php if (!$ALLOW_NAME || !$ALLOW_EMAIL): ?>
                    <span class="ms-auto small text-muted">
                      <?= !$ALLOW_NAME ? 'Name locked' : '' ?><?= (!$ALLOW_NAME && !$ALLOW_EMAIL) ? ' • ' : '' ?><?= !$ALLOW_EMAIL ? 'Email locked' : '' ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div class="card-body">
                  <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="profile">

                    <div class="mb-3">
                      <label for="name" class="form-label">Name</label>
                      <input
                        type="text"
                        class="form-control"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                        <?= $ALLOW_NAME ? '' : 'readonly' ?>
                        maxlength="100"
                      >
                      <?php if (!$ALLOW_NAME): ?>
                        <div class="form-text text-muted">Name changes are disabled by the administrator.</div>
                      <?php endif; ?>
                    </div>

                    <div class="mb-3">
                      <label for="email" class="form-label">Email</label>
                      <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                        <?= $ALLOW_EMAIL ? '' : 'readonly' ?>
                      >
                      <?php if (!$ALLOW_EMAIL): ?>
                        <div class="form-text text-muted">Email changes are disabled by the administrator.</div>
                      <?php else: ?>
                        <div class="form-text">Changing your email will require re-verification.</div>
                      <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                      <button type="submit" class="btn btn-primary" <?= (!$ALLOW_NAME && !$ALLOW_EMAIL) ? 'disabled' : '' ?>>
                        <i class="bi bi-save me-1"></i> Save Changes
                      </button>
                      <a href="./" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Password -->
            <div class="col-12 col-xl-5">
              <div class="card">
                <div class="card-header">
                  <strong><i class="bi bi-shield-lock me-2"></i>Change Password</strong>
                </div>
                <div class="card-body">
                  <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="password">

                    <div class="mb-3">
                      <label for="current_password" class="form-label">Current Password</label>
                      <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                      <label for="new_password" class="form-label">New Password</label>
                      <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                      <div class="form-text">At least 8 characters, with upper & lower case letters and a number.</div>
                      <div class="progress mt-2" role="progressbar" aria-label="Password strength" style="height:6px;">
                        <div id="pwStrengthBar" class="progress-bar" style="width: 0%"></div>
                      </div>
                    </div>

                    <div class="mb-4">
                      <label for="confirm_password" class="form-label">Confirm New Password</label>
                      <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    </div>

                    <div class="d-flex gap-2">
                      <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Update Password
                      </button>
                      <a href="./" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Optional TOTP status -->
              <div class="card mt-3">
                <div class="card-header">
                  <strong><i class="bi bi-shield-check me-2"></i>Security</strong>
                </div>
                <div class="card-body">
                  <p class="mb-2">Two-Factor Authentication (TOTP):
                    <?php if (!empty($user['totp_enabled'])): ?>
                      <span class="badge bg-success">Enabled</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Disabled</span>
                    <?php endif; ?>
                  </p>
                  <ul class="small text-muted ps-3">
                    <li>Use a unique password you don’t use elsewhere.</li>
                    <li>Consider enabling 2FA for stronger protection.</li>
                    <li>Passwords are hashed using industry-standard algorithms.</li>
                  </ul>
                </div>
              </div>

            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Password strength bar
    const pw = document.getElementById('new_password');
    const bar = document.getElementById('pwStrengthBar');
    if (pw && bar) {
      pw.addEventListener('input', () => {
        const v = pw.value || '';
        let score = 0;
        if (v.length >= 8) score += 25;
        if (/[a-z]/.test(v)) score += 20;
        if (/[A-Z]/.test(v)) score += 25;
        if (/\d/.test(v))    score += 20;
        if (/[^A-Za-z0-9]/.test(v)) score += 10;
        if (score > 100) score = 100;
        bar.style.width = score + '%';
        bar.className = 'progress-bar';
        if (score < 40) bar.classList.add('bg-danger');
        else if (score < 70) bar.classList.add('bg-warning');
        else bar.classList.add('bg-success');
      });
    }

    // Auto-dismiss alerts
    setTimeout(() => {
      document.querySelectorAll('.alert-dismissible').forEach(el => {
        try { new bootstrap.Alert(el).close(); } catch (e) {}
      });
    }, 5000);
  </script>
</body>
</html>