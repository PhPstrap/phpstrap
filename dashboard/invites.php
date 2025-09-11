<?php
// Include existing PhPstrap configuration
require_once '../config/app.php';
require_once '../config/functions.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../login/');
    exit();
}

// Get user info from session
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    header('Location: ../login/');
    exit();
}

// Load user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        session_destroy();
        header('Location: ../login/');
        exit();
    }
} catch(PDOException $e) {
    logError("Invites page database error: " . $e->getMessage());
    header('Location: ../login/');
    exit();
}

$user_name = $user['name'] ?? $user['email'];
$user_email = $user['email'] ?? '';

$message = '';
$error = false;

/* =============================================================================
   Settings & Configuration
   ========================================================================== */

// Get invite-related settings
function getInviteSettings($pdo) {
    $settings = [];
    $setting_keys = [
        'invite_allow_user_creation',
        'invite_admin_only',
        'invites_per_week',
        'invite_expiry_days',
        'invite_max_uses',
        'affiliate_program_enabled',
        'credit_per_signup'
    ];
    
    try {
        $placeholders = str_repeat('?,', count($setting_keys) - 1) . '?';
        $stmt = $pdo->prepare("SELECT `key`, `value`, `default_value` FROM settings WHERE `key` IN ($placeholders)");
        $stmt->execute($setting_keys);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = ($row['value'] !== null && $row['value'] !== '') ? $row['value'] : $row['default_value'];
            $settings[$row['key']] = $value;
        }
        
        // Set defaults for missing settings
        $defaults = [
            'invite_allow_user_creation' => '1',
            'invite_admin_only' => '0',
            'invites_per_week' => '1',
            'invite_expiry_days' => '30',
            'invite_max_uses' => '1',
            'affiliate_program_enabled' => '1',
            'credit_per_signup' => '10.00'
        ];
        
        foreach ($defaults as $key => $default) {
            if (!isset($settings[$key])) {
                $settings[$key] = $default;
            }
        }
        
    } catch (Exception $e) {
        error_log("Error fetching invite settings: " . $e->getMessage());
        return $defaults;
    }
    
    return $settings;
}

$invite_settings = getInviteSettings($pdo);

// Check if user can create invites
$can_create_invites = ($invite_settings['invite_allow_user_creation'] === '1' && $invite_settings['invite_admin_only'] !== '1');

/* =============================================================================
   Invite Management Functions
   ========================================================================== */

/**
 * Check user's weekly invite quota
 */
