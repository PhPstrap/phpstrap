<?php
// Include existing PhPstrap configuration
require_once '../config/app.php';
require_once '../config/functions.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$verification_status = '';
$error_message = '';
$success_message = '';
$user_data = null;

// Get token from URL
$verification_token = $_GET['token'] ?? '';

if (empty($verification_token)) {
    $error_message = "Invalid verification link. Please check your email and try again.";
} else {
    try {
        // Look up user by verification token
        $stmt = $pdo->prepare("
            SELECT id, name, email, verified, verification_token, 
                   last_verification_sent_at, created_at 
            FROM users 
            WHERE verification_token = ? 
            AND verification_token IS NOT NULL
        ");
        $stmt->execute([$verification_token]);
        
        if ($stmt->rowCount() === 0) {
            $error_message = "Invalid or expired verification token. Please contact support if you continue to have issues.";
        } else {
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if user is already verified
            if ($user_data['verified'] == 1) {
                $verification_status = 'already_verified';
                $success_message = "Your email address is already verified! You can now log in to your account.";
            } else {
                // Check if token has expired (24 hours)
                $token_age = time() - strtotime($user_data['last_verification_sent_at']);
                $token_expiry = 24 * 60 * 60; // 24 hours in seconds
                
                if (function_exists('getSetting')) {
                    $token_expiry = (int)getSetting('verification_token_expiry', 24) * 60 * 60;
                }
                
                if ($token_age > $token_expiry) {
                    $verification_status = 'expired';
                    $error_message = "This verification link has expired. Please request a new verification email.";
                } else {
                    // Begin transaction to verify user
                    $pdo->beginTransaction();
                    
                    try {
                        // Update user verification status using your exact table structure
                        $update_stmt = $pdo->prepare("
                            UPDATE users 
                            SET verified = 1, 
                                verification_token = NULL, 
                                verified_at = NOW(),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$user_data['id']]);
                        
                        // Log the verification
                        if (function_exists('getUserIP')) {
                            $user_ip = getUserIP();
                        } else {
                            $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        }
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        
                        if (function_exists('logActivity')) {
                            logActivity($user_data['email'], $user_ip, $user_agent, true, 'email_verified');
                        }
                        
                        // Award verification bonus credits if enabled
                        if (function_exists('getSetting')) {
                            $verification_credits = getSetting('verification_bonus_credits', '0.00');
                            if ($verification_credits > 0) {
                                try {
                                    $credit_stmt = $pdo->prepare("
                                        UPDATE users 
                                        SET credits = credits + ? 
                                        WHERE id = ?
                                    ");
                                    $credit_stmt->execute([$verification_credits, $user_data['id']]);
                                } catch (Exception $credit_error) {
                                    // Credits update failed, but continue with verification
                                    if (function_exists('logError')) {
                                        logError("Failed to award verification credits: " . $credit_error->getMessage());
                                    }
                                }
                            }
                        }
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        $verification_status = 'success';
                        $success_message = "Email verification successful! Your account is now active and you can log in.";
                        
                        // Auto-login option (if enabled in settings)
                        $auto_login_after_verification = '0';
                        if (function_exists('getSetting')) {
                            $auto_login_after_verification = getSetting('auto_login_after_verification', '0');
                        }
                        
                        if ($auto_login_after_verification == '1') {
                            try {
                                // Get updated user data for session
                                $user_stmt = $pdo->prepare("
                                    SELECT id, name, email, membership_status, credits, is_admin 
                                    FROM users 
                                    WHERE id = ?
                                ");
                                $user_stmt->execute([$user_data['id']]);
                                $verified_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($verified_user) {
                                    // Set session variables
                                    $_SESSION['loggedin'] = true;
                                    $_SESSION['user_id'] = $verified_user['id'];
                                    $_SESSION['name'] = $verified_user['name'];
                                    $_SESSION['email'] = $verified_user['email'];
                                    $_SESSION['membership_status'] = $verified_user['membership_status'];
                                    $_SESSION['credits'] = $verified_user['credits'];
                                    $_SESSION['is_admin'] = $verified_user['is_admin'];
                                    
                                    $_SESSION['verification_success'] = "Welcome! Your email has been verified and you're now logged in.";
                                    
                                    // Redirect to dashboard after a short delay
                                    echo '<script>
                                        setTimeout(function() {
                                            window.location.href = "../dashboard/";
                                        }, 3000);
                                    </script>';
                                }
                            } catch (Exception $login_error) {
                                // Auto-login failed, but verification was successful
                                if (function_exists('logError')) {
                                    logError("Auto-login after verification failed: " . $login_error->getMessage());
                                }
                                // Don't change the success message as verification still worked
                            }
                        }
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        if (function_exists('logError')) {
                            logError("Email verification error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
                        }
                        $error_message = "Database error during verification: " . $e->getMessage() . ". Please contact support.";
                    }
                }
            }
        }
        
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError("Database error in email verification: " . $e->getMessage());
        }
        $error_message = "A technical error occurred. Please try again later.";
    }
}

/**
 * Send verification email using SMTP module
 */
function sendVerificationEmail($to_email, $user_name, $verification_token) {
    try {
        // Get the document root to build proper paths
        $doc_root = $_SERVER['DOCUMENT_ROOT'];
        $current_dir = dirname(__FILE__);
        
        // Possible paths for the SMTP module (check multiple locations)
        $possible_paths = [
            $current_dir . '/../modules/smtp_mailer/SMTPMailer.php',
            $doc_root . '/modules/smtp_mailer/SMTPMailer.php',
            dirname($current_dir) . '/modules/smtp_mailer/SMTPMailer.php',
            '../modules/smtp_mailer/SMTPMailer.php',
            '../../modules/smtp_mailer/SMTPMailer.php'
        ];
        
        $smtp_file_found = false;
        $smtp_file_path = null;
        
        // Try to find the SMTP module file
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $smtp_file_path = $path;
                $smtp_file_found = true;
                break;
            }
        }
        
        if (!$smtp_file_found) {
            if (function_exists('logError')) {
                logError("SMTP module file not found. Searched paths: " . implode(', ', $possible_paths));
            }
            return false;
        }
        
        // Try to load the SMTP module
        if (!class_exists('PhPstrap\Modules\SMTPMailer\SMTPMailer')) {
            require_once $smtp_file_path;
            
            // If the class still doesn't exist, try different class names
            if (!class_exists('PhPstrap\Modules\SMTPMailer\SMTPMailer')) {
                // Try alternative class names
                $alternative_classes = [
                    'SMTPMailer',
                    'PhPstrap\SMTPMailer',
                    'Modules\SMTPMailer\SMTPMailer',
                    'PhPstrap\Modules\SMTPMailer'
                ];
                
                $class_found = false;
                foreach ($alternative_classes as $class_name) {
                    if (class_exists($class_name)) {
                        $smtp_class = $class_name;
                        $class_found = true;
                        break;
                    }
                }
                
                if (!$class_found) {
                    if (function_exists('logError')) {
                        logError("SMTP class not found after loading file: " . $smtp_file_path);
                    }
                    return false;
                }
            } else {
                $smtp_class = 'PhPstrap\Modules\SMTPMailer\SMTPMailer';
            }
        } else {
            $smtp_class = 'PhPstrap\Modules\SMTPMailer\SMTPMailer';
        }
        
        // Create SMTP instance
        $smtp = new $smtp_class();
        
        // Check if createMailer method exists
        if (!method_exists($smtp, 'createMailer')) {
            if (function_exists('logError')) {
                logError("createMailer method not found in SMTP class: " . $smtp_class);
            }
            return false;
        }
        
        $mailer = $smtp->createMailer();
        
        if (!$mailer) {
            if (function_exists('logError')) {
                logError("Failed to create mailer instance");
            }
            return false;
        }
        
        // Get site settings
        $site_name = function_exists('getSetting') ? getSetting('site_name', 'PhPstrap') : 'PhPstrap';
        $site_url = function_exists('getSetting') ? getSetting('site_url', '') : '';
        
        // Ensure site_url has proper format
        if (empty($site_url)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $site_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
        }
        
        // Remove trailing slash
        $site_url = rtrim($site_url, '/');
        
        // Create verification link
        $verification_link = $site_url . '/login/verify.php?token=' . $verification_token;
        
        // Email subject
        $subject = "Verify Your Email - " . $site_name;
        
        // Create HTML email content
        $html_content = createVerificationEmailTemplate($user_name, $verification_link, $site_name);
        
        // Plain text version
        $plain_content = "Welcome to " . $site_name . "!\n\n" .
                       "Hello " . $user_name . ",\n\n" .
                       "Thank you for registering! Please verify your email address by clicking the link below:\n\n" .
                       $verification_link . "\n\n" .
                       "This link will expire in 24 hours.\n\n" .
                       "If you didn't create this account, please ignore this email.\n\n" .
                       "Best regards,\n" .
                       "The " . $site_name . " Team";
        
        // Set up the email
        $mailer->addAddress($to_email, $user_name);
        $mailer->Subject = $subject;
        $mailer->Body = $html_content;
        $mailer->isHTML(true);
        $mailer->AltBody = $plain_content;
        
        // Send the email
        $result = $mailer->send();
        
        if (function_exists('logError')) {
            if ($result) {
                logError("Verification email sent successfully to: " . $to_email);
            } else {
                logError("Failed to send verification email to: " . $to_email . " - " . $mailer->ErrorInfo);
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError("Verification email error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
        }
        return false;
    }
}

/**
 * Create HTML email template for verification
 */
function createVerificationEmailTemplate($user_name, $verification_link, $site_name) {
    $theme_color = function_exists('getSetting') ? getSetting('theme_color', '#007bff') : '#007bff';
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verification - ' . htmlspecialchars($site_name) . '</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1);">
            <div style="background: ' . $theme_color . '; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0; font-size: 28px;">Welcome to ' . htmlspecialchars($site_name) . '!</h1>
                <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;">Please verify your email address</p>
            </div>
            
            <div style="padding: 40px 30px;">
                <p style="font-size: 18px; margin-bottom: 20px;">Hello ' . htmlspecialchars($user_name) . ',</p>
                
                <p style="font-size: 16px; margin-bottom: 20px;">
                    Thank you for registering with ' . htmlspecialchars($site_name) . '! 
                    To complete your registration and start using your account, please verify your email address.
                </p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . htmlspecialchars($verification_link) . '" 
                       style="display: inline-block; background: ' . $theme_color . '; color: white; padding: 15px 30px; 
                              text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;">
                        Verify My Email Address
                    </a>
                </div>
                
                <p style="font-size: 14px; color: #666; margin-bottom: 15px;">
                    Or copy and paste this link into your browser:
                </p>
                <p style="font-size: 14px; color: #666; word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px;">
                    ' . htmlspecialchars($verification_link) . '
                </p>
                
                <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0; font-size: 14px; color: #155724;">
                        <strong>Note:</strong> This verification link will expire in 24 hours for security reasons.
                        If you didn\'t create this account, please ignore this email.
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

// Handle resend verification email request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resend_verification'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error_message = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            // Check if user exists and is not verified
            $stmt = $pdo->prepare("
                SELECT id, name, email, verified, last_verification_sent_at 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 0) {
                $error_message = "No account found with this email address.";
            } else {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user['verified'] == 1) {
                    $success_message = "This email address is already verified. You can log in to your account.";
                } else {
                    // Check rate limiting (prevent spam)
                    $last_sent = $user['last_verification_sent_at'];
                    $time_since_last = time() - strtotime($last_sent);
                    $rate_limit = 300; // 5 minutes default
                    
                    if (function_exists('getSetting')) {
                        $rate_limit = (int)getSetting('verification_resend_rate_limit', 300);
                    }
                    
                    if ($time_since_last < $rate_limit) {
                        $minutes_remaining = ceil(($rate_limit - $time_since_last) / 60);
                        $error_message = "Please wait {$minutes_remaining} minutes before requesting another verification email.";
                    } else {
                        // Generate new verification token
                        $new_token = bin2hex(random_bytes(32));
                        
                        // Update user with new token
                        $update_stmt = $pdo->prepare("
                            UPDATE users 
                            SET verification_token = ?, 
                                last_verification_sent_at = NOW(),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$new_token, $user['id']]);
                        
                        // Send new verification email
                        if (function_exists('sendVerificationEmail')) {
                            $email_sent = sendVerificationEmail($user['email'], $user['name'], $new_token);
                            
                            if ($email_sent) {
                                $success_message = "A new verification email has been sent to your email address. Please check your inbox and spam folder.";
                            } else {
                                $error_message = "Failed to send verification email. Please contact support.";
                            }
                        } else {
                            $error_message = "Email service is not configured. Please contact support.";
                        }
                    }
                }
            }
            
        } catch (PDOException $e) {
            if (function_exists('logError')) {
                logError("Resend verification error: " . $e->getMessage());
            }
            $error_message = "An error occurred. Please try again later.";
        }
    }
}

