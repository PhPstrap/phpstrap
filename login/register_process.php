<?php
session_start();
require_once '../config/database.php';

function generateAffiliateID() {
    return substr(md5(uniqid(mt_rand(), true)), 0, 10);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $invite_code = $_POST['invite_code'] ?? null;
    
    if (INVITE_ONLY_MODE) {
        if (!$invite_code) {
            $_SESSION['registration_error'] = "Invite code is required.";
            header("Location: register.php");
            exit;
        }
        
        $stmt = $conn->prepare("SELECT id, generated_by FROM invites WHERE code = ? AND used_by IS NULL");
        $stmt->bind_param("s", $invite_code);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows !== 1) {
            $_SESSION['registration_error'] = "Invalid or already used invite code.";
            header("Location: register.php");
            exit;
        }
        
        $stmt->bind_result($invite_id, $generated_by);
        $stmt->fetch();
        $stmt->close();
    }
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $_SESSION['registration_error'] = "This email address is already registered.";
        header("Location: register.php");
        exit;
    }
    $stmt->close();
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $user_affiliate_id = generateAffiliateID();
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, affiliate_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $hashed_password, $user_affiliate_id);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        
        if (INVITE_ONLY_MODE) {
            $stmt = $conn->prepare("UPDATE invites SET used_by = ?, used_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $user_id, $invite_id);
            $stmt->execute();
        }
        
        $verification_token = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("UPDATE users SET verification_token = ?, last_verification_sent_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $verification_token, $user_id);
        $stmt->execute();
        
        $subject = "Verify Your Email - XYZ";
        $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $verify_link = "https://xyz.am/login/verify-email.php?token=$verification_token";
        
        $body = <<<HTML
<p>Dear {$safe_name},</p>
<p>Please verify your email by clicking the link below:</p>
<p><a href="{$verify_link}">Verify Email</a></p>
<p>If the link above does not work, copy and paste this URL into your browser:</p>
<p>{$verify_link}</p>
HTML;
        
        if (empty(trim($body))) {
            $body = "Please verify your email using this link: $verify_link";
        }
        
        if (send_email($email, $subject, $body)) {
            $_SESSION['registration_success'] = "Registration successful! Please check your email for verification.";
            $_SESSION['resend_email_user_id'] = $user_id;
            
            // Add to Sendy registration list ONLY after email verification is sent successfully
            // This runs in the background and won't affect registration success
            if (defined('SENDY_API_KEY') && defined('SENDY_URL') && defined('SENDY_REGISTRATION_LIST_ID')) {
                $sendy_data = array(
                    'api_key' => SENDY_API_KEY,
                    'name' => $name,
                    'email' => $email,
                    'list' => SENDY_REGISTRATION_LIST_ID,
                    'boolean' => 'true',
                    'ipaddress' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'hp' => '' // honeypot field
                );
                
                // Use a very short timeout so it doesn't delay the user
                $sendy_ch = curl_init();
                curl_setopt($sendy_ch, CURLOPT_URL, rtrim(SENDY_URL, '/') . '/subscribe');
                curl_setopt($sendy_ch, CURLOPT_POST, 1);
                curl_setopt($sendy_ch, CURLOPT_POSTFIELDS, http_build_query($sendy_data));
                curl_setopt($sendy_ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($sendy_ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
                curl_setopt($sendy_ch, CURLOPT_FOLLOWLOCATION, false);
                
                $sendy_result = @curl_exec($sendy_ch);
                $sendy_http_code = curl_getinfo($sendy_ch, CURLINFO_HTTP_CODE);
                curl_close($sendy_ch);
                
                // Log result for debugging (optional)
                if ($sendy_http_code == 200 && ($sendy_result == 'true' || $sendy_result == '1')) {
                    error_log("Sendy: Successfully subscribed {$email} to registration list");
                } elseif ($sendy_result == 'Already subscribed.' || strpos($sendy_result, 'Already subscribed') !== false) {
                    error_log("Sendy: {$email} already subscribed to registration list");
                } else {
                    error_log("Sendy: Failed to subscribe {$email} - Response: {$sendy_result}");
                }
            }
            
        } else {
            $_SESSION['registration_error'] = "Registration completed, but email could not be sent.";
        }
        
        header("Location: register.php");
        exit;
    } else {
        $_SESSION['registration_error'] = "There was an error processing your registration.";
        header("Location: register.php");
        exit;
    }
}
?>