function checkWeeklyQuota($pdo, $user_id, $weekly_limit) {
    try {
        // Get start of current week (Monday)
        $week_start = date('Y-m-d', strtotime('monday this week'));
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as created_this_week
            FROM invites 
            WHERE generated_by = ? 
            AND DATE(created_at) >= ?
        ");
        $stmt->execute([$user_id, $week_start]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $created_this_week = $result['created_this_week'] ?? 0;
        $remaining = max(0, $weekly_limit - $created_this_week);
        
        return [
            'created_this_week' => $created_this_week,
            'weekly_limit' => $weekly_limit,
            'remaining' => $remaining,
            'can_create' => $remaining > 0,
            'week_start' => $week_start
        ];
    } catch (Exception $e) {
        error_log("Error checking weekly quota: " . $e->getMessage());
        return [
            'created_this_week' => 0,
            'weekly_limit' => $weekly_limit,
            'remaining' => $weekly_limit,
            'can_create' => true,
            'week_start' => date('Y-m-d')
        ];
    }
}

/**
 * Generate a unique invite code
 */
function generateInviteCode($pdo) {
    $attempts = 0;
    do {
        $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        
        $stmt = $pdo->prepare("SELECT id FROM invites WHERE code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->rowCount() > 0;
        
        $attempts++;
    } while ($exists && $attempts < 10);
    
    return $exists ? null : $code;
}

/**
 * Create a new invite
 */
function createUserInvite($pdo, $user_id, $max_uses, $expiry_days, $custom_message = '', $invite_type = 'registration') {
    $code = generateInviteCode($pdo);
    if (!$code) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO invites (code, generated_by, max_uses, expires_at, custom_message, invite_type, created_at, is_active) 
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), ?, ?, NOW(), 1)
        ");
        
        return $stmt->execute([$code, $user_id, $max_uses, $expiry_days, $custom_message, $invite_type]) ? $code : false;
    } catch (Exception $e) {
        error_log("Error creating invite: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's invites
 */
function getUserInvites($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                i.*,
                CASE 
                    WHEN i.expires_at < NOW() THEN 'expired'
                    WHEN i.max_uses > 0 AND i.uses_count >= i.max_uses THEN 'exhausted'
                    WHEN i.is_active = 0 THEN 'disabled'
                    WHEN i.is_active = 1 THEN 'available'
                    ELSE 'unknown' 
                END as effective_status
            FROM invites i
            WHERE i.generated_by = ?
            ORDER BY i.created_at DESC
        ");
        
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching user invites: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's referral stats (via invites)
 */
function getReferralStats($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN used_by IS NOT NULL THEN 1 END) as total_referrals,
                COUNT(CASE WHEN used_by IS NOT NULL AND used_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_referrals,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_invites
            FROM invites
            WHERE generated_by = ?
        ");
        
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_referrals' => $result['total_referrals'] ?? 0,
            'recent_referrals' => $result['recent_referrals'] ?? 0,
            'active_invites' => $result['active_invites'] ?? 0
        ];
    } catch (Exception $e) {
        error_log("Error fetching referral stats: " . $e->getMessage());
        return ['total_referrals' => 0, 'recent_referrals' => 0, 'active_invites' => 0];
    }
}

/* =============================================================================
   Handle Actions
   ========================================================================== */

// Check weekly quota
$weekly_limit = (int)$invite_settings['invites_per_week'];
$quota_info = checkWeeklyQuota($pdo, $user_id, $weekly_limit);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_create_invites) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_invite':
            if (!$quota_info['can_create']) {
                $message = "You have reached your weekly invite limit ({$weekly_limit}). Try again next week.";
                $error = true;
            } else {
                $max_uses = (int)($_POST['max_uses'] ?? $invite_settings['invite_max_uses']);
                $expiry_days = (int)($_POST['expiry_days'] ?? $invite_settings['invite_expiry_days']);
                $custom_message = trim($_POST['custom_message'] ?? '');
                $invite_type = $_POST['invite_type'] ?? 'registration';
                
                // Validate inputs
                if ($max_uses < 0) $max_uses = 1;
                if ($max_uses > 10) $max_uses = 10;
                if ($expiry_days < 1 || $expiry_days > 90) $expiry_days = 30;
                
                if (!in_array($invite_type, ['registration', 'premium', 'admin'])) {
                    $invite_type = 'registration';
                }
                
                $code = createUserInvite($pdo, $user_id, $max_uses, $expiry_days, $custom_message, $invite_type);
                if ($code) {
                    $message = "Invite code '{$code}' created successfully!";
                    $error = false;
                    $quota_info = checkWeeklyQuota($pdo, $user_id, $weekly_limit);
                } else {
                    $message = "Failed to create invite code. Please try again.";
                    $error = true;
                }
            }
            break;
            
        case 'disable_invite':
            $invite_id = (int)($_POST['invite_id'] ?? 0);
            if ($invite_id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE invites SET is_active = 0 WHERE id = ? AND generated_by = ?");
                    if ($stmt->execute([$invite_id, $user_id])) {
                        $message = "Invite disabled successfully.";
                        $error = false;
                    } else {
                        $message = "Failed to disable invite.";
                        $error = true;
                    }
                } catch (Exception $e) {
                    $message = "Error disabling invite.";
                    $error = true;
                }
            }
            break;
            
        case 'enable_invite':
            $invite_id = (int)($_POST['invite_id'] ?? 0);
            if ($invite_id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE invites SET is_active = 1 WHERE id = ? AND generated_by = ?");
                    if ($stmt->execute([$invite_id, $user_id])) {
                        $message = "Invite enabled successfully.";
                        $error = false;
                    } else {
                        $message = "Failed to enable invite.";
                        $error = true;
                    }
                } catch (Exception $e) {
                    $message = "Error enabling invite.";
                    $error = true;
                }
            }
            break;
    }
}

