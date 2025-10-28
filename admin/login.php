<?php
/**
 * PhPstrap Admin Login - Styled to match user login
 * Secure admin authentication with optional hCaptcha
 * - Keeps CSRF token
 * - Keeps lockout + logging
 * - Adds "Back to User Login" link
 */

// Production error settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Core bootstrapping
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// Initialize (starts session, loads helpers, etc.)
initializeApp();

/* ===============================
   hCaptcha detection (same logic)
   =============================== */
function checkHCaptchaStatus($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT enabled, settings FROM modules WHERE name = 'hcaptcha'");
        $stmt->execute();
        if ($module = $stmt->fetch()) {
            $enabled  = (bool)$module['enabled'];
            $settings = json_decode($module['settings'], true);
            if ($enabled && !empty($settings['site_key']) && !empty($settings['secret_key'])) {
                return ['enabled' => true, 'source' => 'database', 'settings' => $settings];
            }
        }
    } catch (Exception $e) { error_log("hCaptcha DB check error: ".$e->getMessage()); }

    if (file_exists(__DIR__ . '/../modules/hcaptcha/HCaptcha.php')) {
        return ['enabled' => false, 'source' => 'files_only', 'settings' => []];
    }
    return ['enabled' => false, 'source' => 'not_available', 'settings' => []];
}

$pdo              = getDbConnection();
$hcaptcha         = null;
$hcaptcha_status  = checkHCaptchaStatus($pdo);
$hcaptcha_enabled = false;

if ($hcaptcha_status['enabled']) {
    try {
        require_once __DIR__ . '/../modules/hcaptcha/HCaptcha.php';
        $hcaptcha = new PhPstrap\Modules\HCaptcha\HCaptcha();
        $hcaptcha->init();
        $hcaptcha_enabled = true;
    } catch (Exception $e) { error_log("hCaptcha init error: ".$e->getMessage()); }
}

/* ===============================
   CSRF helpers
   =============================== */
function getToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}
function checkToken($t) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $t);
}

/* ===============================
   Already logged in?
   =============================== */
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

/* ===============================
   Messages
   =============================== */
$message = '';
$error   = false;

if (isset($_SESSION['logout_message'])) {
    $message = $_SESSION['logout_message'];
    $error   = false;
    unset($_SESSION['logout_message']);
}

/* ===============================
   Login POST
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $token       = $_POST['token'] ?? '';
    $remember_me = !empty($_POST['remember_me']);

    if (!checkToken($token)) {
        $message = 'Invalid security token. Please try again.';
        $error   = true;
    } elseif ($email === '' || $password === '') {
        $message = 'Email and password are required.';
        $error   = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $error   = true;
    } else {
        // Optional hCaptcha
        $captcha_valid = true;
        if ($hcaptcha_enabled && $hcaptcha) {
            try {
                $captcha_result = $hcaptcha->validateCaptcha();
                if (!$captcha_result['success']) {
                    $captcha_valid = false;
                    $message = $captcha_result['message'];
                    $error   = true;
                }
            } catch (Exception $e) {
                error_log("hCaptcha validation error in admin login: ".$e->getMessage());
                $captcha_valid = true; // fail-open on captcha outage
            }
        }

        if ($captcha_valid) {
            try {
                // Table bootstrap (safe, idempotent)
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        email VARCHAR(255) UNIQUE NOT NULL,
                        password VARCHAR(255) NOT NULL,
                        is_admin BOOLEAN DEFAULT 0,
                        is_active BOOLEAN DEFAULT 1,
                        login_attempts INT DEFAULT 0,
                        locked_until DATETIME NULL,
                        last_login_at DATETIME NULL,
                        last_login_ip VARCHAR(45) NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");

                // Ensure at least one admin exists (first-run safety)
                $countAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=1 AND is_active=1")->fetchColumn();
                if ($countAdmins === 0) {
                    $stmt = $pdo->prepare("INSERT INTO users (name,email,password,is_admin) VALUES (?,?,?,1)");
                    $stmt->execute(['Administrator','admin@example.com', password_hash('admin123', PASSWORD_DEFAULT)]);
                }

                // Lookup user (must be active)
                $stmt = $pdo->prepare("
                    SELECT id, name, email, password, is_admin, is_active, login_attempts, locked_until
                    FROM users
                    WHERE email = ? AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $message = 'Invalid credentials. Please try again.';
                    $error   = true;
                    logFailedAttempt($pdo, $email, 'user_not_found');
                } elseif (!$user['is_admin']) {
                    $message = 'Access denied. Administrator privileges required.';
                    $error   = true;
                    logFailedAttempt($pdo, $email, 'not_admin');
                } elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $unlock_time = date('H:i', strtotime($user['locked_until']));
                    $message     = "Account temporarily locked until $unlock_time due to multiple failed login attempts.";
                    $error       = true;
                } elseif (!password_verify($password, $user['password'])) {
                    incrementFailedAttempts($pdo, (int)$user['id']);
                    $message = 'Invalid credentials. Please try again.';
                    $error   = true;
                    logFailedAttempt($pdo, $email, 'invalid_password');
                } else {
                    // Success: create admin session
                    session_regenerate_id(true);
                    $_SESSION['admin_id']        = (int)$user['id'];
                    $_SESSION['admin_name']      = $user['name'];
                    $_SESSION['admin_email']     = $user['email'];
                    $_SESSION['admin_login_time']= time();

                    if ($remember_me) {
                        $token   = bin2hex(random_bytes(32));
                        $expires = time() + (30*24*60*60);
                        setcookie('remember_admin_token', $token, $expires, '/', '', isset($_SERVER['HTTPS']), true);
                        $_SESSION['remember_token'] = $token;
                    }

                    // Update last login + reset attempts
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, (int)$user['id']]);

                    logSuccessfulLogin($pdo, (int)$user['id'], $email);

                    // Redirect
                    $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    header("Location: $redirect");
                    exit;
                }
            } catch (Exception $e) {
                $message = 'A system error occurred. Please try again later.';
                $error   = true;
                error_log('Admin login error: '.$e->getMessage());
            }
        }
    }
}

/* ===============================
   Helpers: attempts + logs
   =============================== */
