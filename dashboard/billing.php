<?php
// dashboard/billing.php — Credits-focused billing page (no payment processing yet)
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
    logError("Billing load user error: " . $e->getMessage());
    header('Location: ../login/');
    exit();
}

/* =======================
   Load settings
   ======================= */
try {
    $keys = ['site_name','theme_color','secondary_color','site_icon','site_url'];
    $settings = fetch_settings_by_keys($pdo, $keys);
} catch (PDOException $e) {
    logError("Billing settings error: " . $e->getMessage());
    $settings = [];
}

$site_name   = $settings['site_name']   ?? 'PhPstrap';
$theme_color = $settings['theme_color'] ?? '#0d6efd';

/* =======================
   Fetch credit-related data
   ======================= */
$credit_balance = (float)($user['credits'] ?? 0.0); // users.credits

// Token purchases (history only; payments not implemented here)
$token_purchases = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM token_purchases WHERE user_id = ? ORDER BY purchase_date DESC LIMIT 25");
    $stmt->execute([$user['id']]);
    $token_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { logError("Billing token_purchases list error: " . $e->getMessage()); }

// Affiliate rollups (display context; credits top-up not automated here)
$aff = ['pending'=>0.0,'approved'=>0.0,'paid'=>0.0];
try {
    $stmt = $pdo->prepare("SELECT status, COALESCE(SUM(commission_amount),0) AS total 
                           FROM affiliate_signups 
                           WHERE user_id = ?
                           GROUP BY status");
    $stmt->execute([$user['id']]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $s = $r['status'];
        $t = (float)$r['total'];
        if ($s === 'pending')  $aff['pending']  = $t;
        if ($s === 'approved') $aff['approved'] = $t;
        if ($s === 'paid')     $aff['paid']     = $t;
    }
} catch (PDOException $e) { logError("Billing affiliate rollup error: " . $e->getMessage()); }

$currentPage = 'billing';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Billing — <?= htmlspecialchars($site_name) ?></title>

  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --primary-color: <?= htmlspecialchars($theme_color) ?>; }
    body { background: #f5f7fb; }
    .navbar .navbar-brand { font-weight: 700; color: var(--primary-color); }
    .card { border: none; border-radius: 1rem; box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,.05); }
    .stat-card { transition: transform .15s ease, box-shadow .15s ease; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.12); }
    .table-sm td, .table-sm th { padding-top: .5rem; padding-bottom: .5rem; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
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
          <!-- Headline -->
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <h1 class="h4 mb-1">Billing</h1>
              <div class="text-muted">Credits overview & history (payments coming soon)</div>
            </div>
          </div>

          <!-- Stat cards -->
          <div class="row g-3 align-items-stretch">
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card stat-card h-100">
                <div class="card-body text-center">
                  <i class="bi bi-wallet2 display-6 text-primary"></i>
                  <h3 class="mt-2 mb-0">$<?= money_fmt($credit_balance) ?></h3>
                  <div class="text-muted small">Current Credits</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card stat-card h-100">
                <div class="card-body text-center">
                  <i class="bi bi-hourglass-split display-6 text-warning"></i>
                  <h3 class="mt-2 mb-0">$<?= money_fmt($aff['pending']) ?></h3>
                  <div class="text-muted small">Affiliate Pending</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card stat-card h-100">
                <div class="card-body text-center">
                  <i class="bi bi-check2-circle display-6 text-success"></i>
                  <h3 class="mt-2 mb-0">$<?= money_fmt($aff['approved']) ?></h3>
                  <div class="text-muted small">Affiliate Approved</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
              <div class="card stat-card h-100">
                <div class="card-body text-center">
                  <i class="bi bi-trophy display-6 text-info"></i>
                  <h3 class="mt-2 mb-0">$<?= money_fmt($aff['paid']) ?></h3>
                  <div class="text-muted small">Affiliate Paid</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions (placeholders until modules are added) -->
          <div class="row g-3 mt-1">
            <div class="col-12 col-xl-8">
              <div class="card">
                <div class="card-header d-flex align-items-center">
                  <strong><i class="bi bi-plus-circle me-2"></i>Add Credits</strong>
                  <span class="ms-auto small text-muted">Payment modules coming soon</span>
                </div>
                <div class="card-body">
                  <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    We’re finalizing card/PayPal integrations. For now, contact support to top up credits.
                  </div>
                  <a href="support.php" class="btn btn-outline-primary"><i class="bi bi-chat-dots me-1"></i> Contact Support</a>
                </div>
              </div>
            </div>

            <div class="col-12 col-xl-4">
              <div class="card">
                <div class="card-header"><strong><i class="bi bi-lightbulb me-2"></i>Tips</strong></div>
                <div class="card-body">
                  <ul class="small text-muted mb-0 ps-3">
                    <li>Credits are shown as a dollar value in your account.</li>
                    <li>Affiliate earnings may be paid out or (later) converted to credits—stay tuned.</li>
                    <li>Need an invoice for accounting? Ask us in Support.</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <!-- History -->
          <div class="row g-3 mt-1">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <strong><i class="bi bi-clock-history me-2"></i>History</strong>
                </div>
                <div class="card-body">
                  <?php if (empty($token_purchases)): ?>
                    <div class="text-muted">No purchases recorded yet.</div>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>Date</th>
                            <th>Pack</th>
                            <th>Tokens</th>
                            <th>Price</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th class="d-none d-lg-table-cell">Txn ID</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($token_purchases as $p): ?>
                            <tr>
                              <td><?= htmlspecialchars($p['purchase_date'] ? date('M j, Y g:i A', strtotime($p['purchase_date'])) : '-') ?></td>
                              <td><?= htmlspecialchars($p['token_pack']) ?></td>
                              <td class="mono"><?= (int)$p['tokens_amount'] ?></td>
                              <td>$<?= money_fmt($p['price']) ?></td>
                              <td><?= htmlspecialchars(ucfirst($p['method'])) ?></td>
                              <td>
                                <?php
                                  $badge = 'secondary';
                                  if ($p['status']==='completed') $badge='success';
                                  if ($p['status']==='pending')   $badge='warning text-dark';
                                  if ($p['status']==='failed')    $badge='danger';
                                  if ($p['status']==='refunded')  $badge='info';
                                  if ($p['status']==='cancelled') $badge='dark';
                                ?>
                                <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst($p['status'])) ?></span>
                              </td>
                              <td class="d-none d-lg-table-cell mono"><?= htmlspecialchars($p['transaction_id'] ?? '-') ?></td>
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
    // Auto-dismiss alerts
    setTimeout(() => {
      document.querySelectorAll('.alert-dismissible').forEach(el => {
        try { new bootstrap.Alert(el).close(); } catch (e) {}
      });
    }, 5000);
  </script>
</body>
</html>