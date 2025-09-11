<?php
// Include existing PhPstrap configuration
require_once '../config/app.php';
require_once '../config/functions.php';

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
$hcaptcha_status = checkHCaptchaStatus($pdo);
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
// Session Management
// ============================================

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if already logged in
try {
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        redirect('../dashboard/');
    }
} catch (Exception $e) {
    // Continue to login page if redirect fails
}

$error_message = '';
$success_message = '';

// ============================================
// Form Processing
// ============================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = "Email and password are required.";
    } else {
        // Validate hCaptcha only if enabled and configured
        $captcha_valid = true;
        if ($hcaptcha_enabled && $hcaptcha) {
            try {
                $captcha_result = $hcaptcha->validateCaptcha();
                if (!$captcha_result['success']) {
                    $captcha_valid = false;
                    $error_message = $captcha_result['message'];
                }
            } catch (Exception $e) {
                error_log("hCaptcha validation error: " . $e->getMessage());
                // Fail gracefully - allow login without hCaptcha if there's an error
                $captcha_valid = true;
                error_log("Login proceeding without hCaptcha due to validation error");
            }
        }
        
        if ($captcha_valid) {
            try {
                // Get user from database
                $stmt = $pdo->prepare("SELECT id, name, email, password, verified, membership_status, credits, is_admin FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                
                if ($stmt->rowCount() === 1) {
                    $user = $stmt->fetch();
                    
                    // Check if email verification is required
                    $email_verification_required = '1';
                    if (function_exists('getSetting')) {
                        $email_verification_required = getSetting('email_verification_required', '1');
                    }
                    
                    if ($email_verification_required == '1' && $user['verified'] == 0) {
                        $error_message = "Please verify your email before logging in.";
                        $_SESSION['unverified_email'] = $email;
                    } else {
                        // Verify password
                        if (password_verify($password, $user['password'])) {
                            // Successful login
                            $_SESSION['loggedin'] = true;
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['name'] = $user['name'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['membership_status'] = $user['membership_status'];
                            $_SESSION['credits'] = $user['credits'];
                            $_SESSION['is_admin'] = $user['is_admin'];
                            
                            // Update last login
                            if (function_exists('getUserIP')) {
                                $user_ip = getUserIP();
                            } else {
                                $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                            }
                            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                            
                            $update_stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ?, login_attempts = 0 WHERE id = ?");
                            $update_stmt->execute([$user_ip, $user['id']]);
                            
                            // Log successful login
                            if (function_exists('logActivity')) {
                                logActivity($email, $user_ip, $user_agent, true, 'successful_login');
                            }
                            
                            // Redirect to dashboard or admin
                            if ($user['is_admin']) {
                                redirect('../admin/');
                            } else {
                                redirect('../dashboard/');
                            }
                        } else {
                            if (function_exists('logActivity')) {
                                $user_ip = function_exists('getUserIP') ? getUserIP() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                                logActivity($email, $user_ip, $_SERVER['HTTP_USER_AGENT'] ?? '', false, 'invalid_password');
                            }
                            $error_message = "Invalid email or password.";
                        }
                    }
                } else {
                    if (function_exists('logActivity')) {
                        $user_ip = function_exists('getUserIP') ? getUserIP() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                        logActivity($email, $user_ip, $_SERVER['HTTP_USER_AGENT'] ?? '', false, 'user_not_found');
                    }
                    $error_message = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                if (function_exists('logError')) {
                    logError("Database error in login: " . $e->getMessage());
                }
                $error_message = "An error occurred. Please try again later.";
            }
        }
    }
}

// Check for success/error messages from other pages
if (isset($_SESSION['logout_success'])) {
    $success_message = $_SESSION['logout_success'];
    unset($_SESSION['logout_success']);
}

if (isset($_SESSION['verification_success'])) {
    $success_message = $_SESSION['verification_success'];
    unset($_SESSION['verification_success']);
}

if (isset($_SESSION['verification_error'])) {
    $error_message = $_SESSION['verification_error'];
    unset($_SESSION['verification_error']);
}

// Get constants for styling - with fallbacks
$site_name = defined('SITE_NAME') ? SITE_NAME : 'PhPstrap';
$theme_color = defined('THEME_COLOR') ? THEME_COLOR : '#007bff';
$secondary_color = defined('SECONDARY_COLOR') ? SECONDARY_COLOR : '#6c757d';
$site_icon = defined('SITE_ICON') ? SITE_ICON : 'fas fa-home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($site_name); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
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
        :root {
            --theme-color: <?php echo $theme_color; ?>;
            --secondary-color: <?php echo $secondary_color; ?>;
        }
        
        body {
            background: linear-gradient(135deg, var(--theme-color) 0%, #6f42c1 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }
        
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }
        
        .btn-primary {
            background-color: var(--theme-color);
            border-color: var(--theme-color);
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: var(--theme-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
        }
        
        .feature-card {
            transition: transform 0.2s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .alert {
            border-radius: 0.5rem;
        }
        
        .input-group .input-group-text {
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }
        
        .form-control:focus {
            border-color: var(--theme-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .resend-verification {
            background: rgba(255, 193, 7, 0.1);
            border-left: 4px solid #ffc107;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
        }
        
        /* hCaptcha specific styling */
        .hcaptcha-container {
            margin: 1.5rem 0;
            text-align: center;
        }
        
        .hcaptcha-container .h-captcha {
            display: inline-block;
        }
        
        /* Security status indicator */
        .security-status {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.5rem;
        }
        
        .security-status.enabled {
            color: #28a745;
        }
        
        .security-status.disabled {
            color: #6c757d;
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
    <div class="container login-container">
        <div class="row justify-content-center w-100">
            <div class="col-md-6 col-lg-5">
                
                <!-- Login Form -->
                <div class="card shadow-lg">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="logo">
                                <i class="<?php echo $site_icon; ?>"></i>
                            </div>
                            <h2 class="h3 mb-0">Login</h2>
                            <p class="text-muted">Welcome to <?php echo htmlspecialchars($site_name); ?></p>
                            
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

                        <!-- Success Messages -->
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Error Messages -->
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            
                            <!-- Simple resend verification notice -->
                            <?php if (strpos($error_message, 'verify your email') !== false): ?>
                                <div class="resend-verification">
                                    <div class="text-center">
                                        <strong><i class="fas fa-envelope me-2"></i>Need a new verification email?</strong>
                                        <br>
                                        <small class="text-muted">Please contact support or check your email for the verification link.</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Login Form -->
                        <form action="" method="POST" id="loginForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           required autocomplete="email" placeholder="Enter your email">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required autocomplete="current-password" placeholder="Enter your password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
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

                            <div class="d-grid gap-2 mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </div>
                        </form>

                        <!-- Additional Links -->
                        <div class="row text-center pt-3 border-top">
                            <div class="col-6">
                                <a href="forgot-password.php" class="text-decoration-none small">
                                    <i class="fas fa-key me-1"></i>Forgot Password?
                                </a>
                            </div>
                            <div class="col-6">
                                <?php 
                                $registration_enabled = '1';
                                if (function_exists('getSetting')) {
                                    $registration_enabled = getSetting('registration_enabled', '1');
                                }
                                if ($registration_enabled == '1'): 
                                ?>
                                <a href="register.php" class="text-decoration-none small">
                                    <i class="fas fa-user-plus me-1"></i>Create Account
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- New User Options (only show if registration is enabled) -->
                <?php if ($registration_enabled == '1'): ?>
                <div class="mt-4">
                    <div class="text-center mb-4">
                        <h3 class="h5 fw-bold text-white">
                            <i class="fas fa-user-plus me-2"></i>New to <?php echo htmlspecialchars($site_name); ?>?
                        </h3>
                        <p class="text-white-50">Choose how you'd like to get started</p>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card h-100 feature-card">
                                <div class="card-body p-4 text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-gift fa-3x text-success"></i>
                                    </div>
                                    <h4 class="h6 fw-bold mb-3">Start Free</h4>
                                    <p class="text-muted small mb-3">Create your account and explore our features.</p>
                                    <a href="../register.php" class="btn btn-success btn-sm rounded-pill">
                                        <i class="fas fa-user-plus me-1"></i> Create Free Account
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100 feature-card">
                                <div class="card-body p-4 text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-crown fa-3x text-primary"></i>
                                    </div>
                                    <h4 class="h6 fw-bold mb-3">Go Premium</h4>
                                    <p class="text-muted small mb-3">Unlock all premium features instantly.</p>
                                    <a href="../pricing/" class="btn btn-primary btn-sm rounded-pill">
                                        <i class="fas fa-crown me-1"></i> View Plans
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($hcaptcha_enabled && file_exists('../modules/hcaptcha/assets/hcaptcha.js')): ?>
    <!-- hCaptcha Module JS -->
    <script src="../modules/hcaptcha/assets/hcaptcha.js"></script>
    <?php endif; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                if (type === 'password') {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                } else {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            });
        }

        // Auto-focus email field
        const emailInput = document.getElementById('email');
        if (emailInput && emailInput.value === '') {
            emailInput.focus();
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // hCaptcha form submission validation (only if hCaptcha is enabled)
        <?php if ($hcaptcha_enabled): ?>
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const hcaptchaResponse = document.querySelector('[name="h-captcha-response"]');
                if (hcaptchaResponse && !hcaptchaResponse.value) {
                    e.preventDefault();
                    alert('Please complete the security verification before submitting.');
                    return false;
                }
            });
        }
        <?php endif; ?>

        console.log('PhPstrap Login page loaded successfully');
        console.log('hCaptcha status: <?php echo $hcaptcha_enabled ? 'enabled' : 'disabled'; ?>');
    });
    </script>
</body>
</html>