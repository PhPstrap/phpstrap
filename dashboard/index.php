<?php
// Include existing PhPstrap configuration
require_once '../config/app.php';
require_once '../config/functions.php';

// Start session with proper settings
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: ../login/');
    exit();
}

// Load user
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

// Stats + recent logins
$stats = [];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS click_count FROM affiliate_clicks WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $stats['affiliate_clicks'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['click_count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) AS signup_count FROM affiliate_signups WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $stats['affiliate_signups'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['signup_count'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount),0) AS total_commission
                           FROM affiliate_signups WHERE user_id = ? AND status = 'paid'");
    $stmt->execute([$user['id']]);
    $stats['total_commission'] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_commission'];

    $stmt = $pdo->prepare("SELECT created_at, ip_address FROM login_logs
                           WHERE email = ? AND success = 1
                           ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user['email']]);
    $recent_logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    logError("Dashboard stats error: " . $e->getMessage());
    $stats = ['affiliate_clicks' => 0, 'affiliate_signups' => 0, 'total_commission' => 0.0];
    $recent_logins = [];
}

// Settings
$settings = [];
try {
    if (function_exists('getSetting')) {
        foreach (['site_name','theme_color','secondary_color','site_icon','site_url','affiliate_program_enabled','api_enabled'] as $k) {
            $settings[$k] = getSetting($k, '');
        }
    } else {
        $stmt = $pdo->query("SELECT `key`,`value` FROM settings WHERE is_public = 1");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[$row['key']] = $row['value'];
        }
    }
} catch(PDOException $e) {
    logError("Dashboard settings error: " . $e->getMessage());
    $settings = [
        'site_name' => 'PhPstrap',
        'theme_color' => '#0d6efd',
        'secondary_color' => '#6c757d',
        'site_icon' => 'bi bi-speedometer2',
        'site_url' => '',
        'affiliate_program_enabled' => '1',
        'api_enabled' => '1',
    ];
}

