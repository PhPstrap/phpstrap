<?php
// dashboard/profile.php — User profile (general info + avatar)
require_once '../config/app.php';
require_once '../config/functions.php';

session_start();

// Require login
if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
    header('Location: ../login/');
    exit();
}

/* =======================
   CSRF + helpers
   ======================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_check($token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}

/**
 * Fetch specific settings by keys from the `settings` table and cast by `type`.
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
    logError("Profile load user error: " . $e->getMessage());
    header('Location: ../login/');
    exit();
}

/* =======================
   Load settings + feature flags
   ======================= */
try {
    $keys = [
        'site_name','theme_color','secondary_color','site_icon',
        'affiliate_program_enabled','api_enabled',
        // respect both singular/plural for name flag
        'allow_username_changes','allow_username_change'
    ];
    $settings = fetch_settings_by_keys($pdo, $keys);
} catch (PDOException $e) {
    logError("Profile load settings error: " . $e->getMessage());
    $settings = [];
}

$site_name   = $settings['site_name']   ?? 'PhPstrap';
$theme_color = $settings['theme_color'] ?? '#0d6efd';
$ALLOW_NAME  = !empty($settings['allow_username_changes']) || !empty($settings['allow_username_change']);

/* =======================
   Handle POST (profile update / avatar upload/remove)
   ======================= */
