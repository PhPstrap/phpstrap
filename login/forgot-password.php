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

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if already logged in
try {
    if (function_exists('isLoggedIn') && isLoggedIn()) {
        redirect('../dashboard/');
    }
} catch (Exception $e) {
    // Continue to forgot password page
}

$error_message = '';
$success_message = '';

// ============================================
// Form Processing
// ============================================

// Handle forgot password form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error_message = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Validate hCaptcha if enabled and configured
        $captcha_valid = true;
        if ($hcaptcha_enabled && $hcaptcha) {
            try {
                $captcha_result = $hcaptcha->validateCaptcha();
                if (!$captcha_result['success']) {
                    $captcha_valid = false;
                    $error_message = $captcha_result['message'];
                }
            } catch (Exception $e) {
                error_log("hCaptcha validation error in forgot password: " . $e->getMessage());
                // Fail gracefully - allow password reset without hCaptcha if there's an error
                $captcha_valid = true;
                error_log("Password reset proceeding without hCaptcha due to validation error");
            }
        }
        
        if ($captcha_valid) {
            try {
                // Check if user exists and is active
                $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                
                if ($stmt->rowCount() === 1) {
                    $user = $stmt->fetch();
                    
                    // Check for recent password reset requests (rate limiting)
                    $rate_limit_check = $pdo->prepare("
                        SELECT COUNT(*) as recent_requests 
                        FROM password_resets 
                        WHERE user_id = ? 
                        AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    ");
                    $rate_limit_check->execute([$user['id']]);
                    $recent_requests = $rate_limit_check->fetch()['recent_requests'];
                    
                    if ($recent_requests >= 3) {
                        $error_message = "Too many password reset requests. Please wait 15 minutes before trying again.";
                    } else {
                        // Generate secure reset token
                        $reset_token = bin2hex(random_bytes(32)); // 64 character hex string
                        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
                        
                        // Get user IP and user agent
                        if (function_exists('getUserIP')) {
                            $user_ip = getUserIP();
                        } else {
                            $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        }
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        
                        // Insert password reset token
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO password_resets (user_id, token, expires_at, ip_address, user_agent, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $insert_stmt->execute([$user['id'], $reset_token, $expires_at, $user_ip, $user_agent]);
                        
                        // Create reset link
                        $site_url = function_exists('getSetting') ? getSetting('site_url', '') : '';
                        if (empty($site_url)) {
                            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                            $site_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
                        }
                        $reset_link = rtrim($site_url, '/') . '/login/reset-password.php?token=' . $reset_token;
                        
                        // Send password reset email using your SMTP module
                        $email_sent = sendPasswordResetEmailViaSMTP($user['email'], $user['name'], $reset_link, $reset_token);
                        
                        if ($email_sent) {
                            // Log the password reset request
                            if (function_exists('logActivity')) {
                                logActivity($email, $user_ip, $user_agent, true, 'password_reset_requested');
                            }
                            
                            $success_message = "Password reset instructions have been sent to your email address. Please check your inbox and follow the instructions to reset your password.";
                            
                            // Clear the email field on success
                            unset($_POST['email']);
                            
                        } else {
                            $error_message = "Failed to send password reset email. Please try again later or contact support.";
                        }
                    }
                } else {
                    // Don't reveal whether email exists or not for security
                    $success_message = "If an account with that email exists, password reset instructions have been sent.";
                    
                    // Log the failed attempt
                    if (function_exists('logActivity')) {
                        $user_ip = function_exists('getUserIP') ? getUserIP() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                        logActivity($email, $user_ip, $_SERVER['HTTP_USER_AGENT'] ?? '', false, 'password_reset_invalid_email');
                    }
                }
                
            } catch (PDOException $e) {
                if (function_exists('logError')) {
                    logError("Database error in forgot password: " . $e->getMessage());
                }
                $error_message = "An error occurred. Please try again later.";
            }
        }
    }
}

