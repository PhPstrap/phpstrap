<?php
// Include existing BootPHP configuration
require_once '../config/app.php';
require_once '../config/functions.php';

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
    // Continue to reset password page
}

$error_message = '';
$success_message = '';
$token_valid = false;
$user_data = null;

// Check for token in URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error_message = "Invalid or missing reset token. Please request a new password reset.";
} else {
    // Validate token
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, u.id as user_id, u.name, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? 
            AND pr.used = 0 
            AND pr.expires_at > NOW() 
            AND u.is_active = 1
        ");
        $stmt->execute([$token]);
        
        if ($stmt->rowCount() === 1) {
            $token_valid = true;
            $user_data = $stmt->fetch();
        } else {
            $error_message = "Invalid or expired reset token. Please request a new password reset.";
        }
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError("Database error in reset password token validation: " . $e->getMessage());
        }
        $error_message = "An error occurred. Please try again later.";
    }
}

// Handle password reset form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid && $user_data) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error_message = "Both password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Validate password strength
        $password_validation = validatePassword($new_password);
        
        if (!$password_validation['valid']) {
            $error_message = $password_validation['message'];
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update user password
                $update_stmt = $pdo->prepare("
                    UPDATE users 
                    SET password = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $update_stmt->execute([$hashed_password, $user_data['user_id']]);
                
                // Mark the reset token as used
                $token_stmt = $pdo->prepare("
                    UPDATE password_resets 
                    SET used = 1, used_at = NOW() 
                    WHERE token = ?
                ");
                $token_stmt->execute([$token]);
                
                // Invalidate all other unused tokens for this user (security)
                $invalidate_stmt = $pdo->prepare("
                    UPDATE password_resets 
                    SET used = 1, used_at = NOW() 
                    WHERE user_id = ? AND used = 0
                ");
                $invalidate_stmt->execute([$user_data['user_id']]);
                
                // Commit transaction
                $pdo->commit();
                
                // Log the password reset
                if (function_exists('getUserIP')) {
                    $user_ip = getUserIP();
                } else {
                    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                }
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                if (function_exists('logActivity')) {
                    logActivity($user_data['email'], $user_ip, $user_agent, true, 'password_reset_completed');
                }
                
                // Set success message and redirect to login
                $_SESSION['password_reset_success'] = "Your password has been successfully reset! You can now log in with your new password.";
                redirect('./');
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                if (function_exists('logError')) {
                    logError("Database error in password reset: " . $e->getMessage());
                }
                $error_message = "An error occurred while resetting your password. Please try again.";
            }
        }
    }
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    $min_length = 8;
    if (function_exists('getSetting')) {
        $min_length = (int)getSetting('password_min_length', 8);
    }
    
    $errors = [];
    
    if (strlen($password) < $min_length) {
        $errors[] = "Password must be at least {$min_length} characters long";
    }
    
    // Check for additional password requirements if settings exist
    if (function_exists('getSetting')) {
        if (getSetting('password_require_uppercase', '0') == '1' && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (getSetting('password_require_lowercase', '0') == '1' && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (getSetting('password_require_numbers', '0') == '1' && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (getSetting('password_require_symbols', '0') == '1' && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
    }
    
    if (empty($errors)) {
        return ['valid' => true, 'message' => ''];
    } else {
        return ['valid' => false, 'message' => implode('. ', $errors) . '.'];
    }
}

// Handle success message from login redirect
if (isset($_SESSION['password_reset_success'])) {
    $success_message = $_SESSION['password_reset_success'];
    unset($_SESSION['password_reset_success']);
}

// Get constants for styling - with fallbacks
$site_name = defined('SITE_NAME') ? SITE_NAME : 'BootPHP';
$theme_color = defined('THEME_COLOR') ? THEME_COLOR : '#007bff';
$secondary_color = defined('SECONDARY_COLOR') ? SECONDARY_COLOR : '#6c757d';
$site_icon = defined('SITE_ICON') ? SITE_ICON : 'fas fa-home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo htmlspecialchars($site_name); ?></title>
    
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
        
        .password-strength {
            margin-top: 0.5rem;
        }
        
        .strength-meter {
            height: 5px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }
        
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #fd7e14; width: 50%; }
        .strength-good { background: #ffc107; width: 75%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="row justify-content-center w-100">
            <div class="col-md-6 col-lg-5">
                
                <!-- Reset Password Form -->
                <div class="card shadow-lg">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="logo">
                                <i class="fas fa-lock"></i>
                            </div>
                            <h2 class="h3 mb-0">Reset Password</h2>
                            <?php if ($token_valid && $user_data): ?>
                                <p class="text-muted">Create a new password for <?php echo htmlspecialchars($user_data['email']); ?></p>
                            <?php else: ?>
                                <p class="text-muted">Password reset</p>
                            <?php endif; ?>
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

                        <?php if ($token_valid && $user_data): ?>
                            <!-- Reset Password Form -->
                            <form action="" method="POST" id="resetPasswordForm">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               required autocomplete="new-password" placeholder="Enter new password">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength">
                                        <div class="strength-meter">
                                            <div class="strength-fill" id="strengthFill"></div>
                                        </div>
                                        <small class="text-muted" id="strengthText">Enter a password to see strength</small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               required autocomplete="new-password" placeholder="Confirm new password">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text" id="passwordMatch"></div>
                                </div>

                                <div class="d-grid gap-2 mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                        <i class="fas fa-check me-2"></i>Reset Password
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- Invalid Token Message -->
                            <div class="text-center">
                                <p class="mb-3">The password reset link is invalid or has expired.</p>
                                <a href="forgot-password.php" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Request New Reset Link
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- Back to Login -->
                        <div class="text-center pt-3 border-top">
                            <a href="./" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-2"></i>Back to Login
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
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const passwordMatch = document.getElementById('passwordMatch');
        const submitBtn = document.getElementById('submitBtn');

        // Password visibility toggles
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                togglePasswordVisibility(passwordInput, this);
            });
        }

        if (toggleConfirmPassword && confirmPasswordInput) {
            toggleConfirmPassword.addEventListener('click', function() {
                togglePasswordVisibility(confirmPasswordInput, this);
            });
        }

        function togglePasswordVisibility(input, button) {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            const icon = button.querySelector('i');
            if (type === 'password') {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }

        // Password strength checker
        if (passwordInput && strengthFill && strengthText) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
                
                strengthFill.className = 'strength-fill strength-' + strength.level;
                strengthText.textContent = strength.text;
                strengthText.className = 'text-' + strength.color;
                
                checkPasswordMatch();
            });
        }

        // Password match checker
        if (confirmPasswordInput && passwordMatch) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }

        function checkPasswordMatch() {
            if (!passwordInput || !confirmPasswordInput || !passwordMatch) return;
            
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword === '') {
                passwordMatch.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                passwordMatch.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i>Passwords match</span>';
            } else {
                passwordMatch.innerHTML = '<span class="text-danger"><i class="fas fa-times me-1"></i>Passwords do not match</span>';
            }
        }

        function checkPasswordStrength(password) {
            if (password.length === 0) {
                return { level: 'weak', text: 'Enter a password to see strength', color: 'muted' };
            }
            
            let score = 0;
            
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            if (score < 3) {
                return { level: 'weak', text: 'Weak password', color: 'danger' };
            } else if (score < 4) {
                return { level: 'fair', text: 'Fair password', color: 'warning' };
            } else if (score < 5) {
                return { level: 'good', text: 'Good password', color: 'info' };
            } else {
                return { level: 'strong', text: 'Strong password', color: 'success' };
            }
        }

        // Auto-focus password field
        if (passwordInput) {
            passwordInput.focus();
        }

        // Form submission
        const form = document.getElementById('resetPasswordForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    return;
                }
                
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Resetting Password...';
                submitBtn.disabled = true;
            });
        }

        console.log('BootPHP Reset Password page loaded successfully');
    });
    </script>
</body>
</html>