$flashes = [];
$push = function($type,$msg) use (&$flashes){ $flashes[] = ['type'=>$type,'msg'=>$msg]; };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? 'profile';

    if (!csrf_check($token)) {
        $push('danger', 'Security check failed. Please refresh and try again.');
    } else {

        if ($action === 'profile') {
            // Gather inputs
            $new_name     = trim((string)($_POST['name'] ?? $user['name']));
            $company_name = trim((string)($_POST['company_name'] ?? $user['company_name']));
            $phone        = trim((string)($_POST['phone_number'] ?? $user['phone_number']));
            $timezone     = trim((string)($_POST['timezone'] ?? ($user['timezone'] ?? 'UTC')));
            $language     = trim((string)($_POST['language'] ?? ($user['language'] ?? 'en')));
            $website      = trim((string)($_POST['website'] ?? $user['website']));
            $bio          = trim((string)($_POST['bio'] ?? $user['bio']));
            $address      = trim((string)($_POST['address'] ?? $user['address']));
            $email_notif  = isset($_POST['email_notifications']) ? 1 : 0;
            $mkt_emails   = isset($_POST['marketing_emails']) ? 1 : 0;

            // Social links: accept JSON string or simple key/value pairs
            $social_input = $_POST['social_links'] ?? '';
            $social_links = [];
            if (is_string($social_input) && $social_input !== '') {
                $decoded = json_decode($social_input, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $social_links = $decoded;
                } else {
                    // fallback: parse newline key:value
                    $lines = preg_split('/\r\n|\r|\n/', $social_input);
                    foreach ($lines as $line) {
                        if (strpos($line, ':') !== false) {
                            [$k,$v] = array_map('trim', explode(':', $line, 2));
                            if ($k !== '' && $v !== '') $social_links[$k] = $v;
                        }
                    }
                }
            }

            // Build update query parts
            $updates = [];
            $params  = [];

            if ($ALLOW_NAME && $new_name !== $user['name']) {
                if ($new_name === '' || mb_strlen($new_name) < 2 || mb_strlen($new_name) > 100) {
                    $push('warning', 'Name must be between 2 and 100 characters.');
                } else {
                    $updates[] = "name = ?";
                    $params[]  = $new_name;
                }
            }

            // other fields
            if ($company_name !== ($user['company_name'] ?? null)) { $updates[]="company_name = ?"; $params[]=$company_name; }
            if ($phone        !== ($user['phone_number'] ?? null)) { $updates[]="phone_number = ?"; $params[]=$phone; }
            if ($address      !== ($user['address'] ?? null))      { $updates[]="address = ?"; $params[]=$address; }
            if ($timezone     !== ($user['timezone'] ?? 'UTC'))    { $updates[]="timezone = ?"; $params[]=$timezone; }
            if ($language     !== ($user['language'] ?? 'en'))     { $updates[]="language = ?"; $params[]=$language; }
            if ($website      !== ($user['website'] ?? null))      { $updates[]="website = ?"; $params[]=$website; }
            if ($bio          !== ($user['bio'] ?? null))          { $updates[]="bio = ?"; $params[]=$bio; }
            if ($email_notif  !== (int)($user['email_notifications'] ?? 1)) { $updates[]="email_notifications = ?"; $params[]=$email_notif; }
            if ($mkt_emails   !== (int)($user['marketing_emails'] ?? 0))    { $updates[]="marketing_emails = ?";    $params[]=$mkt_emails; }
            // social_links as JSON
            $social_json = json_encode($social_links, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if ($social_json !== ($user['social_links'] ?? null)) { $updates[]="social_links = ?"; $params[]=$social_json; }

            // Avatar upload (optional)
            if (!empty($_FILES['avatar']['name'])) {
                $file = $_FILES['avatar'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $tmp = $file['tmp_name'];
                    $size = (int)$file['size'];
                    if ($size > 0 && $size <= 3 * 1024 * 1024) { // 3MB
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime  = $finfo->file($tmp);
                        $allowed = [
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/webp' => 'webp'
                        ];
                        if (isset($allowed[$mime])) {
                            $ext = $allowed[$mime];
                            $dir = realpath(__DIR__ . '/../uploads/avatars');
                            if ($dir === false) {
                                // attempt to create
                                $newDir = __DIR__ . '/../uploads/avatars';
                                if (!is_dir($newDir)) @mkdir($newDir, 0755, true);
                                $dir = realpath($newDir);
                            }
                            if ($dir !== false && is_writable($dir)) {
                                $filename = sprintf('u%d_%s.%s', (int)$user['id'], bin2hex(random_bytes(6)), $ext);
                                $dest = $dir . DIRECTORY_SEPARATOR . $filename;
                                if (move_uploaded_file($tmp, $dest)) {
                                    // store relative path from /dashboard/ context -> "../uploads/avatars/..."
                                    $relative = '../uploads/avatars/' . $filename;
                                    $updates[] = "avatar = ?";
                                    $params[]  = $relative;
                                } else {
                                    $push('danger', 'Could not save uploaded avatar.');
                                }
                            } else {
                                $push('danger', 'Avatar directory is not writable.');
                            }
                        } else {
                            $push('warning', 'Invalid avatar type. Allowed: JPG, PNG, WEBP (max 3MB).');
                        }
                    } else {
                        $push('warning', 'Avatar too large (max 3MB).');
                    }
                } else {
                    $push('warning', 'Avatar upload failed (error code '.$file['error'].').');
                }
            }

            if ($updates) {
                try {
                    $sql = "UPDATE users SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = ?";
                    $params[] = $user['id'];
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    // refresh user
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user;

                    $push('success', 'Profile updated successfully.');
                } catch (PDOException $e) {
                    logError("Profile update error: " . $e->getMessage());
                    $push('danger', 'Could not update your profile at this time.');
                }
            } else {
                $push('info', 'No changes to update.');
            }
        }

        if ($action === 'remove_avatar') {
            try {
                $stmt = $pdo->prepare("UPDATE users SET avatar = NULL, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                // optionally delete file on disk (only if path points to our avatars dir)
                if (!empty($user['avatar']) && str_starts_with($user['avatar'], '../uploads/avatars/')) {
                    $path = realpath(__DIR__ . '/../uploads/avatars/' . basename($user['avatar']));
                    if ($path && is_file($path)) @unlink($path);
                }
                // refresh user
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user;

                $push('success', 'Avatar removed.');
            } catch (PDOException $e) {
                logError("Remove avatar error: " . $e->getMessage());
                $push('danger', 'Could not remove avatar right now.');
            }
        }
    }
}

// highlight in nav
$currentPage = 'profile';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profile — <?= htmlspecialchars($site_name) ?></title>

  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --primary-color: <?= htmlspecialchars($theme_color) ?>; }
    body { background: #f5f7fb; }
    .navbar .navbar-brand { font-weight: 700; color: var(--primary-color); }
    .card { border: none; border-radius: 1rem; box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,.05); }
    .avatar-preview { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; }
  </style>