/**
 * Send password reset email using your existing SMTP module
 */
function sendPasswordResetEmailViaSMTP($to_email, $user_name, $reset_link, $token) {
    try {
        if (function_exists('logError')) {
            logError("Starting password reset email for: " . $to_email);
        }
        
        // Load your SMTP module
        if (!class_exists('PhPstrap\Modules\SMTPMailer\SMTPMailer')) {
            if (file_exists('../modules/smtp_mailer/SMTPMailer.php')) {
                require_once '../modules/smtp_mailer/SMTPMailer.php';
            } elseif (file_exists('modules/smtp_mailer/SMTPMailer.php')) {
                require_once 'modules/smtp_mailer/SMTPMailer.php';
            } else {
                if (function_exists('logError')) {
                    logError("SMTP module not found at expected paths");
                }
                return false;
            }
        }
        
        // Create SMTP instance
        $smtp = new PhPstrap\Modules\SMTPMailer\SMTPMailer();
        if (function_exists('logError')) {
            logError("SMTP instance created successfully");
        }
        
        $mailer = $smtp->createMailer();
        if (function_exists('logError')) {
            logError("PHPMailer instance created successfully");
        }
        
        // Get site settings
        $site_name = function_exists('getSetting') ? getSetting('site_name', 'PhPstrap') : 'PhPstrap';
        
        // Email subject
        $subject = "Password Reset Request - " . $site_name;
        
        // Create HTML email content
        $html_content = createPasswordResetEmailTemplate($user_name, $reset_link, $site_name, $token);
        
        // Set up the email
        $mailer->addAddress($to_email, $user_name);
        $mailer->Subject = $subject;
        $mailer->Body = $html_content;
        $mailer->isHTML(true);
        
        // Add plain text alternative
        $plain_content = createPasswordResetPlainText($user_name, $reset_link, $site_name);
        $mailer->AltBody = $plain_content;
        
        // Send the email
        $result = $mailer->send();
        
        if ($result) {
            if (function_exists('logError')) {
                logError("Password reset email sent successfully to: " . $to_email);
            }
        } else {
            if (function_exists('logError')) {
                logError("Failed to send password reset email to: " . $to_email . " - " . $mailer->ErrorInfo);
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError("Password reset email error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        }
        return false;
    }
}

/**
 * Create HTML email template for password reset
 */
function createPasswordResetEmailTemplate($user_name, $reset_link, $site_name, $token) {
    $theme_color = function_exists('getSetting') ? getSetting('theme_color', '#007bff') : '#007bff';
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset - ' . htmlspecialchars($site_name) . '</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1);">
            <div style="background: ' . $theme_color . '; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0; font-size: 28px;">Password Reset Request</h1>
                <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;">' . htmlspecialchars($site_name) . '</p>
            </div>
            
            <div style="padding: 40px 30px;">
                <p style="font-size: 18px; margin-bottom: 20px;">Hello ' . htmlspecialchars($user_name) . ',</p>
                
                <p style="font-size: 16px; margin-bottom: 20px;">
                    We received a request to reset your password for your ' . htmlspecialchars($site_name) . ' account. 
                    If you made this request, please click the button below to reset your password:
                </p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . htmlspecialchars($reset_link) . '" 
                       style="display: inline-block; background: ' . $theme_color . '; color: white; padding: 15px 30px; 
                              text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;">
                        Reset My Password
                    </a>
                </div>
                
                <p style="font-size: 14px; color: #666; margin-bottom: 15px;">
                    Or copy and paste this link into your browser:
                </p>
                <p style="font-size: 14px; color: #666; word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px;">
                    ' . htmlspecialchars($reset_link) . '
                </p>
                
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0; font-size: 14px; color: #856404;">
                        <strong>Important:</strong> This link will expire in 1 hour for security reasons.
                        If you did not request this password reset, please ignore this email or contact support if you have concerns.
                    </p>
                </div>
                
                <p style="font-size: 14px; color: #666; margin-top: 30px;">
                    Best regards,<br>
                    The ' . htmlspecialchars($site_name) . ' Team
                </p>
            </div>
            
            <div style="background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px; color: #666;">
                <p style="margin: 0;">
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Create plain text version for email clients that don't support HTML
 */
function createPasswordResetPlainText($user_name, $reset_link, $site_name) {
    return "Password Reset Request - " . $site_name . "\n\n" .
           "Hello " . $user_name . ",\n\n" .
           "We received a request to reset your password for your " . $site_name . " account.\n\n" .
           "Please click the following link to reset your password:\n" .
           $reset_link . "\n\n" .
           "This link will expire in 1 hour for security reasons.\n\n" .
           "If you did not request this password reset, please ignore this email.\n\n" .
           "Best regards,\n" .
           "The " . $site_name . " Team";
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
    <title>Forgot Password - <?php echo htmlspecialchars($site_name); ?></title>
    
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
        
        .info-box {
            background: rgba(13, 202, 240, 0.1);
            border-left: 4px solid #0dcaf0;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
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
    <div class="container login-container">
        <div class="row justify-content-center w-100">
            <div class="col-md-6 col-lg-5">
                
                <!-- Forgot Password Form -->
                <div class="card shadow-lg">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="logo">
                                <i class="fas fa-key"></i>
                            </div>
                            <h2 class="h3 mb-0">Forgot Password</h2>
                            <p class="text-muted">Enter your email to reset your password</p>
                            
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

                        <!-- Info Box -->
                        <div class="info-box">
                            <small>
                                <i class="fas fa-info-circle me-2"></i>
                                Enter your email address and we'll send you a link to reset your password. 
                                The reset link will expire in 1 hour for security.
                            </small>
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
                        <?php endif; ?>

                        <!-- Forgot Password Form -->
                        <form action="" method="POST" id="forgotPasswordForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           required autocomplete="email" placeholder="Enter your email address">
                                </div>
                                <div class="form-text">
                                    We'll send password reset instructions to this email address.
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
                                    <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                                </button>
                            </div>
                        </form>

                        <!-- Back to Login -->
                        <div class="text-center pt-3 border-top">
                            <a href="./" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-2"></i>Back to Login
                            </a>
                        </div>

                    </div>
                </div>

                <!-- Additional Help -->
                <div class="mt-4 text-center">
                    <div class="card">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-2">
                                <i class="fas fa-question-circle me-2"></i>Need Help?
                            </h6>
                            <p class="card-text small text-muted mb-2">
                                If you're having trouble receiving the email, check your spam folder or contact support.
                            </p>
                            <a href="#" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-life-ring me-1"></i>Contact Support
                            </a>
                        </div>
                    </div>
                </div>

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
        // Auto-focus email field
        const emailInput = document.getElementById('email');
        if (emailInput && emailInput.value === '') {
            emailInput.focus();
        }

        // Auto-hide alerts after 10 seconds (longer for password reset messages)
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 10000);

        // Form submission validation and feedback
        const form = document.getElementById('forgotPasswordForm');
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            
            <?php if ($hcaptcha_enabled): ?>
            // hCaptcha validation
            const hcaptchaResponse = document.querySelector('[name="h-captcha-response"]');
            if (hcaptchaResponse && !hcaptchaResponse.value) {
                e.preventDefault();
                alert('Please complete the security verification before submitting.');
                return;
            }
            <?php endif; ?>
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            submitBtn.disabled = true;
            
            // Re-enable after 8 seconds in case of issues
            setTimeout(() => {
                submitBtn.innerHTML = originalHTML;
                submitBtn.disabled = false;
            }, 8000);
        });

        console.log('PhPstrap Forgot Password page loaded successfully');
        console.log('hCaptcha status: <?php echo $hcaptcha_enabled ? 'enabled' : 'disabled'; ?>');
    });
    </script>
</body>
</html>