function incrementFailedAttempts($pdo, $user_id) {
    try {
        $pdo->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?")->execute([$user_id]);
        $attempts = (int)$pdo->prepare("SELECT login_attempts FROM users WHERE id = ?")
                             ->execute([$user_id]) ?: 0;
        $attempts = (int)$pdo->query("SELECT login_attempts FROM users WHERE id = {$user_id}")->fetchColumn();
        if ($attempts >= 5) {
            $locked_until = date('Y-m-d H:i:s', time() + 900); // 15 min
            $pdo->prepare("UPDATE users SET locked_until = ? WHERE id = ?")->execute([$locked_until, $user_id]);
        }
    } catch (Exception $e) { error_log('Error incrementing failed attempts: '.$e->getMessage()); }
}
function logFailedAttempt($pdo, $email, $reason) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255),
                ip_address VARCHAR(45),
                user_agent TEXT,
                success BOOLEAN,
                reason VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (email, ip_address, user_agent, success, reason)
            VALUES (?, ?, ?, 0, ?)
        ");
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $reason]);
    } catch (Exception $e) { error_log('Error logging failed attempt: '.$e->getMessage()); }
}
function logSuccessfulLogin($pdo, $user_id, $email) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (email, ip_address, user_agent, success, reason)
            VALUES (?, ?, ?, 1, 'successful_admin_login')
        ");
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
    } catch (Exception $e) { error_log('Error logging successful login: '.$e->getMessage()); }
}

/* ===============================
   Theming (match user login)
   =============================== */
$site_name       = defined('SITE_NAME') ? SITE_NAME : 'PhPstrap';
$theme_color     = defined('THEME_COLOR') ? THEME_COLOR : '#007bff';
$secondary_color = defined('SECONDARY_COLOR') ? SECONDARY_COLOR : '#6c757d';
$site_icon       = defined('SITE_ICON') ? SITE_ICON : 'fas fa-shield-alt';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login - <?= htmlspecialchars($site_name) ?></title>

<!-- Bootstrap (local to match user login) -->
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<?php if ($hcaptcha_enabled): ?>
<script src="https://js.hcaptcha.com/1/api.js" async defer></script>
<?php if (file_exists(__DIR__ . '/../modules/hcaptcha/assets/hcaptcha.css')): ?>
<link href="../modules/hcaptcha/assets/hcaptcha.css" rel="stylesheet">
<?php endif; endif; ?>

