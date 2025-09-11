<?php
// Include existing PhPstrap configuration
require_once '../config/app.php';
require_once '../config/functions.php';

// ============================================
// hCaptcha Configuration & Initialization
// ============================================

/**
 * Check if hCaptcha should be enabled
 */
function checkHCaptchaStatus($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT enabled, settings FROM modules WHERE name = 'hcaptcha'");
        $stmt->execute();
        $module = $stmt->fetch();
        
        if ($module) {
            $enabled = (bool)$module['enabled'];
            $settings = json_decode($module['settings'], true);
            
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
    
    if (file_exists('../modules/hcaptcha/HCaptcha.php')) {
        return [
            'enabled' => false,
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
        redirect('dashboard/');
    }
} catch (Exception $e) {
    // Continue to registration page
}

// ============================================
// Settings Validation & Access Control
// ============================================

/**
 * Get all registration-related settings from database
 */
function getRegistrationSettings($pdo) {
    $settings = [];
    $setting_keys = [
        'registration_enabled',
        'invite_only_mode', 
        'email_verification_required',
        'admin_approval_required',
        'auto_approve_users',
        'default_user_credits',
        'default_user_role',
        'password_min_length',
        'password_require_uppercase',
        'password_require_lowercase', 
        'password_require_numbers',
        'password_require_symbols',
        'affiliate_program_enabled',
        'credit_per_signup',
        'site_name',
        'site_url',
        'theme_color',
        'secondary_color',
        'site_icon'
    ];
    
    // Set defaults first
    $defaults = [
        'registration_enabled' => '1',
        'invite_only_mode' => '0',
        'email_verification_required' => '1',
        'admin_approval_required' => '0', 
        'auto_approve_users' => '1',
        'default_user_credits' => '0.00',
        'default_user_role' => 'user',
        'password_min_length' => '8',
        'password_require_uppercase' => '0',
        'password_require_lowercase' => '0',
        'password_require_numbers' => '0', 
        'password_require_symbols' => '0',
        'affiliate_program_enabled' => '1',
        'credit_per_signup' => '10.00',
        'site_name' => 'PhPstrap',
        'site_url' => '',
        'theme_color' => '#007bff',
        'secondary_color' => '#6c757d',
        'site_icon' => 'fas fa-home'
    ];
    
    $settings = $defaults;
    
    try {
        // Try getSetting function first if it exists
        if (function_exists('getSetting')) {
            foreach ($setting_keys as $key) {
                $value = getSetting($key, $defaults[$key] ?? '');
                $settings[$key] = $value;
            }
        } else {
            // Fallback to direct database query
            $placeholders = str_repeat('?,', count($setting_keys) - 1) . '?';
            $stmt = $pdo->prepare("SELECT `key`, `value`, `default_value` FROM settings WHERE `key` IN ($placeholders)");
            $stmt->execute($setting_keys);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = ($row['value'] !== null && $row['value'] !== '') ? $row['value'] : $row['default_value'];
                if (array_key_exists($row['key'], $settings)) {
                    $settings[$row['key']] = $value;
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error fetching registration settings: " . $e->getMessage());
        return $defaults;
    }
    
    return $settings;
}

$reg_settings = getRegistrationSettings($pdo);

// ============================================
// Registration Access Control Logic (FIXED)
// ============================================

$access_denied = false;
$access_message = '';
$requires_invite = false;
$show_invite_field = false;

// Check if registration is completely disabled
if ($reg_settings['registration_enabled'] !== '1') {
    $access_denied = true;
    $access_message = "Registration is currently disabled. Please contact support if you need an account.";
}

// Check invite-only mode - IMPROVED LOGIC
if (!$access_denied && $reg_settings['invite_only_mode'] === '1') {
    $requires_invite = true;
    $show_invite_field = true;
    // DON'T deny access here - let them use the form with invite code field
}

// If access is denied, show appropriate message and redirect or block
if ($access_denied) {
    $_SESSION['registration_message'] = $access_message;
    $_SESSION['registration_message_type'] = 'warning';
    
    // For AJAX requests or if they want to stay on page, show the message
    if (isset($_GET['stay']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
        // Will show the message but disable the form
    } else {
        redirect('login/?registration_disabled=1');
    }
}

$error_message = '';
$success_message = '';

// ============================================
// Invite Code Validation
// ============================================

/**
 * Validate invite code if required
 */
function validateInviteCode($pdo, $invite_code, $settings) {
    if ($settings['invite_only_mode'] !== '1') {
        return ['valid' => true, 'message' => '', 'invite_data' => null];
    }
    
    if (empty($invite_code)) {
        return ['valid' => false, 'message' => 'Invitation code is required.', 'invite_data' => null];
    }
    
    try {
        // Updated query to match your database structure
        $stmt = $pdo->prepare("
            SELECT i.*, u.name as inviter_name 
            FROM invites i 
            LEFT JOIN users u ON i.generated_by = u.id 
            WHERE i.code = ? AND i.is_active = 1 
            AND (i.expires_at IS NULL OR i.expires_at > NOW())
        ");
        $stmt->execute([$invite_code]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invite) {
            return ['valid' => false, 'message' => 'Invalid or expired invitation code.', 'invite_data' => null];
        }
        
        // Check if invite has remaining uses
        if ($invite['max_uses'] > 0 && $invite['uses_count'] >= $invite['max_uses']) {
            return ['valid' => false, 'message' => 'This invitation code has reached its usage limit.', 'invite_data' => null];
        }
        
        return ['valid' => true, 'message' => 'Valid invitation from ' . ($invite['inviter_name'] ?: 'Administrator'), 'invite_data' => $invite];
        
    } catch (Exception $e) {
        error_log("Error validating invite code: " . $e->getMessage());
        return ['valid' => false, 'message' => 'Error validating invitation code.', 'invite_data' => null];
    }
}

// ============================================
// Form Processing
// ============================================

$invite_validation = null;

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$access_denied) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $agree_terms = isset($_POST['agree_terms']);
    $referral_code = trim($_POST['referral_code'] ?? '');
    $invite_code = trim($_POST['invite_code'] ?? '');
    
    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        $error_message = "Name, email, and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (!$agree_terms) {
        $error_message = "You must agree to the terms and conditions.";
    } else {
        // Validate invite code if required
        if ($requires_invite || !empty($invite_code)) {
            $invite_validation = validateInviteCode($pdo, $invite_code, $reg_settings);
            if (!$invite_validation['valid']) {
                $error_message = $invite_validation['message'];
            }
        }
        
        if (empty($error_message)) {
            // Validate password strength
            $password_validation = validateRegistrationPassword($password, $reg_settings);
            if (!$password_validation['valid']) {
                $error_message = $password_validation['message'];
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
                        error_log("hCaptcha validation error in registration: " . $e->getMessage());
                        $captcha_valid = true;
                        error_log("Registration proceeding without hCaptcha due to validation error");
                    }
                }
                
                if ($captcha_valid) {
                    try {
                        // Check if email already exists
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        
                        if ($stmt->rowCount() > 0) {
                            $error_message = "An account with this email address already exists.";
                        } else {
                            // Check referral code if provided
                            $referrer_id = null;
                            if (!empty($referral_code)) {
                                $ref_stmt = $pdo->prepare("SELECT id, name FROM users WHERE affiliate_id = ? AND is_active = 1");
                                $ref_stmt->execute([$referral_code]);
                                if ($ref_stmt->rowCount() === 1) {
                                    $referrer = $ref_stmt->fetch();
                                    $referrer_id = $referrer['id'];
                                } else {
                                    $error_message = "Invalid referral code.";
                                }
                            }
                            
                            if (empty($error_message)) {
                                // Begin transaction
                                $pdo->beginTransaction();
                                
                                try {
                                    // Hash password
                                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                    
                                    // Generate affiliate ID
                                    $affiliate_id = generateAffiliateId($pdo);
                                    
                                    // Determine user status based on settings
                                    $verification_token = null;
                                    $verified = 1; // Default to verified
                                    $is_active = 1; // Default to active
                                    
                                    // Email verification logic
                                    if ($reg_settings['email_verification_required'] === '1') {
                                        $verification_token = bin2hex(random_bytes(32));
                                        $verified = 0; // User needs to verify email
                                    }
                                    
                                    // Admin approval logic
                                    if ($reg_settings['admin_approval_required'] === '1') {
                                        $is_active = 0; // Needs admin approval
                                    } else if ($reg_settings['auto_approve_users'] === '1') {
                                        $is_active = 1; // Auto-approve
                                    }
                                    
                                    // Special handling for invite-only registrations
                                    if ($invite_validation && $invite_validation['valid']) {
                                        // If invite auto-approve is enabled, override admin approval
                                        if (isset($reg_settings['invite_auto_approve']) && $reg_settings['invite_auto_approve'] === '1') {
                                            $is_active = 1;
                                        }
                                    }
                                    
                                    // Insert new user
                                    $insert_stmt = $pdo->prepare("
                                        INSERT INTO users (
                                            name, email, password, affiliate_id, verification_token, verified, 
                                            is_active, last_verification_sent_at, credits, membership_status, 
                                            created_at, updated_at
                                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), NOW())
                                    ");
                                    
                                    $insert_stmt->execute([
                                        $name, $email, $hashed_password, $affiliate_id, 
                                        $verification_token, $verified, $is_active,
                                        $reg_settings['default_user_credits'], 
                                        $reg_settings['default_user_role']
                                    ]);
                                    
                                    $user_id = $pdo->lastInsertId();
                                    
                                    // Handle invite code usage - Updated for your database structure
                                    if ($invite_validation && $invite_validation['valid']) {
                                        $invite_data = $invite_validation['invite_data'];
                                        
                                        // Update invite usage count and used_by field
                                        $update_invite_stmt = $pdo->prepare("
                                            UPDATE invites 
                                            SET uses_count = uses_count + 1, used_by = ?, used_at = NOW(), email = ?
                                            WHERE id = ?
                                        ");
                                        $update_invite_stmt->execute([$user_id, $email, $invite_data['id']]);
                                        
                                        // Record invite usage in separate table if it exists
                                        try {
                                            $record_usage_stmt = $pdo->prepare("
                                                INSERT INTO invite_usages (invite_id, user_id, used_at) 
                                                VALUES (?, ?, NOW())
                                            ");
                                            $record_usage_stmt->execute([$invite_data['id'], $user_id]);
                                        } catch (Exception $e) {
                                            // Table might not exist, that's okay
                                            error_log("Invite usage table not found: " . $e->getMessage());
                                        }
                                    }
                                    
                                    // Handle referral if exists
                                    if ($referrer_id && $reg_settings['affiliate_program_enabled'] === '1') {
                                        // Record the referral signup
                                        $signup_stmt = $pdo->prepare("
                                            INSERT INTO affiliate_signups (user_id, referred_user_id, signup_time, status) 
                                            VALUES (?, ?, NOW(), 'pending')
                                        ");
                                        $signup_stmt->execute([$referrer_id, $user_id]);
                                        
                                        // Award referral credits to referrer
                                        $referral_credits = $reg_settings['credit_per_signup'];
                                        if ($referral_credits > 0) {
                                            $credit_stmt = $pdo->prepare("
                                                UPDATE users 
                                                SET credits = credits + ? 
                                                WHERE id = ?
                                            ");
                                            $credit_stmt->execute([$referral_credits, $referrer_id]);
                                        }
                                    }
                                    
                                    // Commit transaction
                                    $pdo->commit();
                                    
                                    // Log registration
                                    if (function_exists('getUserIP')) {
                                        $user_ip = getUserIP();
                                    } else {
                                        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                                    }
                                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                                    
                                    if (function_exists('logActivity')) {
                                        logActivity($email, $user_ip, $user_agent, true, 'user_registered');
                                    }
                                    
                                    // Determine next steps based on settings
                                    $needs_verification = ($reg_settings['email_verification_required'] === '1' && $verification_token);
                                    $needs_approval = ($reg_settings['admin_approval_required'] === '1' && !$is_active);
                                    
                                    if ($needs_verification) {
                                        // Send verification email
                                        $verification_sent = sendVerificationEmail($email, $name, $verification_token, $reg_settings);
                                        
                                        if ($verification_sent) {
                                            $success_message = "Registration successful! Please check your email and click the verification link to activate your account.";
                                        } else {
                                            $success_message = "Registration successful! However, we couldn't send the verification email. Please contact support.";
                                        }
                                    } elseif ($needs_approval) {
                                        $success_message = "Registration successful! Your account is pending admin approval. You'll receive an email once approved.";
                                    } else {
                                        // Auto-login the user
                                        $_SESSION['loggedin'] = true;
                                        $_SESSION['user_id'] = $user_id;
                                        $_SESSION['name'] = $name;
                                        $_SESSION['email'] = $email;
                                        $_SESSION['membership_status'] = $reg_settings['default_user_role'];
                                        $_SESSION['credits'] = $reg_settings['default_user_credits'];
                                        $_SESSION['is_admin'] = 0;
                                        
                                        $_SESSION['registration_success'] = "Welcome to " . $reg_settings['site_name'] . "! Your account has been created successfully.";
                                        redirect('dashboard/');
                                    }
                                    
                                    // Clear form data on success
                                    unset($_POST);
                                    
                                } catch (Exception $e) {
                                    $pdo->rollBack();
                                    if (function_exists('logError')) {
                                        logError("Registration error: " . $e->getMessage());
                                    }
                                    $error_message = "An error occurred during registration. Please try again.";
                                }
                            }
                        }
                    } catch (PDOException $e) {
                        if (function_exists('logError')) {
                            logError("Database error in registration: " . $e->getMessage());
                        }
                        $error_message = "An error occurred. Please try again later.";
                    }
                }
            }
        }
    }
}

/**
 * Validate password strength for registration
 */
function validateRegistrationPassword($password, $settings) {
    $min_length = (int)$settings['password_min_length'];
    $errors = [];
    
    if (strlen($password) < $min_length) {
        $errors[] = "Password must be at least {$min_length} characters long";
    }
    
    if ($settings['password_require_uppercase'] === '1' && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if ($settings['password_require_lowercase'] === '1' && !preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if ($settings['password_require_numbers'] === '1' && !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if ($settings['password_require_symbols'] === '1' && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    if (empty($errors)) {
        return ['valid' => true, 'message' => ''];
    } else {
        return ['valid' => false, 'message' => implode('. ', $errors) . '.'];
    }
}

/**
 * Generate unique affiliate ID
 */
function generateAffiliateId($pdo) {
    do {
        $affiliate_id = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8));
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE affiliate_id = ?");
        $stmt->execute([$affiliate_id]);
    } while ($stmt->rowCount() > 0);
    
    return $affiliate_id;
}

/**
 * Send verification email using SMTP module
 */
function sendVerificationEmail($to_email, $user_name, $verification_token, $settings) {
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
        $site_name = $settings['site_name'];
        $site_url = $settings['site_url'];
        
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
        $html_content = createVerificationEmailTemplate($user_name, $verification_link, $site_name, $settings);
        
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
function createVerificationEmailTemplate($user_name, $verification_link, $site_name, $settings) {
    $theme_color = $settings['theme_color'] ?? '#007bff';
    
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

// Handle referral parameter
$referral_code = $_GET['ref'] ?? '';

// Handle invite parameter (from URL if provided)
$invite_code = $_GET['invite'] ?? '';

// Get constants for styling - with fallbacks
$site_name = $reg_settings['site_name'];
$theme_color = $reg_settings['theme_color'] ?? '#007bff';
$secondary_color = $reg_settings['secondary_color'] ?? '#6c757d';
$site_icon = $reg_settings['site_icon'] ?? 'fas fa-home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo htmlspecialchars($site_name); ?></title>
    
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
        
        .register-container {
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
        
        .btn-primary:disabled {
            background-color: #6c757d;
            border-color: #6c757d;
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
        
        .referral-info, .invite-info {
            background: rgba(40, 167, 69, 0.1);
            border-left: 4px solid #28a745;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .access-denied {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
            padding: 2rem;
            border-radius: 0.5rem;
            text-align: center;
        }
        
        .settings-info {
            background: rgba(13, 202, 240, 0.1);
            border-left: 4px solid #0dcaf0;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
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
        
        .form-disabled {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="container register-container">
        <div class="row justify-content-center w-100">
            <div class="col-md-8 col-lg-6">
                
                <!-- Registration Form -->
                <div class="card shadow-lg">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="logo">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h2 class="h3 mb-0">Create Account</h2>
                            <p class="text-muted">Join <?php echo htmlspecialchars($site_name); ?> today</p>
                            
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

                        <!-- Access Denied Message -->
                        <?php if ($access_denied): ?>
                            <div class="access-denied">
                                <i class="fas fa-user-slash fa-3x text-danger mb-3"></i>
                                <h4 class="text-danger">Registration Not Available</h4>
                                <p class="mb-3"><?php echo htmlspecialchars($access_message); ?></p>
                                <a href="login/" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                                </a>
                            </div>
                        <?php else: ?>

                        <!-- Registration Settings Info -->
                        <?php if ($reg_settings['email_verification_required'] === '1' || $reg_settings['admin_approval_required'] === '1' || $requires_invite): ?>
                            <div class="settings-info">
                                <small>
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Registration Requirements:</strong>
                                    <?php if ($requires_invite): ?>
                                        Invitation code required.
                                    <?php endif; ?>
                                    <?php if ($reg_settings['email_verification_required'] === '1'): ?>
                                        Email verification required.
                                    <?php endif; ?>
                                    <?php if ($reg_settings['admin_approval_required'] === '1'): ?>
                                        Admin approval required.
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <!-- Invite Info -->
                        <?php if (!empty($invite_code) && $invite_validation && $invite_validation['valid']): ?>
                            <div class="invite-info">
                                <small>
                                    <i class="fas fa-envelope me-2"></i>
                                    <strong><?php echo htmlspecialchars($invite_validation['message']); ?></strong>
                                </small>
                            </div>
                        <?php endif; ?>

                        <!-- Referral Info -->
                        <?php if (!empty($referral_code)): ?>
                            <div class="referral-info">
                                <small>
                                    <i class="fas fa-users me-2"></i>
                                    <strong>You're being referred!</strong> 
                                    Registration will credit your referrer and may earn you bonus credits.
                                </small>
                            </div>
                        <?php endif; ?>

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

                        <!-- Registration Form -->
                        <form action="" method="POST" id="registerForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                                   required autocomplete="name" placeholder="Enter your full name">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
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
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            Password
                                            <small class="text-muted">(min <?php echo $reg_settings['password_min_length']; ?> characters)</small>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   required autocomplete="new-password" placeholder="Create password">
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
                                        <?php if ($reg_settings['password_require_uppercase'] === '1' || $reg_settings['password_require_lowercase'] === '1' || $reg_settings['password_require_numbers'] === '1' || $reg_settings['password_require_symbols'] === '1'): ?>
                                        <div class="form-text">
                                            <small>Password must contain:
                                            <?php if ($reg_settings['password_require_uppercase'] === '1'): ?>uppercase letter, <?php endif; ?>
                                            <?php if ($reg_settings['password_require_lowercase'] === '1'): ?>lowercase letter, <?php endif; ?>
                                            <?php if ($reg_settings['password_require_numbers'] === '1'): ?>number, <?php endif; ?>
                                            <?php if ($reg_settings['password_require_symbols'] === '1'): ?>special character<?php endif; ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                   required autocomplete="new-password" placeholder="Confirm password">
                                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text" id="passwordMatch"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Invite Code Field - ALWAYS show when invite-only mode is enabled OR if code in URL -->
                            <?php if ($requires_invite || !empty($invite_code) || isset($_POST['invite_code'])): ?>
                            <div class="mb-3">
                                <label for="invite_code" class="form-label">
                                    Invitation Code
                                    <?php if ($requires_invite): ?><span class="text-danger">*</span><?php endif; ?>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="text" class="form-control" id="invite_code" name="invite_code" 
                                           value="<?php echo htmlspecialchars($invite_code ?: ($_POST['invite_code'] ?? '')); ?>" 
                                           <?php echo $requires_invite ? 'required' : ''; ?>
                                           placeholder="<?php echo $requires_invite ? 'Enter your invitation code' : 'Optional invitation code'; ?>">
                                </div>
                                <div class="form-text">
                                    <?php if ($requires_invite): ?>
                                        <i class="fas fa-exclamation-circle text-warning me-1"></i>
                                        An invitation code is required to register on this site.
                                    <?php else: ?>
                                        Have an invitation code? Enter it here to credit your inviter.
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Referral Code Field (show if referral code in URL or previously entered) -->
                            <?php if (!empty($referral_code) || isset($_POST['referral_code'])): ?>
                            <div class="mb-3">
                                <label for="referral_code" class="form-label">Referral Code</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-users"></i>
                                    </span>
                                    <input type="text" class="form-control" id="referral_code" name="referral_code" 
                                           value="<?php echo htmlspecialchars($referral_code ?: ($_POST['referral_code'] ?? '')); ?>" 
                                           placeholder="Optional referral code">
                                </div>
                                <div class="form-text">Have a referral code? Enter it here to credit your referrer.</div>
                            </div>
                            <?php endif; ?>

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

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
                                    <label class="form-check-label" for="agree_terms">
                                        I agree to the 
                                        <a href="#" target="_blank" class="text-decoration-none">Terms of Service</a> 
                                        and 
                                        <a href="#" target="_blank" class="text-decoration-none">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mb-3">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                            </div>
                        </form>

                        <?php endif; // End access check ?>

                        <!-- Login Link -->
                        <div class="text-center pt-3 border-top">
                            <span class="text-muted">Already have an account?</span>
                            <a href="login/" class="text-decoration-none ms-2">
                                <i class="fas fa-sign-in-alt me-1"></i>Login Here
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
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const passwordMatch = document.getElementById('passwordMatch');
        const submitBtn = document.getElementById('submitBtn');

        // Password requirements from PHP
        const passwordRequirements = {
            minLength: <?php echo $reg_settings['password_min_length']; ?>,
            requireUppercase: <?php echo $reg_settings['password_require_uppercase'] === '1' ? 'true' : 'false'; ?>,
            requireLowercase: <?php echo $reg_settings['password_require_lowercase'] === '1' ? 'true' : 'false'; ?>,
            requireNumbers: <?php echo $reg_settings['password_require_numbers'] === '1' ? 'true' : 'false'; ?>,
            requireSymbols: <?php echo $reg_settings['password_require_symbols'] === '1' ? 'true' : 'false'; ?>
        };

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
            let issues = [];
            
            if (password.length >= passwordRequirements.minLength) {
                score++;
            } else {
                issues.push(`At least ${passwordRequirements.minLength} characters`);
            }
            
            if (password.length >= passwordRequirements.minLength + 4) score++;
            
            if (passwordRequirements.requireLowercase && /[a-z]/.test(password)) {
                score++;
            } else if (passwordRequirements.requireLowercase) {
                issues.push('lowercase letter');
            }
            
            if (passwordRequirements.requireUppercase && /[A-Z]/.test(password)) {
                score++;
            } else if (passwordRequirements.requireUppercase) {
                issues.push('uppercase letter');
            }
            
            if (passwordRequirements.requireNumbers && /[0-9]/.test(password)) {
                score++;
            } else if (passwordRequirements.requireNumbers) {
                issues.push('number');
            }
            
            if (passwordRequirements.requireSymbols && /[^A-Za-z0-9]/.test(password)) {
                score++;
            } else if (passwordRequirements.requireSymbols) {
                issues.push('special character');
            }
            
            if (issues.length > 0) {
                return { level: 'weak', text: 'Missing: ' + issues.join(', '), color: 'danger' };
            }
            
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

        // Auto-focus name field
        const nameInput = document.getElementById('name');
        if (nameInput) {
            nameInput.focus();
        }

        // Form submission validation
        const form = document.getElementById('registerForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const agreeTerms = document.getElementById('agree_terms').checked;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    return;
                }
                
                if (password.length < passwordRequirements.minLength) {
                    e.preventDefault();
                    alert(`Password must be at least ${passwordRequirements.minLength} characters long.`);
                    return;
                }
                
                if (!agreeTerms) {
                    e.preventDefault();
                    alert('You must agree to the terms and conditions.');
                    return;
                }
                
                // Check invite code if required
                const inviteInput = document.getElementById('invite_code');
                if (inviteInput && inviteInput.hasAttribute('required') && !inviteInput.value.trim()) {
                    e.preventDefault();
                    alert('Invitation code is required.');
                    return;
                }
                
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
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
                submitBtn.disabled = true;
            });
        }

        // Auto-hide alerts after 10 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 10000);

        console.log('PhPstrap Registration page loaded successfully');
        console.log('hCaptcha status: <?php echo $hcaptcha_enabled ? 'enabled' : 'disabled'; ?>');
        console.log('Registration settings:', {
            registrationEnabled: <?php echo $reg_settings['registration_enabled'] === '1' ? 'true' : 'false'; ?>,
            inviteOnly: <?php echo $reg_settings['invite_only_mode'] === '1' ? 'true' : 'false'; ?>,
            emailVerification: <?php echo $reg_settings['email_verification_required'] === '1' ? 'true' : 'false'; ?>,
            adminApproval: <?php echo $reg_settings['admin_approval_required'] === '1' ? 'true' : 'false'; ?>
        });
    });
    </script>
</body>
</html>