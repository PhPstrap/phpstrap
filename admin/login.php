<?php
/**
 * PhPstrap Admin Login - Production Version
 * Secure admin authentication with optional hCaptcha protection
 */

// Disable error display for production
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include PhPstrap core files
require_once '../config/database.php';
require_once '../config/app.php';

// Initialize the application (this will start session)
initializeApp();

// ============================================
// hCaptcha Configuration & Initialization
// ============================================

/**
 * Check if hCaptcha should be enabled
 * Priority: 1. Database setting, 2. File existence, 3. Manual config
 */
function checkHCaptchaStatus($pdo) {
    // Check database for module status first
    try {
        $stmt = $pdo->prepare("SELECT enabled, settings FROM modules WHERE name = 'hcaptcha'");
        $stmt->execute();
        $module = $stmt->fetch();
        
        if ($module) {
            $enabled = (bool)$module['enabled'];
            $settings = json_decode($module['settings'], true);
            
            // Module exists in DB, check if it's properly configured
            if ($enabled && !empty($settings['site_key']) && !empty($settings['secret_key'])) {
                return [
                    'enabled' => true,
                    'source' => 'database',
                    'settings' => $settings
                ];
            }
        }
    } catch (Exception $e) {
        error_log("hCaptcha DB check error: " . $e->getMessage());
    }
    
    // Fallback: Check if files exist but no DB entry
    if (file_exists('../modules/hcaptcha/HCaptcha.php')) {
        return [
            'enabled' => false, // Disabled by default if not configured
            'source' => 'files_only',
            'settings' => []
        ];
    }
    
    return [
        'enabled' => false,
        'source' => 'not_available',
        'settings' => []
    ];
}

// Initialize hCaptcha
$hcaptcha = null;
$hcaptcha_status = checkHCaptchaStatus(getDbConnection());
$hcaptcha_enabled = false;

if ($hcaptcha_status['enabled']) {
    try {
        require_once '../modules/hcaptcha/HCaptcha.php';
        $hcaptcha = new PhPstrap\Modules\HCaptcha\HCaptcha();
        $hcaptcha->init();
        $hcaptcha_enabled = true;
    } catch (Exception $e) {
        error_log("hCaptcha initialization error: " . $e->getMessage());
        $hcaptcha_enabled = false;
    }
}

// ============================================
// CSRF Protection Functions
// ============================================

// Simple CSRF token function (using the config's CSRF_TOKEN_NAME)
function getToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function checkToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = false;

// Check for logout messages
if (isset($_SESSION['logout_message'])) {
    $message = $_SESSION['logout_message'];
    $error = false;
    unset($_SESSION['logout_message']);
}

