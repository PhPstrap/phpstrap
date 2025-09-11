<?php
// dashboard/affiliate.php — Affiliate dashboard (stats, link, recent activity, withdrawals)
require_once '../config/app.php';
require_once '../config/functions.php';

session_start();

// Require login
if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
    header('Location: ../login/');
    exit();
}

/* =======================
   CSRF + helpers
   ======================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_check($token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}
function money_fmt($v) { return number_format((float)$v, 2); }

/**
 * Fetch specific settings by keys from the `settings` table (casts by `type`).
 */
function fetch_settings_by_keys(PDO $pdo, array $keys): array {
    if (!$keys) return [];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $sql = "SELECT `key`,`value`,`type`,`default_value` FROM settings WHERE `key` IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($keys);
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $k = $row['key'];
        $type = $row['type'] ?: 'string';
        $valRaw = $row['value'];
        $defRaw = $row['default_value'];
        $raw = ($valRaw !== null && $valRaw !== '') ? $valRaw : $defRaw;

        switch ($type) {
            case 'boolean':
                $v = is_string($raw)
                    ? in_array(strtolower(trim($raw)), ['1','true','on','yes'], true)
                    : (bool)$raw;
                break;
            case 'integer':
                $v = (int)$raw;
                break;
            case 'json':
            case 'array':
                $v = json_decode((string)$raw, true);
                if ($v === null && json_last_error() !== JSON_ERROR_NONE) $v = [];
                break;
            case 'text':
            case 'string':
            default:
                $v = (string)$raw;
                break;
        }
        $out[$k] = $v;
    }
    return $out;
}

/* =======================
   Load user
   ======================= */
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        session_destroy();
        header('Location: ../login/');
        exit();
    }
} catch (PDOException $e) {
    logError("Affiliate load user error: " . $e->getMessage());
    header('Location: ../login/');
    exit();
}

/* =======================
   Load settings
   ======================= */
try {
    $keys = [
        'site_name','theme_color','secondary_color','site_icon','site_url',
        'affiliate_program_enabled','affiliate_commission_rate','credit_per_signup',
        'min_withdrawal_amount','withdrawal_fee','affiliate_cookie_lifetime'
    ];
    $settings = fetch_settings_by_keys($pdo, $keys);
} catch (PDOException $e) {
    logError("Affiliate load settings error: " . $e->getMessage());
    $settings = [];
}

$site_name   = $settings['site_name']   ?? 'PhPstrap';
$theme_color = $settings['theme_color'] ?? '#0d6efd';
$site_url    = rtrim($settings['site_url'] ?? '', '/');
$program_on  = !empty($settings['affiliate_program_enabled']);

$commission_rate_pct = (float)($settings['affiliate_commission_rate'] ?? '0'); // % display only
$credit_per_signup   = (float)($settings['credit_per_signup'] ?? '0.00');
$cookie_days         = (int)($settings['affiliate_cookie_lifetime'] ?? 30);
$min_withdrawal      = (float)($settings['min_withdrawal_amount'] ?? '50.00');
$withdrawal_fee      = (float)($settings['withdrawal_fee'] ?? '0.00'); // fixed fee

/* =======================
   Actions: generate affiliate_id / request withdrawal
   ======================= */