// Get constants for styling - with fallbacks
$site_name = defined('SITE_NAME') ? SITE_NAME : 'PhPstrap';
$theme_color = defined('THEME_COLOR') ? THEME_COLOR : '#007bff';
$secondary_color = defined('SECONDARY_COLOR') ? SECONDARY_COLOR : '#6c757d';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo htmlspecialchars($site_name); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
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
        
        .verify-container {
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
        
        .status-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 3rem;
            color: white;
        }
        
        .status-success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .status-error {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
        }
        
        .status-expired {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
        }
        
        .status-already {
            background: linear-gradient(135deg, #17a2b8, #6f42c1);
        }
        
        .alert {
            border-radius: 0.5rem;
        }
        
        .countdown {
            font-weight: bold;
            color: var(--theme-color);
        }
        
        .resend-form {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container verify-container">
        <div class="row justify-content-center w-100">
            <div class="col-md-6 col-lg-5">
                
                <div class="card shadow-lg">
                    <div class="card-body p-4 text-center">
                        
                        <!-- Success Status -->
                        <?php if ($verification_status === 'success'): ?>
                            <div class="status-icon status-success">
                                <i class="fas fa-check"></i>
                            </div>
                            <h2 class="h3 mb-3 text-success">Email Verified!</h2>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                            </div>
                            
                            <?php if (function_exists('getSetting') && getSetting('auto_login_after_verification', '0') == '1'): ?>
                                <p class="text-muted mb-3">
                                    <i class="fas fa-spinner fa-spin me-2"></i>
                                    Redirecting to your dashboard in <span class="countdown" id="countdown">3</span> seconds...
                                </p>
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 mb-3">
                                <a href="../dashboard/" class="btn btn-primary btn-lg">
                                    <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                                </a>
                            </div>
                            
                        <!-- Already Verified -->
                        <?php elseif ($verification_status === 'already_verified'): ?>
                            <div class="status-icon status-already">
                                <i class="fas fa-check-double"></i>
                            </div>
                            <h2 class="h3 mb-3 text-info">Already Verified</h2>
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                            </div>
                            
                        <!-- Expired Token -->
                        <?php elseif ($verification_status === 'expired'): ?>
                            <div class="status-icon status-expired">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h2 class="h3 mb-3 text-warning">Link Expired</h2>
                            <div class="alert alert-warning" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                            </div>
                            
                            <!-- Show resend form for expired tokens -->
                            <div class="resend-form">
                                <h5 class="mb-3">Request New Verification Email</h5>
                                <form action="" method="POST">
                                    <div class="mb-3">
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo $user_data ? htmlspecialchars($user_data['email']) : ''; ?>" 
                                                   placeholder="Enter your email address" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="resend_verification" class="btn btn-warning">
                                        <i class="fas fa-paper-plane me-2"></i>Send New Verification Email
                                    </button>
                                </form>
                            </div>
                            
                        <!-- Error Status -->
                        <?php else: ?>
                            <div class="status-icon status-error">
                                <i class="fas fa-times"></i>
                            </div>
                            <h2 class="h3 mb-3 text-danger">Verification Failed</h2>
                            
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Show resend form for general errors -->
                            <div class="resend-form">
                                <h5 class="mb-3">Need a New Verification Email?</h5>
                                <form action="" method="POST">
                                    <div class="mb-3">
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                            <input type="email" class="form-control" name="email" 
                                                   placeholder="Enter your email address" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="resend_verification" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Resend Verification Email
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Navigation Links -->
                        <div class="text-center pt-3 border-top mt-4">
                            <a href="./" class="text-decoration-none me-3">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                            <a href="register.php" class="text-decoration-none me-3">
                                <i class="fas fa-user-plus me-1"></i>Register
                            </a>
                            <a href="../" class="text-decoration-none">
                                <i class="fas fa-home me-1"></i>Home
                            </a>
                        </div>
                        
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Countdown timer for auto-redirect
        const countdownElement = document.getElementById('countdown');
        if (countdownElement) {
            let seconds = 3;
            const timer = setInterval(function() {
                seconds--;
                countdownElement.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(timer);
                }
            }, 1000);
        }
        
        // Auto-hide alerts after 10 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 10000);
        
        console.log('PhPstrap Email Verification page loaded successfully');
    });
    </script>
</body>
</html>