</head>
<body>
  <div class="container-fluid">
    <div class="row g-0">

      <?php
      // Use your new location for the nav:
      include __DIR__ . '/includes/nav.php';
      ?>

      <main class="col-12 col-lg-10">
        <!-- Topbar -->
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

        <div class="p-4">
          <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show" role="alert">
              <?= $f['msg'] ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endforeach; ?>

          <div class="row g-3">
            <!-- Left: Profile form -->
            <div class="col-12 col-xl-8">
              <div class="card">
                <div class="card-header">
                  <strong><i class="bi bi-person-lines-lead me-2"></i>Your Profile</strong>
                </div>
                <div class="card-body">
                  <form method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="profile">

                    <div class="row">
                      <div class="col-md-8">
                        <div class="mb-3">
                          <label for="name" class="form-label">Name</label>
                          <input type="text" class="form-control" id="name" name="name"
                                 value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                                 <?= $ALLOW_NAME ? '' : 'readonly' ?> maxlength="100">
                          <?php if (!$ALLOW_NAME): ?>
                            <div class="form-text text-muted">Name changes are disabled by the administrator.</div>
                          <?php endif; ?>
                        </div>

                        <div class="mb-3">
                          <label class="form-label">Email</label>
                          <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                          <div class="form-text">Want to change your email? Use <a href="settings.php">Settings</a>.</div>
                        </div>

                        <div class="row">
                          <div class="col-md-6 mb-3">
                            <label for="company_name" class="form-label">Company</label>
                            <input type="text" class="form-control" id="company_name" name="company_name"
                                   value="<?= htmlspecialchars($user['company_name'] ?? '') ?>">
                          </div>
                          <div class="col-md-6 mb-3">
                            <label for="phone_number" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number"
                                   value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                          </div>
                        </div>

                        <div class="mb-3">
                          <label for="address" class="form-label">Address</label>
                          <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                          <div class="col-md-6 mb-3">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select id="timezone" name="timezone" class="form-select">
                              <?php
                              $tzOptions = ['UTC','America/Toronto','America/New_York','America/Chicago','America/Los_Angeles','Europe/London','Europe/Berlin','Asia/Tokyo','Asia/Singapore','Australia/Sydney'];
                              $curTz = $user['timezone'] ?? 'UTC';
                              foreach ($tzOptions as $tz) {
                                  $sel = ($curTz === $tz) ? 'selected' : '';
                                  echo "<option value=\"".htmlspecialchars($tz)."\" $sel>".htmlspecialchars($tz)."</option>";
                              }
                              ?>
                            </select>
                          </div>
                          <div class="col-md-6 mb-3">
                            <label for="language" class="form-label">Language</label>
                            <select id="language" name="language" class="form-select">
                              <?php
                              $langs = ['en'=>'English','fr'=>'Français','es'=>'Español','de'=>'Deutsch','it'=>'Italiano','pt'=>'Português'];
                              $curLang = $user['language'] ?? 'en';
                              foreach ($langs as $code=>$label) {
                                  $sel = ($curLang === $code) ? 'selected' : '';
                                  echo "<option value=\"$code\" $sel>".htmlspecialchars($label)."</option>";
                              }
                              ?>
                            </select>
                          </div>
                        </div>

                        <div class="mb-3">
                          <label for="website" class="form-label">Website</label>
                          <input type="url" class="form-control" id="website" name="website"
                                 value="<?= htmlspecialchars($user['website'] ?? '') ?>" placeholder="https://example.com">
                        </div>

                        <div class="mb-3">
                          <label for="bio" class="form-label">Bio</label>
                          <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Tell us about yourself"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                          <label for="social_links" class="form-label">Social Links</label>
                          <textarea class="form-control" id="social_links" name="social_links" rows="3"
                                    placeholder='JSON (e.g. {"twitter":"https://x.com/you"}) or one per line: key: value'><?= htmlspecialchars($user['social_links'] ?? '') ?></textarea>
                          <div class="form-text">JSON recommended. We’ll store this in the <code>social_links</code> JSON column.</div>
                        </div>

                        <div class="form-check mb-2">
                          <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?= ((int)($user['email_notifications'] ?? 1) === 1 ? 'checked' : '') ?>>
                          <label class="form-check-label" for="email_notifications">Enable email notifications</label>
                        </div>
                        <div class="form-check mb-4">
                          <input class="form-check-input" type="checkbox" id="marketing_emails" name="marketing_emails" <?= ((int)($user['marketing_emails'] ?? 0) === 1 ? 'checked' : '') ?>>
                          <label class="form-check-label" for="marketing_emails">Receive marketing emails</label>
                        </div>

                        <div class="d-flex gap-2">
                          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Profile</button>
                          <a href="./" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                      </div>

                      <!-- Right: Avatar -->
                      <div class="col-md-4">
                        <div class="mb-3">
                          <label class="form-label d-block">Avatar</label>
                          <div class="d-flex align-items-center gap-3">
                            <?php if (!empty($user['avatar'])): ?>
                              <img class="avatar-preview border" src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar">
                            <?php else: ?>
                              <div class="avatar-preview border d-flex align-items-center justify-content-center text-muted" style="background:#eee;">
                                <span class="fw-bold"><?= strtoupper(substr($user['name'] ?? $user['email'], 0, 1)) ?></span>
                              </div>
                            <?php endif; ?>
                          </div>
                        </div>
                        <div class="mb-3">
                          <input class="form-control" type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp">
                          <div class="form-text">JPG/PNG/WEBP, up to 3MB.</div>
                        </div>
                        <?php if (!empty($user['avatar'])): ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="remove_avatar">
                            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash3 me-1"></i> Remove Avatar</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </div>

                  </form>
                </div>
              </div>
            </div>

            <!-- Right: Account summary card -->
            <div class="col-12 col-xl-4">
              <div class="card">
                <div class="card-header">
                  <strong><i class="bi bi-info-circle me-2"></i>Account Summary</strong>
                </div>
                <div class="card-body">
                  <p class="mb-1"><strong>Membership:</strong> <?= htmlspecialchars(ucfirst($user['membership_status'] ?? 'free')) ?></p>
                  <?php if (!empty($user['membership_expiry'])): ?>
                    <p class="mb-1"><strong>Expires:</strong> <?= date('M j, Y', strtotime($user['membership_expiry'])) ?></p>
                  <?php endif; ?>
                  <p class="mb-1"><strong>Joined:</strong> <?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                  <p class="mb-1"><strong>Last login:</strong> <?= !empty($user['last_login_at']) ? date('M j, Y g:i A', strtotime($user['last_login_at'])) : 'Never' ?></p>
                  <p class="mb-0"><strong>Verification:</strong>
                    <?= !empty($user['verified']) ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning text-dark">Pending</span>' ?>
                  </p>
                  <hr>
                  <a href="settings.php" class="btn btn-outline-primary w-100"><i class="bi bi-shield-lock me-1"></i> Password & Email</a>
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
    // Auto-dismiss alerts
    setTimeout(() => {
      document.querySelectorAll('.alert-dismissible').forEach(el => {
        try { new bootstrap.Alert(el).close(); } catch (e) {}
      });
    }, 5000);
  </script>
</body>
</html>