$flashes = [];
$push = function($type,$msg) use (&$flashes){ $flashes[] = ['type'=>$type,'msg'=>$msg]; };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!csrf_check($token)) {
        $push('danger', 'Security check failed. Please refresh and try again.');
    } else {
        if ($action === 'generate_affiliate_id') {
            if (!empty($user['affiliate_id'])) {
                $push('info', 'You already have an affiliate ID.');
            } else {
                // create unique 8-char alnum code
                $attempts = 0;
                do {
                    $attempts++;
                    $code = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars; you can switch to custom alphabet
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE affiliate_id = ?");
                    $stmt->execute([$code]);
                    $exists = $stmt->fetchColumn();
                } while ($exists && $attempts < 5);

                if (!empty($exists)) {
                    $push('danger', 'Could not generate a unique affiliate ID. Please try again.');
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET affiliate_id = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$code, $user['id']]);
                        $user['affiliate_id'] = $code;
                        $push('success', 'Affiliate ID generated successfully.');
                    } catch (PDOException $e) {
                        logError("Generate affiliate_id error: " . $e->getMessage());
                        $push('danger', 'Could not create affiliate ID right now.');
                    }
                }
            }
        }

        if ($action === 'request_withdrawal') {
            $amount = (float)($_POST['amount'] ?? 0);
            $method = trim((string)($_POST['method'] ?? 'paypal'));
            // Capture method-specific details (simple example)
            $details = [];
            if ($method === 'paypal') {
                $details['paypal_email'] = trim((string)($_POST['paypal_email'] ?? ''));
            } elseif ($method === 'bank') {
                $details['bank_name']   = trim((string)($_POST['bank_name'] ?? ''));
                $details['account_name']= trim((string)($_POST['account_name'] ?? ''));
                $details['iban']        = trim((string)($_POST['iban'] ?? ''));
                $details['swift']       = trim((string)($_POST['swift'] ?? ''));
            }

            // Compute available balance first (approved commissions - completed withdrawals)
            $approved = 0.0; $withdrawn = 0.0;
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(commission_amount),0) FROM affiliate_signups WHERE user_id = ? AND status = 'approved'");
                $stmt->execute([$user['id']]);
                $approved = (float)$stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount),0) FROM withdrawals WHERE user_id = ? AND status IN ('completed','processing','pending')");
                $stmt->execute([$user['id']]);
                $withdrawn = (float)$stmt->fetchColumn();
            } catch (PDOException $e) {
                logError("Compute available balance error: " . $e->getMessage());
            }
            $available = max(0, $approved - $withdrawn);

            // Validation
            if ($amount <= 0) {
                $push('warning', 'Please enter a valid withdrawal amount.');
            } elseif ($amount < $min_withdrawal) {
                $push('warning', 'Minimum withdrawal is $' . money_fmt($min_withdrawal) . '.');
            } elseif ($amount > $available) {
                $push('warning', 'You can withdraw up to $' . money_fmt($available) . ' right now.');
            } else {
                $fee = min($withdrawal_fee, $amount);
                $net = max(0, $amount - $fee);
                try {
                    $stmt = $pdo->prepare("INSERT INTO withdrawals 
                        (user_id, amount, fee, net_amount, currency, method, payment_details, status, request_time) 
                        VALUES (?, ?, ?, ?, 'USD', ?, ?, 'pending', NOW())");
                    $stmt->execute([
                        $user['id'], $amount, $fee, $net, $method, json_encode($details, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
                    ]);
                    $push('success', 'Withdrawal request submitted for $' . money_fmt($amount) . '. We’ll process it shortly.');
                } catch (PDOException $e) {
                    logError("Create withdrawal error: " . $e->getMessage());
                    $push('danger', 'Could not submit withdrawal request right now.');
                }
            }
        }
    }
}

/* =======================
   Stats
   ======================= */
$stats = [
    'clicks' => 0,
    'signups' => 0,
    'commission_pending' => 0.0,
    'commission_approved' => 0.0,
    'commission_paid' => 0.0,
    'available' => 0.0,
];

$recent_clicks = [];
$recent_signups = [];
$recent_withdrawals = [];