// ============================================
// Login Form Processing
// ============================================

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['token'] ?? '';
    $remember_me = !empty($_POST['remember_me']);
    
    // Basic validation
    if (!checkToken($token)) {
        $message = 'Invalid security token. Please try again.';
        $error = true;
    } elseif (empty($email) || empty($password)) {
        $message = 'Email and password are required.';
        $error = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $error = true;
    } else {
        // Validate hCaptcha if enabled and configured
        $captcha_valid = true;
        if ($hcaptcha_enabled && $hcaptcha) {
            try {
                $captcha_result = $hcaptcha->validateCaptcha();
                if (!$captcha_result['success']) {
                    $captcha_valid = false;
                    $message = $captcha_result['message'];
                    $error = true;
                }
            } catch (Exception $e) {
                error_log("hCaptcha validation error in admin login: " . $e->getMessage());
                // Fail gracefully - allow admin login without hCaptcha if there's an error
                $captcha_valid = true;
                error_log("Admin login proceeding without hCaptcha due to validation error");
            }
        }
        
        if ($captcha_valid) {
            try {
                $pdo = getDbConnection();
                
                // Create users table if doesn't exist
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
                
                // Check if admin exists, create if not
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 1 AND is_active = 1");
                $stmt->execute();
                if ($stmt->fetchColumn() == 0) {
                    // Create default admin (only if no admin exists)
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, 1)");
                    $stmt->execute([
                        'Administrator',
                        'admin@example.com',
                        password_hash('admin123', PASSWORD_DEFAULT)
                    ]);
                }
                
                // Try login
                $stmt = $pdo->prepare("
                    SELECT id, name, email, password, is_admin, is_active, login_attempts, locked_until 
                    FROM users 
                    WHERE email = ? AND is_active = 1
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $message = 'Invalid credentials. Please try again.';
                    $error = true;
                    logFailedAttempt($pdo, $email, 'user_not_found');
                } elseif (!$user['is_admin']) {
                    $message = 'Access denied. Administrator privileges required.';
                    $error = true;
                    logFailedAttempt($pdo, $email, 'not_admin');
                } elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $unlock_time = date('H:i', strtotime($user['locked_until']));
                    $message = "Account temporarily locked until $unlock_time due to multiple failed login attempts.";
                    $error = true;
                } elseif (!password_verify($password, $user['password'])) {
                    // Increment failed attempts
                    incrementFailedAttempts($pdo, $user['id']);
                    $message = 'Invalid credentials. Please try again.';
                    $error = true;
                    logFailedAttempt($pdo, $email, 'invalid_password');
                } else {
                    // Success! Create admin session
                    session_regenerate_id(true);
                    
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_name'] = $user['name'];
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['admin_login_time'] = time();
                    
                    // Set remember me cookie if requested
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $expires = time() + (30 * 24 * 60 * 60); // 30 days
                        setcookie('remember_admin_token', $token, $expires, '/', '', isset($_SERVER['HTTPS']), true);
                        $_SESSION['remember_token'] = $token;
                    }
                    
                    // Update last login
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);
                    
                    // Log successful login
                    logSuccessfulLogin($pdo, $user['id'], $email);
                    
                    // Redirect to dashboard or intended page
                    $redirect_url = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    header("Location: $redirect_url");
                    exit;
                }
                
            } catch (Exception $e) {
                $message = 'A system error occurred. Please try again later.';
                $error = true;
                error_log('Admin login error: ' . $e->getMessage());
            }
        }
    }
}

/**
 * Helper functions
 */
function incrementFailedAttempts($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Check if account should be locked (5 attempts = 15 min lock)
        $stmt = $pdo->prepare("SELECT login_attempts FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $attempts = $stmt->fetchColumn();
        
        if ($attempts >= 5) {
            $locked_until = date('Y-m-d H:i:s', time() + 900); // 15 minutes
            $stmt = $pdo->prepare("UPDATE users SET locked_until = ? WHERE id = ?");
            $stmt->execute([$locked_until, $user_id]);
        }
    } catch (Exception $e) {
        error_log('Error incrementing failed attempts: ' . $e->getMessage());
    }
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
        $stmt->execute([
            $email,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $reason
        ]);
    } catch (Exception $e) {
        error_log('Error logging failed attempt: ' . $e->getMessage());
    }
}