<style>
:root{
  --theme-color: <?= $theme_color ?>;
  --secondary-color: <?= $secondary_color ?>;
}
body{
  background: linear-gradient(135deg, var(--theme-color) 0%, #6f42c1 100%);
  min-height:100vh; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
}
.login-container{min-height:100vh; display:flex; align-items:center; padding:2rem 0;}
.card{border:none; border-radius:1rem; box-shadow:0 15px 35px rgba(0,0,0,.1); backdrop-filter:blur(10px);}
.btn-primary{background-color:var(--theme-color); border-color:var(--theme-color);}
.btn-primary:hover{background-color:#0056b3; border-color:#0056b3;}
.logo{width:80px; height:80px; background:var(--theme-color); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; color:#fff; font-size:2rem;}
.input-group .input-group-text{background-color:#f8f9fa; border-color:#dee2e6;}
.form-control:focus{border-color:var(--theme-color); box-shadow:0 0 0 .2rem rgba(13,110,253,.25);}
.security-status{font-size:.75rem; opacity:.7; margin-top:.5rem;}
.security-status.enabled{color:#28a745;}
.security-status.disabled{color:#ced4da;}
.hcaptcha-container{margin:1.5rem 0; text-align:center;}
.hcaptcha-container .h-captcha{display:inline-block;}
@media (max-width:480px){ .hcaptcha-container .h-captcha{transform:scale(.85); transform-origin:center;} }
.alert{border-radius:.5rem;}
</style>
</head>
<body>
<div class="container login-container">
  <div class="row justify-content-center w-100">
    <div class="col-md-6 col-lg-5">

      <div class="card shadow-lg">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <div class="logo"><i class="<?= $site_icon ?>"></i></div>
            <h2 class="h3 mb-0">Admin Login</h2>
            <p class="text-muted">Restricted area — authorized admins only</p>

            <!-- Security Status -->
            <div class="security-status <?= $hcaptcha_enabled ? 'enabled' : 'disabled' ?>">
              <i class="fas fa-shield-<?= $hcaptcha_enabled ? 'alt' : 'halved' ?> me-1"></i>
              <?php if ($hcaptcha_enabled): ?>
                Enhanced security enabled
              <?php else: ?>
                <?php if ($hcaptcha_status['source'] === 'not_available'): ?>
                  Standard security
                <?php elseif ($hcaptcha_status['source'] === 'files_only'): ?>
                  Security module available but not configured
                <?php else: ?>
                  Security module not configured
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Alerts -->
          <?php if ($message): ?>
          <div class="alert alert-<?= $error ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $error ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i><?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>

          <!-- Form -->
          <form method="POST" id="adminLoginForm" class="needs-validation" novalidate>
            <input type="hidden" name="token" value="<?= getToken() ?>">

            <div class="mb-3">
              <label for="email" class="form-label">Admin Email</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autocomplete="email" placeholder="admin@yourdomain.com">
                <div class="invalid-feedback">Please enter a valid email address.</div>
              </div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password"
                       required autocomplete="current-password" placeholder="Enter your password">
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                  <i class="fas fa-eye"></i>
                </button>
                <div class="invalid-feedback">Please enter your password.</div>
              </div>
            </div>

            <?php if ($hcaptcha_enabled && $hcaptcha): ?>
            <div class="hcaptcha-container mb-4">
              <?php
              try {
                  echo $hcaptcha->renderWidget(['theme'=>'light','size'=>'normal']);
              } catch (Exception $e) {
                  echo '<div class="alert alert-warning"><small><i class="fas fa-exclamation-triangle me-1"></i>Security verification temporarily unavailable</small></div>';
                  error_log("hCaptcha render error: ".$e->getMessage());
              }
              ?>
            </div>
            <?php endif; ?>

            <div class="mb-3 form-check">
              <input class="form-check-input" type="checkbox" name="remember_me" value="1" id="rememberMe">
              <label class="form-check-label" for="rememberMe"><i class="fas fa-clock me-1"></i>Remember me for 30 days</label>
            </div>

            <div class="d-grid gap-2 mb-3">
              <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In to Admin Panel
              </button>
            </div>
          </form>

          <!-- Links (match user login footer row) -->
          <div class="row text-center pt-3 border-top">
            <div class="col-12">
              <a href="../login/" class="text-decoration-none small">
                <i class="fas fa-arrow-left me-1"></i>Back to User Login
              </a>
            </div>
          </div>

          <div class="text-center mt-2">
            <a href="../" class="text-decoration-none small">
              <i class="fas fa-home me-1"></i>Return to Website
            </a>
          </div>

          <div class="text-center mt-3">
            <small class="text-muted"><i class="fas fa-shield-alt me-1"></i>This is a secure area. All access attempts are logged.</small>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<?php if ($hcaptcha_enabled && file_exists(__DIR__ . '/../modules/hcaptcha/assets/hcaptcha.js')): ?>
<script src="../modules/hcaptcha/assets/hcaptcha.js"></script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Password toggle
  const togglePassword = document.getElementById('togglePassword');
  const passwordInput  = document.getElementById('password');
  if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', function () {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      const icon = this.querySelector('i');
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    });
  }

  // Autofocus email if empty
  const emailInput = document.getElementById('email');
  if (emailInput && emailInput.value === '') emailInput.focus();

  // Auto-hide alerts
  setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el => {
      try { (new bootstrap.Alert(el)).close(); } catch(e){}
    });
  }, 5000);

  // Client-side validation + optional hCaptcha check
  const form = document.getElementById('adminLoginForm');
  form.addEventListener('submit', function (e) {
    if (!form.checkValidity()) {
      e.preventDefault(); e.stopPropagation();
    }
    <?php if ($hcaptcha_enabled): ?>
    const hcaptchaResponse = document.querySelector('[name="h-captcha-response"]');
    if (hcaptchaResponse && !hcaptchaResponse.value) {
      e.preventDefault(); e.stopPropagation();
      alert('Please complete the security verification before submitting.');
      return false;
    }
    <?php endif; ?>
    form.classList.add('was-validated');
  });

  console.log('PhPstrap Admin Login loaded successfully');
  console.log('hCaptcha status: <?= $hcaptcha_enabled ? 'enabled' : 'disabled' ?>');
});
</script>
</body>
</html>