try {
    // Clicks & Signups count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_clicks WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $stats['clicks'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_signups WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $stats['signups'] = (int)$stmt->fetchColumn();

    // Commissions by status
    $stmt = $pdo->prepare("SELECT status, COALESCE(SUM(commission_amount),0) AS total FROM affiliate_signups WHERE user_id = ? GROUP BY status");
    $stmt->execute([$user['id']]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $s = $row['status'];
        $total = (float)$row['total'];
        if ($s === 'pending')  $stats['commission_pending']  = $total;
        if ($s === 'approved') $stats['commission_approved'] = $total;
        if ($s === 'paid')     $stats['commission_paid']     = $total;
    }

    // Available to withdraw: approved - withdrawals (pending/processing/completed)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount),0) FROM withdrawals WHERE user_id = ? AND status IN ('pending','processing','completed')");
    $stmt->execute([$user['id']]);
    $already_withdrawn_or_held = (float)$stmt->fetchColumn();
    $stats['available'] = max(0, $stats['commission_approved'] - $already_withdrawn_or_held);

    // Recent activity
    $stmt = $pdo->prepare("SELECT * FROM affiliate_clicks WHERE user_id = ? ORDER BY click_time DESC LIMIT 10");
    $stmt->execute([$user['id']]);
    $recent_clicks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT s.*, u.email AS referred_email 
                           FROM affiliate_signups s 
                           LEFT JOIN users u ON u.id = s.referred_user_id
                           WHERE s.user_id = ? 
                           ORDER BY s.signup_time DESC LIMIT 10");
    $stmt->execute([$user['id']]);
    $recent_signups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY request_time DESC LIMIT 10");
    $stmt->execute([$user['id']]);
    $recent_withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    logError("Affiliate stats error: " . $e->getMessage());
}

/* =======================
   View vars
   ======================= */
$ref_link = '';
if (!empty($user['affiliate_id']) && !empty($site_url)) {
    $ref_link = $site_url . '/register.php?ref=' . urlencode($user['affiliate_id']);
}

$currentPage = 'affiliate'; // for active nav
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Affiliate — <?= htmlspecialchars($site_name) ?></title>

  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --primary-color: <?= htmlspecialchars($theme_color) ?>; }
    body { background: #f5f7fb; }
    .navbar .navbar-brand { font-weight: 700; color: var(--primary-color); }
    .card { border: none; border-radius: 1rem; box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,.05); }
    .stat-card { transition: transform .15s ease, box-shadow .15s ease; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15); }
    .table-sm td, .table-sm th { padding-top: .5rem; padding-bottom: .5rem; }
  </style>