// Get data for display
$user_invites = getUserInvites($pdo, $user_id);
$referral_stats = getReferralStats($pdo, $user_id);

// Settings
$settings = [];
try {
    if (function_exists('getSetting')) {
        foreach (['site_name','theme_color','secondary_color','site_icon','site_url','affiliate_program_enabled'] as $k) {
            $settings[$k] = getSetting($k, '');
        }
    } else {
        $stmt = $pdo->query("SELECT `key`,`value` FROM settings WHERE is_public = 1");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[$row['key']] = $row['value'];
        }
    }
} catch(PDOException $e) {
    logError("Invites settings error: " . $e->getMessage());
    $settings = [
        'site_name' => 'PhPstrap',
        'theme_color' => '#0d6efd',
        'secondary_color' => '#6c757d',
        'site_icon' => 'bi bi-envelope-plus',
        'site_url' => '',
        'affiliate_program_enabled' => '1',
    ];
}

// Helper for printing value safely
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// For nav highlighting
$currentPage = 'invites';
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Invites — <?= htmlspecialchars($settings['site_name'] ?? 'PhPstrap') ?></title>

  <!-- Bootstrap 5 CSS -->
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <style>
    :root {
      --primary-color: <?= $settings['theme_color'] ?? '#0d6efd' ?>;
    }
    body { background: #f5f7fb; }
    .navbar .navbar-brand { font-weight: 700; color: var(--primary-color); }
    .card { border: none; border-radius: 1rem; box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,.05); }
    .card.hover-up:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.10); }
    .stat-number { font-size: 1.875rem; font-weight: 700; }
    .user-avatar {
      width: 40px; height: 40px; border-radius: 50%;
      background: var(--primary-color); color: #fff; display:flex; align-items:center; justify-content:center; font-weight: 700;
    }
    .status-badge {
      font-size: 0.75rem;
      padding: 0.25rem 0.5rem;
      border-radius: 15px;
    }
    .status-available {
      background: #d4edda;
      color: #155724;
    }
    .status-expired {
      background: #f8d7da;
      color: #721c24;
    }
    .status-exhausted {
      background: #fff3cd;
      color: #856404;
    }
    .status-disabled {
      background: #e2e3e5;
      color: #383d41;
    }
    .copy-code {
      cursor: pointer;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      transition: all 0.2s;
      display: inline-block;
      font-family: 'Courier New', monospace;
      font-weight: bold;
    }
    .copy-code:hover {
      background: #e9ecef;
      transform: translateY(-1px);
    }
    .quota-progress {
      background: #e9ecef;
      border-radius: 10px;
      height: 8px;
      overflow: hidden;
    }
    .quota-fill {
      background: var(--primary-color);
      height: 100%;
      border-radius: 10px;
      transition: width 0.3s ease;
    }
    .invite-url {
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      padding: 1rem;
      font-family: 'Courier New', monospace;
      font-size: 0.9rem;
      word-break: break-all;
    }
    .feature-disabled {
      opacity: 0.6;
      pointer-events: none;
    }
    .invite-type-badge {
      font-size: 0.7rem;
      padding: 0.2rem 0.4rem;
      border-radius: 10px;
      font-weight: bold;
    }
    .type-registration {
      background: #cff4fc;
      color: #055160;
    }
    .type-premium {
      background: #fff3cd;
      color: #664d03;
    }
    .type-admin {
      background: #f8d7da;
      color: #58151c;
    }
  </style>