function logSuccessfulLogin($pdo, $user_id, $email) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (email, ip_address, user_agent, success, reason)
            VALUES (?, ?, ?, 1, 'successful_admin_login')
        ");
        $stmt->execute([
            $email,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log('Error logging successful login: ' . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <?php if ($hcaptcha_enabled): ?>
    <!-- hCaptcha API Script -->
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    
    <!-- hCaptcha Module CSS -->
    <?php if (file_exists('../modules/hcaptcha/assets/hcaptcha.css')): ?>
    <link href="../modules/hcaptcha/assets/hcaptcha.css" rel="stylesheet">
    <?php endif; ?>
    <?php endif; ?>
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }
        
        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: -2s;
        }
        
        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: -8s;
        }
        
        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: -15s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }
        
        .login-footer {
            text-align: center;
            padding: 1rem 2rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        
        .security-info {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 1rem;
        }
        
        /* Security status indicator */
        .security-status {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-top: 0.5rem;
        }
        
        .security-status.enabled {
            color: #28a745;
        }
        
        .security-status.disabled {
            color: #6c757d;
        }
        
        /* hCaptcha specific styling */
        .hcaptcha-container {
            margin: 1.5rem 0;
            text-align: center;
        }
        
        .hcaptcha-container .h-captcha {
            display: inline-block;
        }
        
        /* Responsive hCaptcha */
        @media (max-width: 480px) {
            .hcaptcha-container .h-captcha {
                transform: scale(0.85);
                transform-origin: center;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Background Shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card login-card">
                    <!-- Header -->
                    <div class="login-header">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h3 class="mb-0">Admin Panel</h3>
                        <p class="mb-0 opacity-75"><?php echo SITE_NAME; ?></p>
                        
                        <!-- Security Status Indicator -->
                        <div class="security-status <?php echo $hcaptcha_enabled ? 'enabled' : 'disabled'; ?>">
                            <i class="fas fa-shield-<?php echo $hcaptcha_enabled ? 'alt' : 'halved'; ?> me-1"></i>
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
                    
                    <!-- Body -->
                    <div class="login-body">
                        <!-- Alerts -->
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $error ? 'danger' : 'success'; ?> alert-dismissible fade show">
                                <i class="fas fa-<?php echo $error ? 'exclamation-triangle' : 'check-circle'; ?> me-2"></i>
                                <?php if ($error): ?>
                                    <strong>Error:</strong>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Login Form -->
                        <form method="POST" class="needs-validation" novalidate id="adminLoginForm">
                            <!-- CSRF Token -->
                            <input type="hidden" name="token" value="<?php echo getToken(); ?>">
                            
                            <!-- Email Field -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-envelope me-1"></i>Email Address
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="email" 
                                           class="form-control" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           placeholder="Enter your email address"
                                           required
                                           autocomplete="email">
                                    <div class="invalid-feedback">
                                        Please enter a valid email address.
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Password Field -->
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-lock me-1"></i>Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-key"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           name="password" 
                                           placeholder="Enter your password"
                                           required
                                           autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            onclick="togglePassword()"
                                            id="passwordToggle">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </button>
                                    <div class="invalid-feedback">
                                        Please enter your password.
                                    </div>
                                </div>
                            </div>
                            
                            <!-- hCaptcha Widget (only if enabled) -->
                            <?php if ($hcaptcha_enabled && $hcaptcha): ?>
                            <div class="hcaptcha-container mb-4">
                                <?php 
                                try {
                                    echo $hcaptcha->renderWidget([
                                        'theme' => 'light',
                                        'size' => 'normal'
                                    ]);
                                } catch (Exception $e) {
                                    echo '<div class="alert alert-warning"><small><i class="fas fa-exclamation-triangle me-1"></i>Security verification temporarily unavailable</small></div>';
                                    error_log("hCaptcha render error: " . $e->getMessage());
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Remember Me -->
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="remember_me" 
                                           value="1"
                                           id="rememberMe">
                                    <label class="form-check-label" for="rememberMe">
                                        <i class="fas fa-clock me-1"></i>Remember me for 30 days
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Login Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In to Admin Panel
                                </button>
                            </div>
                        </form>
                        
                        <!-- Security Info -->
                        <div class="security-info text-center">
                            <i class="fas fa-shield-alt me-1"></i>
                            <small>This is a secure area. All access attempts are logged.</small>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="login-footer">
                        <small class="text-muted">
                            <i class="fas fa-home me-1"></i>
                            <a href="../" class="text-decoration-none">Return to Website</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($hcaptcha_enabled && file_exists('../modules/hcaptcha/assets/hcaptcha.js')): ?>
    <!-- hCaptcha Module JS -->
    <script src="../modules/hcaptcha/assets/hcaptcha.js"></script>
    <?php endif; ?>
    
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        
                        <?php if ($hcaptcha_enabled): ?>
                        // hCaptcha validation
                        const hcaptchaResponse = document.querySelector('[name="h-captcha-response"]');
                        if (hcaptchaResponse && !hcaptchaResponse.value) {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Please complete the security verification before submitting.');
                            return false;
                        }
                        <?php endif; ?>
                        
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        // Password toggle
        function togglePassword() {
            const passwordInput = document.querySelector('input[name="password"]');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }
        
        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput) {
                emailInput.focus();
            }
        });
        
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        console.log('PhPstrap Admin Login loaded successfully');
        console.log('hCaptcha status: <?php echo $hcaptcha_enabled ? 'enabled' : 'disabled'; ?>');
    </script>
</body>
</html>