</head>
<body>
  <div class="container-fluid">
    <div class="row g-0">

      <?php include __DIR__ . '/includes/nav.php'; ?>

      <main class="col-12 col-lg-10">
        <!-- Topbar -->
        <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
          <div class="container-fluid">
            <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
              <i class="bi bi-list"></i>
            </button>
            <a class="navbar-brand" href="../"><?= htmlspecialchars($site_name) ?></a>
            <div class="ms-auto d-flex align-items-center">
              <div class="rounded-circle d-flex align-items-center justify-content-center me-2 text-white"
                   style="width:40px;height:40px;background:var(--primary-color);font-weight:700;">
                <?= strtoupper(substr($user['name'] ?? $user['email'], 0, 1)) ?>
              </div>
              <div class="small">
                <div class="fw-semibold"><?= htmlspecialchars($user['name'] ?? $user['email']) ?></div>
                <div class="text-muted"><?= htmlspecialchars($user['email']) ?></div>
              </div>
            </div>
          </div>
        </nav>

        <div class="p-4">

          <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show" role="alert">
              <?= $f['msg'] ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endforeach; ?>

          <?php if (!$program_on): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>
              The affiliate program is currently disabled. Please check back later.
            </div>
          <?php endif; ?>

          <div class="row g-3 align-items-stretch">
            <!-- Stat cards -->
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card stat-card h-100">
                <div class="card-body text-center">
                  <i class="bi bi-cursor-fill display-6 text-success"></i>
                  <h3 class="mt-2 mb-0"><?= (int)$stats['clicks'] ?></h3>
                  <div class="text-muted small">Clicks</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card stat-card h-100">
                <div class="card-body text-center">
                  <i class="bi bi-person-check display-6 text-primary"></i>
                  <h3 class="mt-2 mb-0"><?= (int)$stats['signups'] ?></h3>
                  <div class="text-muted small">Signups</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card stat-card h-100">
                <div class="card-body text-center">
                  <i class="bi bi-hourglass-split display-6 text-warning"></i>
                  <h3 class="mt-2 mb-0">$<?= money_fmt($stats['commission_pending']) ?></h3>
                  <div class="text-muted small">Pending</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card stat-card h-100">
                <div class="card-body text-center">
                  <i class="bi bi-trophy display-6 text-success"></i>
                  <h3 class="mt-2 mb-0">$<?= money_fmt($stats['available']) ?></h3>
                  <div class="text-muted small">Available</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Referral link / program info -->
          <div class="row g-3 mt-1">
            <div class="col-12 col-xl-8">
              <div class="card">
                <div class="card-header">
                  <strong><i class="bi bi-share me-2"></i>Your Referral Link</strong>
                </div>
                <div class="card-body">
                  <?php if (empty($user['affiliate_id'])): ?>
                    <p class="text-muted mb-3">Generate your affiliate ID to start sharing and earning.</p>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="action" value="generate_affiliate_id">
                      <button class="btn btn-primary" type="submit"><i class="bi bi-gear-wide-connected me-1"></i> Generate Affiliate ID</button>
                    </form>
                  <?php elseif (empty($ref_link)): ?>
                    <div class="alert alert-warning mb-0">We couldn’t build your link (missing Site URL). Please contact support.</div>
                  <?php else: ?>
                    <div class="input-group">
                      <input type="text" id="refLink" class="form-control" value="<?= htmlspecialchars($ref_link) ?>" readonly>
                      <button class="btn btn-outline-primary" type="button" id="copyRef"><i class="bi bi-clipboard"></i> Copy</button>
                    </div>
                    <div class="small text-muted mt-2">
                      Cookie lasts <strong><?= (int)$cookie_days ?></strong> days. Commission rate: <strong><?= money_fmt($commission_rate_pct) ?>%</strong>. Credits per signup: <strong><?= money_fmt($credit_per_signup) ?></strong>.
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Withdraw -->
            <div class="col-12 col-xl-4">
              <div class="card">
                <div class="card-header d-flex align-items-center">
                  <strong><i class="bi bi-cash-coin me-2"></i>Withdraw</strong>
                  <span class="ms-auto small text-muted">Min: $<?= money_fmt($min_withdrawal) ?> · Fee: $<?= money_fmt($withdrawal_fee) ?></span>
                </div>
                <div class="card-body">
                  <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="request_withdrawal">

                    <div class="mb-2">
                      <label class="form-label">Amount</label>
                      <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" min="0" class="form-control" name="amount" placeholder="<?= money_fmt($stats['available']) ?>">
                      </div>
                      <div class="form-text">Available: $<?= money_fmt($stats['available']) ?></div>
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Method</label>
                      <select class="form-select" name="method" id="method">
                        <option value="paypal">PayPal</option>
                        <option value="bank">Bank Transfer</option>
                      </select>
                    </div>

                    <div class="mb-2 method-paypal">
                      <label class="form-label">PayPal Email</label>
                      <input type="email" class="form-control" name="paypal_email" placeholder="you@example.com">
                    </div>

                    <div class="d-none method-bank">
                      <div class="mb-2">
                        <label class="form-label">Bank Name</label>
                        <input type="text" class="form-control" name="bank_name">
                      </div>
                      <div class="mb-2">
                        <label class="form-label">Account Holder</label>
                        <input type="text" class="form-control" name="account_name">
                      </div>
                      <div class="mb-2">
                        <label class="form-label">IBAN</label>
                        <input type="text" class="form-control" name="iban">
                      </div>
                      <div class="mb-2">
                        <label class="form-label">SWIFT/BIC</label>
                        <input type="text" class="form-control" name="swift">
                      </div>
                    </div>

                    <div class="d-grid">
                      <button class="btn btn-primary" type="submit" <?= ($stats['available'] < $min_withdrawal ? 'disabled' : '') ?>>
                        Submit Request
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

          </div>

          <!-- Recent activity (Clicks / Signups / Withdrawals) -->
          <div class="row g-3 mt-1">
            <div class="col-12 col-xl-6">
              <div class="card">
                <div class="card-header"><strong><i class="bi bi-mouse3 me-2"></i>Recent Clicks</strong></div>
                <div class="card-body">
                  <?php if (!$recent_clicks): ?>
                    <div class="text-muted">No clicks yet.</div>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>Time</th>
                            <th>IP</th>
                            <th>Referrer</th>
                            <th>Device</th>
                          </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_clicks as $c): ?>
                          <tr>
                            <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($c['click_time'] ?? 'now'))) ?></td>
                            <td><span class="text-monospace"><?= htmlspecialchars($c['ip_address'] ?? '-') ?></span></td>
                            <td class="text-truncate" style="max-width:240px;" title="<?= htmlspecialchars($c['referrer'] ?? '-') ?>">
                              <?= htmlspecialchars($c['referrer'] ?? '-') ?>
                            </td>
                            <td><?= htmlspecialchars($c['device_type'] ?? '-') ?></td>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="col-12 col-xl-6">
              <div class="card">
                <div class="card-header"><strong><i class="bi bi-person-plus me-2"></i>Recent Signups</strong></div>
                <div class="card-body">
                  <?php if (!$recent_signups): ?>
                    <div class="text-muted">No signups yet.</div>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>Time</th>
                            <th>Referred</th>
                            <th>Status</th>
                            <th>Commission</th>
                          </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_signups as $s): ?>
                          <tr>
                            <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($s['signup_time'] ?? 'now'))) ?></td>
                            <td><?= htmlspecialchars($s['referred_email'] ?? ('User#'.$s['referred_user_id'])) ?></td>
                            <td>
                              <?php
                                $badge = 'secondary';
                                if ($s['status']==='approved') $badge='success';
                                if ($s['status']==='pending')  $badge='warning text-dark';
                                if ($s['status']==='paid')     $badge='info';
                                if ($s['status']==='cancelled')$badge='danger';
                              ?>
                              <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst($s['status'])) ?></span>
                            </td>
                            <td>$<?= money_fmt($s['commission_amount'] ?? 0) ?></td>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="card">
                <div class="card-header"><strong><i class="bi bi-receipt me-2"></i>Recent Withdrawals</strong></div>
                <div class="card-body">
                  <?php if (!$recent_withdrawals): ?>
                    <div class="text-muted">No withdrawals yet.</div>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>Requested</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Fee</th>
                            <th>Net</th>
                            <th>Status</th>
                          </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_withdrawals as $w): ?>
                          <tr>
                            <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($w['request_time'] ?? 'now'))) ?></td>
                            <td><?= htmlspecialchars(ucfirst($w['method'])) ?></td>
                            <td>$<?= money_fmt($w['amount']) ?></td>
                            <td>$<?= money_fmt($w['fee']) ?></td>
                            <td>$<?= money_fmt($w['net_amount']) ?></td>
                            <td>
                              <?php
                                $b = 'secondary';
                                if ($w['status']==='pending')     $b='warning text-dark';
                                if ($w['status']==='processing')  $b='info';
                                if ($w['status']==='completed')   $b='success';
                                if ($w['status']==='failed')      $b='danger';
                                if ($w['status']==='cancelled')   $b='dark';
                              ?>
                              <span class="badge bg-<?= $b ?>"><?= htmlspecialchars(ucfirst($w['status'])) ?></span>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                      </table>
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

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Copy referral link
    document.getElementById('copyRef')?.addEventListener('click', function (e) {
      const input = document.getElementById('refLink');
      if (!input) return;
      input.select();
      input.setSelectionRange(0, 99999);
      try {
        document.execCommand('copy');
        const btn = e.currentTarget;
        const prev = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        setTimeout(() => { btn.innerHTML = prev; btn.classList.remove('btn-success'); btn.classList.add('btn-outline-primary'); }, 1600);
      } catch {}
    });

    // Toggle withdrawal method details
    const methodSel = document.getElementById('method');
    const paypalBox = document.querySelector('.method-paypal');
    const bankBox   = document.querySelector('.method-bank');
    function updateMethodUI() {
      const v = methodSel?.value || 'paypal';
      if (paypalBox) paypalBox.classList.toggle('d-none', v !== 'paypal');
      if (bankBox)   bankBox.classList.toggle('d-none',   v !== 'bank');
    }
    methodSel?.addEventListener('change', updateMethodUI);
    updateMethodUI();

    // Auto-dismiss alerts
    setTimeout(() => {
      document.querySelectorAll('.alert-dismissible').forEach(el => {
        try { new bootstrap.Alert(el).close(); } catch (e) {}
      });
    }, 5000);
  </script>
</body>
</html>