// For nav highlighting (optional override)
$currentPage = 'overview';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — <?= htmlspecialchars($settings['site_name'] ?? 'PhPstrap') ?></title>

  <!-- Bootstrap 5 CSS -->
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Font Awesome (optional) -->
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
          <?php if (isset($_SESSION['verification_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['verification_success']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['verification_success']); ?>
          <?php endif; ?>

          <div class="mb-4">
            <h1 class="h3 mb-1">Welcome back, <?= htmlspecialchars($user['name']) ?>!</h1>
            <p class="text-muted mb-0">Here’s what’s happening with your account.</p>
          </div>

          <!-- Stats -->
          <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card hover-up text-center">
                <div class="card-body">
                  <i class="bi bi-wallet2 display-5 d-block mb-2 text-primary"></i>
                  <div class="stat-number">$<?= number_format((float)($user['credits'] ?? 0), 2) ?></div>
                  <div class="text-muted">Account Balance</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card hover-up text-center">
                <div class="card-body">
                  <i class="bi bi-person-check display-5 d-block mb-2"></i>
                  <div class="stat-number"><?= (int)$stats['affiliate_signups'] ?></div>
                  <div class="text-muted">Referrals</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card hover-up text-center">
                <div class="card-body">
                  <i class="bi bi-cursor-fill display-5 d-block mb-2"></i>
                  <div class="stat-number"><?= (int)$stats['affiliate_clicks'] ?></div>
                  <div class="text-muted">Affiliate Clicks</div>
                </div>
              </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card hover-up text-center">
                <div class="card-body">
                  <i class="bi bi-trophy display-5 d-block mb-2"></i>
                  <div class="stat-number">$<?= number_format((float)$stats['total_commission'], 2) ?></div>
                  <div class="text-muted">Earned</div>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <!-- Account information -->
            <div class="col-12 col-xl-8">
              <div class="card">
                <div class="card-header">
                  <strong><i class="bi bi-info-circle me-2"></i>Account Information</strong>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-6">
                      <p class="mb-1 fw-semibold">Membership Status</p>
                      <?php
                        $m = strtolower($user['membership_status'] ?? 'free');
                        $badge = [
                          'free' => 'bg-light text-body',
                          'premium' => 'bg-warning-subtle text-warning-emphasis',
                          'lifetime' => 'bg-success-subtle text-success-emphasis'
                        ][$m] ?? 'bg-light text-body';
                      ?>
                      <span class="badge rounded-pill <?= $badge ?> px-3 py-2 text-uppercase">
                        <?= htmlspecialchars(ucfirst($m)) ?>
                      </span>

                      <?php if (!empty($user['membership_expiry'])): ?>
                        <p class="mt-3 mb-0"><strong>Expires:</strong> <?= date('M j, Y', strtotime($user['membership_expiry'])) ?></p>
                      <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                      <p class="mb-1"><strong>Member Since:</strong> <?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                      <p class="mb-1"><strong>Last Login:</strong>
                        <?= !empty($user['last_login_at']) ? date('M j, Y g:i A', strtotime($user['last_login_at'])) : 'Never' ?>
                      </p>
                      <p class="mb-0"><strong>Verification:</strong>
                        <?= !empty($user['verified']) ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning text-dark">Pending</span>' ?>
                      </p>
                    </div>
                  </div>

                  <?php if (!empty($user['affiliate_id'])): ?>
                    <hr>
                    <div>
                      <p class="fw-semibold mb-2">Your Affiliate Link</p>
                      <div class="input-group">
                        <input id="affiliateLink" type="text" class="form-control"
                               value="<?= htmlspecialchars(($settings['site_url'] ?? '') . '/register.php?ref=' . $user['affiliate_id']) ?>" readonly>
                        <button class="btn btn-outline-primary" type="button" id="copyAffiliateBtn">
                          <i class="bi bi-clipboard"></i> Copy
                        </button>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Recent logins -->
            <div class="col-12 col-xl-4">
              <div class="card">
                <div class="card-header">
                  <strong><i class="bi bi-clock-history me-2"></i>Recent Logins</strong>
                </div>
                <div class="card-body">
                  <?php if (!$recent_logins): ?>
                    <p class="text-muted mb-0">No recent login activity.</p>
                  <?php else: ?>
                    <?php foreach ($recent_logins as $login): ?>
                      <div class="d-flex justify-content-between align-items-start border rounded p-2 mb-2">
                        <div class="small text-muted">
                          <?= date('M j, g:i A', strtotime($login['created_at'])) ?><br>
                          IP: <?= htmlspecialchars($login['ip_address']) ?>
                        </div>
                        <i class="bi bi-check-circle text-success"></i>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick actions -->
          <div class="card mt-3">
            <div class="card-header">
              <strong><i class="bi bi-lightning me-2"></i>Quick Actions</strong>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-6 col-md-3">
                  <a href="profile.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-person d-block mb-2" style="font-size:1.5rem;"></i>
                    Edit Profile
                  </a>
                </div>
                <div class="col-6 col-md-3">
                  <a href="billing.php" class="btn btn-outline-success w-100">
                    <i class="bi bi-credit-card d-block mb-2" style="font-size:1.5rem;"></i>
                    Upgrade Plan
                  </a>
                </div>
                <?php if (($settings['api_enabled'] ?? '0') === '1'): ?>
                <div class="col-6 col-md-3">
                  <a href="api.php" class="btn btn-outline-info w-100">
                    <i class="bi bi-code-slash d-block mb-2" style="font-size:1.5rem;"></i>
                    API Keys
                  </a>
                </div>
                <?php endif; ?>
                <div class="col-6 col-md-3">
                  <a href="support.php" class="btn btn-outline-warning w-100">
                    <i class="bi bi-question-circle d-block mb-2" style="font-size:1.5rem;"></i>
                    Get Help
                  </a>
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
    // Copy affiliate link
    const copyBtn = document.getElementById('copyAffiliateBtn');
    if (copyBtn) {
      copyBtn.addEventListener('click', function() {
        const input = document.getElementById('affiliateLink');
        input.select(); input.setSelectionRange(0, 99999);
        document.execCommand('copy');
        const original = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
        copyBtn.classList.remove('btn-outline-primary');
        copyBtn.classList.add('btn-success');
        setTimeout(() => {
          copyBtn.innerHTML = original;
          copyBtn.classList.remove('btn-success');
          copyBtn.classList.add('btn-outline-primary');
        }, 1500);
      });
    }

    // Auto-dismiss alerts
    setTimeout(() => {
      document.querySelectorAll('.alert-dismissible').forEach(el => {
        try { new bootstrap.Alert(el).close(); } catch (e) {}
      });
    }, 5000);
  </script>
</body>
</html>