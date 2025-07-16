<?php
// Include existing PhPstrap configuration
require_once '../config/app.php';
require_once '../config/functions.php';

// Start session with proper settings
session_start();

// Check if user is logged in - use the same session variables as your login script
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../login/');
    exit();
}

// Get user information using the existing PDO connection from app.php
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: ../login/');
        exit();
    }
} catch(PDOException $e) {
    logError("Dashboard database error: " . $e->getMessage());
    header('Location: ../login/');
    exit();
}

// Get user statistics
$stats = [];

try {
    // Get affiliate clicks count
    $stmt = $pdo->prepare("SELECT COUNT(*) as click_count FROM affiliate_clicks WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $stats['affiliate_clicks'] = $stmt->fetch(PDO::FETCH_ASSOC)['click_count'];

    // Get affiliate signups count
    $stmt = $pdo->prepare("SELECT COUNT(*) as signup_count FROM affiliate_signups WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $stats['affiliate_signups'] = $stmt->fetch(PDO::FETCH_ASSOC)['signup_count'];

    // Get total commission earned
    $stmt = $pdo->prepare("SELECT SUM(commission_amount) as total_commission FROM affiliate_signups WHERE user_id = ? AND status = 'paid'");
    $stmt->execute([$user['id']]);
    $stats['total_commission'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_commission'] ?? 0;

    // Get recent login logs (last 5)
    $stmt = $pdo->prepare("SELECT * FROM login_logs WHERE email = ? AND success = 1 ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user['email']]);
    $recent_logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    logError("Dashboard stats error: " . $e->getMessage());
    // Set default values if stats query fails
    $stats = [
        'affiliate_clicks' => 0,
        'affiliate_signups' => 0,
        'total_commission' => 0
    ];
    $recent_logins = [];
}

// Get site settings - use the getSetting function if available, otherwise query directly
$settings = [];
try {
    if (function_exists('getSetting')) {
        // Use the getSetting function from your PhPstrap system
        $setting_keys = [
            'site_name', 'theme_color', 'secondary_color', 'site_icon', 
            'site_url', 'affiliate_program_enabled', 'api_enabled'
        ];
        foreach ($setting_keys as $key) {
            $settings[$key] = getSetting($key, '');
        }
    } else {
        // Fallback to direct database query
        $stmt = $pdo->prepare("SELECT key, value FROM settings WHERE is_public = 1");
        $stmt->execute();
        $settings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($settings_raw as $setting) {
            $settings[$setting['key']] = $setting['value'];
        }
    }
} catch(PDOException $e) {
    logError("Dashboard settings error: " . $e->getMessage());
    // Set default values
    $settings = [
        'site_name' => 'PhPstrap',
        'theme_color' => '#007bff',
        'secondary_color' => '#6c757d',
        'site_icon' => 'fas fa-home',
        'site_url' => '',
        'affiliate_program_enabled' => '1',
        'api_enabled' => '1'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($settings['site_name'] ?? 'PhPstrap'); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: <?php echo $settings['theme_color'] ?? '#007bff'; ?>;
            --secondary-color: <?php echo $settings['secondary_color'] ?? '#6c757d'; ?>;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
        }
        
        .stat-card .card-body {
            padding: 2rem;
        }
        
        .membership-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .membership-free { background: #e3f2fd; color: #1976d2; }
        .membership-premium { background: #fff3e0; color: #f57c00; }
        .membership-lifetime { background: #e8f5e8; color: #388e3c; }
        
        .activity-item {
            padding: 1rem;
            border-left: 3px solid var(--primary-color);
            margin-bottom: 1rem;
            background: white;
            border-radius: 0.5rem;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
        }
        
        .alert {
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
   <div class="container-fluid">
    <div class="row">

<!-- Sidebar (Mobile Offcanvas & Desktop Sidebar) -->
<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header bg-primary text-white">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">
            <i class="<?php echo $settings['site_icon'] ?? 'bi bi-speedometer2'; ?> me-2"></i>
            Dashboard
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body sidebar">
        <div class="p-2">
            <nav class="nav flex-column">
                <!-- your nav links here -->
                <a class="nav-link active" href="./"><i class="bi bi-house me-2"></i> Overview</a>
                <a class="nav-link" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a>
                <?php if (($settings['affiliate_program_enabled'] ?? '0') == '1'): ?>
                <a class="nav-link" href="affiliate.php"><i class="bi bi-share me-2"></i> Affiliate</a>
                <?php endif; ?>
                <?php if (($settings['api_enabled'] ?? '0') == '1'): ?>
                <a class="nav-link" href="api.php"><i class="bi bi-code-slash me-2"></i> API Access</a>
                <?php endif; ?>
                <a class="nav-link" href="billing.php"><i class="bi bi-credit-card me-2"></i> Billing</a>
                <a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a>
                <hr class="my-3" style="border-color: rgba(255,255,255,0.2);">
                <a class="nav-link" href="support.php"><i class="bi bi-question-circle me-2"></i> Support</a>
                <?php if ($user['is_admin']): ?>
                <a class="nav-link" href="../admin/"><i class="bi bi-shield-lock me-2"></i> Admin Panel</a>
                <?php endif; ?>
                <a class="nav-link" href="../login/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
            </nav>
        </div>
    </div>
</div>

<!-- Desktop Sidebar -->
<div class="col-12 col-md-3 col-lg-2 d-none d-lg-block px-0">
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-white mb-4">
                <i class="<?php echo $settings['site_icon'] ?? 'bi bi-speedometer2'; ?> me-2"></i>
                Dashboard
            </h4>
            <nav class="nav flex-column">
                <!-- duplicate nav links for desktop -->
                <a class="nav-link active" href="./"><i class="bi bi-house me-2"></i> Overview</a>
                <a class="nav-link" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a>
                <?php if (($settings['affiliate_program_enabled'] ?? '0') == '1'): ?>
                <a class="nav-link" href="affiliate.php"><i class="bi bi-share me-2"></i> Affiliate</a>
                <?php endif; ?>
                <?php if (($settings['api_enabled'] ?? '0') == '1'): ?>
                <a class="nav-link" href="api.php"><i class="bi bi-code-slash me-2"></i> API Access</a>
                <?php endif; ?>
                <a class="nav-link" href="billing.php"><i class="bi bi-credit-card me-2"></i> Billing</a>
                <a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a>
                <hr class="my-3" style="border-color: rgba(255,255,255,0.2);">
                <a class="nav-link" href="support.php"><i class="bi bi-question-circle me-2"></i> Support</a>
                <?php if ($user['is_admin']): ?>
                <a class="nav-link" href="../admin/"><i class="bi bi-shield-lock me-2"></i> Admin Panel</a>
                <?php endif; ?>
                <a class="nav-link" href="../login/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
            </nav>
        </div>
    </div>
</div>

<!-- Main Content Column (Navbar + Page Content) -->
<div class="col-12 col-md-9 col-lg-10">
    <div class="main-content">

        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
            <div class="container-fluid">
                <!-- Offcanvas toggle for mobile -->
                <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                    <i class="bi bi-list"></i>
                </button>

                <!-- Brand -->
                <a class="navbar-brand" href="../">
                    <?php echo htmlspecialchars($settings['site_name'] ?? 'PhPstrap'); ?>
                </a>

                <!-- User Info -->
                <div class="d-flex align-items-center ms-auto">
                    <div class="user-avatar me-3">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                    </div>
                </div>
            </div>
        </nav>



          
                    <!-- Dashboard Content -->
                    <div class="p-4">
                        <!-- Success/Error Messages -->
                        <?php if (isset($_SESSION['verification_success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['verification_success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['verification_success']); ?>
                        <?php endif; ?>
                        
                        <!-- Welcome Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h1 class="h3 mb-1">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                                <p class="text-muted">Here's what's happening with your account today.</p>
                            </div>
                        </div>
                        
                        <!-- Stats Cards -->
                        <div class="row mb-4">
                            <div class="col-12 col-sm-6 col-lg-3 mb-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-wallet2 display-4 mb-3"></i>
                                        <h3 class="card-title">$<?php echo number_format($user['credits'], 2); ?></h3>
                                        <p class="card-text">Account Balance</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-sm-6 col-lg-3 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-person-check display-4 mb-3 text-primary"></i>
                                        <h3 class="card-title"><?php echo $stats['affiliate_signups']; ?></h3>
                                        <p class="card-text text-muted">Referrals</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-sm-6 col-lg-3 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-cursor-fill display-4 mb-3 text-success"></i>
                                        <h3 class="card-title"><?php echo $stats['affiliate_clicks']; ?></h3>
                                        <p class="card-text text-muted">Affiliate Clicks</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-sm-6 col-lg-3 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-trophy display-4 mb-3 text-warning"></i>
                                        <h3 class="card-title">$<?php echo number_format($stats['total_commission'], 2); ?></h3>
                                        <p class="card-text text-muted">Earned</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Account Information -->
                            <div class="col-12 col-lg-8 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-info-circle me-2"></i>Account Information
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Membership Status:</strong></p>
                                                <span class="membership-badge membership-<?php echo $user['membership_status']; ?>">
                                                    <?php echo ucfirst($user['membership_status']); ?>
                                                </span>
                                                
                                                <?php if ($user['membership_expiry']): ?>
                                                <p class="mt-3 mb-0"><strong>Expires:</strong> <?php echo date('M j, Y', strtotime($user['membership_expiry'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Member Since:</strong> <?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                                                <p><strong>Last Login:</strong> 
                                                    <?php echo $user['last_login_at'] ? date('M j, Y g:i A', strtotime($user['last_login_at'])) : 'Never'; ?>
                                                </p>
                                                <p><strong>Verification Status:</strong> 
                                                    <?php if ($user['verified']): ?>
                                                        <span class="badge bg-success">✅ Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">⏳ Pending</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <?php if ($user['affiliate_id']): ?>
                                        <hr>
                                        <div class="row">
                                            <div class="col-12">
                                                <p><strong>Your Affiliate Link:</strong></p>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" 
                                                           value="<?php echo htmlspecialchars(($settings['site_url'] ?? '') . '/register.php?ref=' . $user['affiliate_id']); ?>" 
                                                           id="affiliateLink" readonly>
                                                    <button class="btn btn-outline-primary" type="button" onclick="copyAffiliateLink()">
                                                        <i class="bi bi-clipboard"></i> Copy
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recent Activity -->
                            <div class="col-12 col-lg-4 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-clock-history me-2"></i>Recent Logins
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($recent_logins)): ?>
                                            <p class="text-muted">No recent login activity.</p>
                                        <?php else: ?>
                                            <?php foreach ($recent_logins as $login): ?>
                                            <div class="activity-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, g:i A', strtotime($login['created_at'])); ?>
                                                        </small>
                                                        <br>
                                                        <small class="text-muted">
                                                            IP: <?php echo htmlspecialchars($login['ip_address']); ?>
                                                        </small>
                                                    </div>
                                                    <i class="bi bi-check-circle text-success"></i>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-lightning me-2"></i>Quick Actions
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6 col-md-3 mb-3">
                                                <a href="profile.php" class="btn btn-outline-primary w-100">
                                                    <i class="bi bi-person d-block mb-2" style="font-size: 1.5rem;"></i>
                                                    Edit Profile
                                                </a>
                                            </div>
                                            <div class="col-6 col-md-3 mb-3">
                                                <a href="billing.php" class="btn btn-outline-success w-100">
                                                    <i class="bi bi-credit-card d-block mb-2" style="font-size: 1.5rem;"></i>
                                                    Upgrade Plan
                                                </a>
                                            </div>
                                            <?php if (($settings['api_enabled'] ?? '0') == '1'): ?>
                                            <div class="col-6 col-md-3 mb-3">
                                                <a href="api.php" class="btn btn-outline-info w-100">
                                                    <i class="bi bi-code-slash d-block mb-2" style="font-size: 1.5rem;"></i>
                                                    API Keys
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                            <div class="col-6 col-md-3 mb-3">
                                                <a href="support.php" class="btn btn-outline-warning w-100">
                                                    <i class="bi bi-question-circle d-block mb-2" style="font-size: 1.5rem;"></i>
                                                    Get Help
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function copyAffiliateLink() {
            const affiliateLink = document.getElementById('affiliateLink');
            affiliateLink.select();
            affiliateLink.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand('copy');
                // Show success feedback
                const button = event.target.closest('button');
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check"></i> Copied!';
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-success');
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-primary');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy: ', err);
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        console.log('PhPstrap Dashboard loaded successfully');
    </script>
</body>
</html>