</head>
<body>
  <div class="container-fluid">
    <div class="row g-0">

      <!-- Sidebar / Offcanvas -->
      <?php include __DIR__ . '/includes/nav.php'; ?>

      <!-- Main column -->
      <main class="col-12 col-lg-10">
        <!-- Topbar -->
        <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
          <div class="container-fluid">
            <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
              <i class="bi bi-list"></i>
            </button>
            <a class="navbar-brand" href="../"><?= htmlspecialchars($settings['site_name'] ?? 'PhPstrap') ?></a>
            <div class="ms-auto d-flex align-items-center">
              <div class="user-avatar me-2"><?= strtoupper(substr($user['name'] ?? $user['email'], 0, 1)) ?></div>
              <div class="small">
                <div class="fw-semibold"><?= htmlspecialchars($user['name'] ?? $user['email']) ?></div>
                <div class="text-muted"><?= htmlspecialchars($user['email']) ?></div>
              </div>
            </div>
          </div>
        </nav>

        <!-- Page content -->
        <div class="p-4">
          <?php if ($message): ?>
            <div class="alert alert-<?php echo $error ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
              <i class="<?php echo $error ? 'bi bi-exclamation-triangle' : 'bi bi-check-circle'; ?> me-2"></i>
              <?php echo e($message); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <!-- Feature Check -->
          <?php if (!$can_create_invites): ?>
            <div class="alert alert-info" role="alert">
              <i class="bi bi-info-circle me-2"></i>
              Invite creation is currently disabled for regular users. Contact an administrator if you need invite codes.
            </div>
          <?php endif; ?>

          <div class="mb-4">
            <h1 class="h3 mb-1">My Invites</h1>
            <p class="text-muted mb-0">Manage your invitation codes and track referrals</p>
          </div>

          <!-- Statistics -->
          <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card hover-up text-center">
                <div class="card-body">
                  <i class="bi bi-person-check display-5 d-block mb-2" style="color: var(--primary-color);"></i>
                  <div class="stat-number"><?php echo $referral_stats['total_referrals']; ?></div>
                  <div class="text-muted">Total Referrals</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card hover-up text-center">
                <div class="card-body">
                  <i class="bi bi-calendar-check display-5 d-block mb-2" style="color: var(--primary-color);"></i>
                  <div class="stat-number"><?php echo $referral_stats['recent_referrals']; ?></div>
                  <div class="text-muted">This Month</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card hover-up text-center">
                <div class="card-body">
                  <i class="bi bi-envelope-plus display-5 d-block mb-2" style="color: var(--primary-color);"></i>
                  <div class="stat-number"><?php echo count($user_invites); ?></div>
                  <div class="text-muted">My Invites</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card hover-up text-center">
                <div class="card-body">
                  <i class="bi bi-check-circle display-5 d-block mb-2" style="color: var(--primary-color);"></i>
                  <div class="stat-number"><?php echo $referral_stats['active_invites']; ?></div>
                  <div class="text-muted">Active Invites</div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <!-- Create New Invite -->
            <div class="col-12 col-xl-4">
              <div class="card <?php echo !$can_create_invites ? 'feature-disabled' : ''; ?>">
                <div class="card-header">
                  <strong><i class="bi bi-plus-circle me-2"></i>Create New Invite</strong>
                </div>
                <div class="card-body">
                  <!-- Weekly Quota Display -->
                  <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <small class="text-muted">Weekly Quota</small>
                      <small class="text-muted">
                        <?php echo $quota_info['created_this_week']; ?>/<?php echo $quota_info['weekly_limit']; ?>
                      </small>
                    </div>
                    <div class="quota-progress">
                      <div class="quota-fill" style="width: <?php echo ($quota_info['weekly_limit'] > 0) ? (($quota_info['created_this_week'] / $quota_info['weekly_limit']) * 100) : 0; ?>%"></div>
                    </div>
                    <small class="text-muted">
                      <?php echo $quota_info['remaining']; ?> remaining this week
                      (resets <?php echo date('M j', strtotime('monday next week')); ?>)
                    </small>
                  </div>

                  <?php if ($quota_info['can_create'] && $can_create_invites): ?>
                    <form method="POST">
                      <input type="hidden" name="action" value="create_invite">
                      
                      <div class="mb-3">
                        <label class="form-label">Invite Type</label>
                        <select class="form-select" name="invite_type">
                          <option value="registration" selected>Registration</option>
                          <option value="premium">Premium</option>
                          <option value="admin">Admin</option>
                        </select>
                      </div>
                      
                      <div class="mb-3">
                        <label class="form-label">Maximum Uses</label>
                        <select class="form-select" name="max_uses">
                          <option value="1" selected>1 use</option>
                          <option value="3">3 uses</option>
                          <option value="5">5 uses</option>
                          <option value="10">10 uses</option>
                        </select>
                      </div>
                      
                      <div class="mb-3">
                        <label class="form-label">Expires in</label>
                        <select class="form-select" name="expiry_days">
                          <option value="7">1 week</option>
                          <option value="30" selected>1 month</option>
                          <option value="60">2 months</option>
                          <option value="90">3 months</option>
                        </select>
                      </div>
                      
                      <div class="mb-3">
                        <label class="form-label">Custom Message (optional)</label>
                        <textarea class="form-control" name="custom_message" rows="3"
                               placeholder="e.g., Welcome to our platform! This invite is for friends and family." maxlength="500"></textarea>
                      </div>
                      
                      <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-2"></i>Create Invite
                      </button>
                    </form>
                  <?php else: ?>
                    <div class="text-center text-muted py-3">
                      <?php if (!$can_create_invites): ?>
                        <i class="bi bi-lock display-5 d-block mb-2"></i>
                        <p>Invite creation disabled</p>
                      <?php else: ?>
                        <i class="bi bi-clock display-5 d-block mb-2"></i>
                        <p>Weekly quota reached</p>
                        <small>Try again next week</small>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Referral Info -->
              <?php if ($invite_settings['affiliate_program_enabled'] === '1'): ?>
              <div class="card">
                <div class="card-header">
                  <strong><i class="bi bi-gift me-2"></i>Referral Rewards</strong>
                </div>
                <div class="card-body text-center">
                  <div class="mb-3">
                    <i class="bi bi-coin display-5 d-block" style="color: #ffc107;"></i>
                  </div>
                  <h5><?php echo e($invite_settings['credit_per_signup']); ?> Credits</h5>
                  <p class="text-muted">per successful referral</p>
                  <small class="text-muted">
                    Credits are awarded when someone registers using your invite code.
                  </small>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <!-- Invite List -->
            <div class="col-12 col-xl-8">
              <div class="card">
                <div class="card-header">
                  <strong><i class="bi bi-list-ul me-2"></i>My Invite Codes</strong>
                </div>
                <div class="card-body">
                  <?php if (empty($user_invites)): ?>
                    <div class="text-center py-4">
                      <i class="bi bi-envelope-open display-1 d-block mb-3 text-muted"></i>
                      <h5 class="text-muted">No invites created yet</h5>
                      <p class="text-muted">Create your first invite code to start referring friends!</p>
                    </div>
                  <?php else: ?>
                    <div class="row g-3">
                      <?php foreach ($user_invites as $invite): ?>
                        <div class="col-12 col-md-6">
                          <div class="card border">
                            <div class="card-body">
                              <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="copy-code" onclick="copyToClipboard('<?php echo e($invite['code']); ?>')" 
                                      title="Click to copy">
                                  <i class="bi bi-clipboard me-1"></i><?php echo e($invite['code']); ?>
                                </span>
                                <div>
                                  <span class="invite-type-badge type-<?php echo $invite['invite_type']; ?>">
                                    <?php echo ucfirst($invite['invite_type']); ?>
                                  </span>
                                  <span class="status-badge status-<?php echo $invite['effective_status']; ?>">
                                    <?php echo ucfirst($invite['effective_status']); ?>
                                  </span>
                                </div>
                              </div>
                              
                              <?php if ($invite['custom_message']): ?>
                                <p class="text-muted small mb-2"><?php echo e($invite['custom_message']); ?></p>
                              <?php endif; ?>
                              
                              <div class="small text-muted mb-2">
                                <div class="row">
                                  <div class="col-6">
                                    <strong>Uses:</strong>
                                    <?php if ($invite['max_uses'] == 0): ?>
                                      <?php echo $invite['uses_count']; ?>/∞
                                    <?php else: ?>
                                      <?php echo $invite['uses_count']; ?>/<?php echo $invite['max_uses']; ?>
                                    <?php endif; ?>
                                  </div>
                                  <div class="col-6">
                                    <strong>Expires:</strong>
                                    <?php if ($invite['expires_at']): ?>
                                      <?php echo date('M j, Y', strtotime($invite['expires_at'])); ?>
                                    <?php else: ?>
                                      Never
                                    <?php endif; ?>
                                  </div>
                                </div>
                                <div class="mt-2">
                                  <strong>Created:</strong> <?php echo date('M j, Y', strtotime($invite['created_at'])); ?>
                                </div>
                                <?php if ($invite['used_at']): ?>
                                  <div>
                                    <strong>Last Used:</strong> <?php echo date('M j, Y', strtotime($invite['used_at'])); ?>
                                  </div>
                                <?php endif; ?>
                              </div>
                              
                              <!-- Invite URL -->
                              <div class="invite-url mb-2" onclick="copyToClipboard('<?php echo e(getCurrentURL() . '../register/?invite=' . $invite['code']); ?>')">
                                <small>
                                  <i class="bi bi-link-45deg me-1"></i>
                                  <?php echo e(getCurrentURL() . '../register/?invite=' . $invite['code']); ?>
                                </small>
                              </div>
                              
                              <div class="d-flex gap-2">
                                <button onclick="copyToClipboard('<?php echo e(getCurrentURL() . '../register/?invite=' . $invite['code']); ?>')" 
                                        class="btn btn-outline-primary btn-sm flex-fill">
                                  <i class="bi bi-clipboard me-1"></i>Copy Link
                                </button>
                                
                                <?php if ($invite['is_active'] == 1 && $invite['effective_status'] === 'available'): ?>
                                  <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="disable_invite">
                                    <input type="hidden" name="invite_id" value="<?php echo $invite['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" 
                                            onclick="return confirm('Disable this invite?')" title="Disable">
                                      <i class="bi bi-x-circle"></i>
                                    </button>
                                  </form>
                                <?php elseif ($invite['is_active'] == 0 && $invite['effective_status'] === 'disabled'): ?>
                                  <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="enable_invite">
                                    <input type="hidden" name="invite_id" value="<?php echo $invite['id']; ?>">
                                    <button type="submit" class="btn btn-outline-success btn-sm" 
                                            onclick="return confirm('Enable this invite?')" title="Enable">
                                      <i class="bi bi-check-circle"></i>
                                    </button>
                                  </form>
                                <?php endif; ?>
                              </div>
                              
                              <!-- Show who used this invite -->
                              <?php if ($invite['used_by']): ?>
                                <hr>
                                <small class="text-muted">
                                  <strong>Used by:</strong> User ID <?php echo $invite['used_by']; ?>
                                  <?php if ($invite['email']): ?>
                                    (<?php echo e($invite['email']); ?>)
                                  <?php endif; ?>
                                </small>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Bootstrap 5 JS -->
  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Get current URL for invite links
    function getCurrentURL() {
        return window.location.protocol + '//' + window.location.host + window.location.pathname.replace(/\/[^\/]*$/, '/');
    }
    
    // Copy to clipboard function
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Show temporary success message
            const originalContent = event.target.innerHTML;
            event.target.innerHTML = '<i class="bi bi-check me-1 text-success"></i>Copied!';
            
            setTimeout(() => {
                event.target.innerHTML = originalContent;
            }, 2000);
        }).catch(function() {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            alert('Copied: ' + text);
        });
    }
    
    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(alert => {
            try { 
                new bootstrap.Alert(alert).close(); 
            } catch (e) {}
        });
    }, 5000);
    
    console.log('PhPstrap User Invites page loaded successfully');
  </script>
</body>
</html>

<?php
// Helper function to get current URL
function getCurrentURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['REQUEST_URI']);
    return $protocol . '://' . $host . $path . '/';
}
?>