<?php
// dashboard/api.php — API token management for end users
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
function fmt_money($v){ return number_format((float)$v, 2); }

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
                $v = is_string($raw) ? in_array(strtolower(trim($raw)), ['1','true','on','yes'], true) : (bool)$raw;
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
    logError("API load user error: " . $e->getMessage());
    header('Location: ../login/');
    exit();
}

/* =======================
   Load settings
   ======================= */
try {
    $keys = [
        'site_name','theme_color','secondary_color','site_icon','site_url',
        'api_enabled',
        // optional:
        'api_user_reset_enabled',          // let users reset their own usage window (optional)
        'api_docs_url',                    // link to external docs (optional)
        'api_rate_limit_per_minute'        // display-only, if you store it (optional)
    ];
    $settings = fetch_settings_by_keys($pdo, $keys);
} catch (PDOException $e) {
    logError("API settings load error: " . $e->getMessage());
    $settings = [];
}

$site_name   = $settings['site_name']   ?? 'PhPstrap';
$theme_color = $settings['theme_color'] ?? '#0d6efd';
$api_enabled = !empty($settings['api_enabled']);
$api_docs    = $settings['api_docs_url'] ?? '';
$allow_user_reset = !empty($settings['api_user_reset_enabled']);

/* =======================
   Actions
   ======================= */
$flashes = [];
$push = function($type,$msg) use (&$flashes){ $flashes[] = ['type'=>$type,'msg'=>$msg]; };

function generate_unique_token(PDO $pdo): string {
    // Prefix for clarity; 48 hex chars ~ 24 bytes entropy
    for ($i=0; $i<6; $i++) {
        $candidate = 'psk_' . bin2hex(random_bytes(24));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ?");
        $stmt->execute([$candidate]);
        if (!$stmt->fetchColumn()) return $candidate;
    }
    // Extremely unlikely
    throw new RuntimeException("Could not generate unique token");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!csrf_check($token)) {
        $push('danger', 'Security check failed. Please refresh and try again.');
    } elseif (!$api_enabled && in_array($action, ['generate','rotate','revoke','reset_usage'], true)) {
        $push('warning', 'API is disabled by the administrator.');
    } else {
        try {
            if ($action === 'generate') {
                if (!empty($user['api_token'])) {
                    $push('info', 'You already have an API token. Use Rotate to create a new one.');
                } else {
                    $new = generate_unique_token($pdo);
                    $stmt = $pdo->prepare("UPDATE users SET api_token = ?, api_token_usage_count = 0, api_token_reset_time = NOW(), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new, $user['id']]);
                    $user['api_token'] = $new;
                    $user['api_token_usage_count'] = 0;
                    $user['api_token_reset_time'] = date('Y-m-d H:i:s');
                    $push('success', 'API token generated.');
                }
            }

            if ($action === 'rotate') {
                if (empty($user['api_token'])) {
                    $push('info', 'You don’t have a token yet. Click Generate.');
                } else {
                    $new = generate_unique_token($pdo);
                    $stmt = $pdo->prepare("UPDATE users SET api_token = ?, api_token_usage_count = 0, api_token_reset_time = NOW(), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$new, $user['id']]);
                    $user['api_token'] = $new;
                    $user['api_token_usage_count'] = 0;
                    $user['api_token_reset_time'] = date('Y-m-d H:i:s');
                    $push('success', 'Your API token was rotated.');
                }
            }

            if ($action === 'revoke') {
                if (empty($user['api_token'])) {
                    $push('info', 'No token to revoke.');
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET api_token = NULL, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $user['api_token'] = null;
                    $push('success', 'Your API token was revoked.');
                }
            }

            if ($action === 'reset_usage') {
                if (!$allow_user_reset) {
                    $push('warning', 'Usage reset is disabled by the administrator.');
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET api_token_usage_count = 0, api_token_reset_time = NOW(), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $user['api_token_usage_count'] = 0;
                    $user['api_token_reset_time'] = date('Y-m-d H:i:s');
                    $push('success', 'Usage window reset.');
                }
            }
        } catch (Throwable $e) {
            logError("API action error: " . $e->getMessage());
            $push('danger', 'Something went wrong. Please try again.');
        }
    }
}

/* =======================
   Derived view data
   ======================= */
$usage_count = (int)($user['api_token_usage_count'] ?? 0);
$usage_max   = (int)($user['api_token_max_usage'] ?? 1000);
$reset_time  = $user['api_token_reset_time'] ?? null;

$usage_percent = $usage_max > 0 ? min(100, round(($usage_count / $usage_max) * 100)) : 0;

$currentPage = 'api'; // highlight in nav
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>API Access — <?= htmlspecialchars($site_name) ?></title>

  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --primary-color: <?= htmlspecialchars($theme_color) ?>; }
    body { background: #f5f7fb; }
    .navbar .navbar-brand { font-weight: 700; color: var(--primary-color); }
    .card { border: none; border-radius: 1rem; box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,.05); }
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
          <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show" role="alert">
              <?= $f['msg'] ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endforeach; ?>

          <?php if (!$api_enabled): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>
              API access is currently disabled. Please check back later.
            </div>
          <?php endif; ?>

          <div class="row g-3">
            <!-- Token management -->
            <div class="col-12 col-xl-7">
              <div class="card">
                <div class="card-header d-flex align-items-center">
                  <strong><i class="bi bi-key me-2"></i>API Token</strong>
                  <?php if ($api_docs): ?>
                    <a class="ms-auto small" href="<?= htmlspecialchars($api_docs) ?>" target="_blank" rel="noopener">View API Docs <i class="bi bi-box-arrow-up-right ms-1"></i></a>
                  <?php endif; ?>
                </div>
                <div class="card-body">
                  <?php if (empty($user['api_token'])): ?>
                    <p class="text-muted">Generate your personal API token to authenticate requests.</p>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="action" value="generate">
                      <button class="btn btn-primary" type="submit" <?= !$api_enabled ? 'disabled' : '' ?>>
                        <i class="bi bi-stars me-1"></i> Generate Token
                      </button>
                    </form>
                  <?php else: ?>
                    <div class="mb-3">
                      <label class="form-label">Your Token</label>
                      <div class="input-group">
                        <input type="password" id="apiToken" class="form-control mono" value="<?= htmlspecialchars($user['api_token']) ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="toggleToken"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-outline-primary" type="button" id="copyToken"><i class="bi bi-clipboard"></i> Copy</button>
                      </div>
                      <div class="form-text">Treat this like a password. Rotate it immediately if exposed.</div>
                    </div>

                    <div class="d-flex gap-2">
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="rotate">
                        <button class="btn btn-warning" type="submit" <?= !$api_enabled ? 'disabled' : '' ?>>
                          <i class="bi bi-arrow-repeat me-1"></i> Rotate
                        </button>
                      </form>
                      <form method="post" onsubmit="return confirm('Revoke this token? Any apps using it will stop working.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="revoke">
                        <button class="btn btn-outline-danger" type="submit" <?= !$api_enabled ? 'disabled' : '' ?>>
                          <i class="bi bi-trash3 me-1"></i> Revoke
                        </button>
                      </form>
                      <?php if ($allow_user_reset): ?>
                        <form method="post" class="ms-auto">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                          <input type="hidden" name="action" value="reset_usage">
                          <button class="btn btn-outline-secondary" type="submit" <?= !$api_enabled ? 'disabled' : '' ?>>
                            <i class="bi bi-clock-history me-1"></i> Reset Usage
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Usage / limits -->
            <div class="col-12 col-xl-5">
              <div class="card">
                <div class="card-header">
                  <strong><i class="bi bi-activity me-2"></i>Usage & Limits</strong>
                </div>
                <div class="card-body">
                  <div class="d-flex justify-content-between small">
                    <span>Usage</span>
                    <span><?= (int)$usage_count ?> / <?= (int)$usage_max ?></span>
                  </div>
                  <div class="progress mb-2" role="progressbar" aria-label="Usage" style="height:8px;">
                    <div class="progress-bar <?= $usage_percent>=90?'bg-danger':($usage_percent>=70?'bg-warning':'bg-success') ?>" style="width: <?= $usage_percent ?>%"></div>
                  </div>
                  <div class="small text-muted mb-2">Resets: <?= $reset_time ? htmlspecialchars(date('M j, Y g:i A', strtotime($reset_time))) : '—' ?></div>

                  <?php if (!empty($settings['api_rate_limit_per_minute'])): ?>
                    <div class="small mb-0"><strong>Rate limit:</strong> <?= (int)$settings['api_rate_limit_per_minute'] ?> req/min</div>
                  <?php else: ?>
                    <div class="small mb-0 text-muted">Rate limiting information will appear here if configured.</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Quickstart -->
            <div class="col-12">
              <div class="card">
                <div class="card-header"><strong><i class="bi bi-lightning me-2"></i>Quickstart</strong></div>
                <div class="card-body">
<pre class="bg-light p-3 rounded small mb-3"><code class="mono"># cURL example
curl -H "Authorization: Bearer <?= htmlspecialchars($user['api_token'] ?? 'YOUR_TOKEN_HERE') ?>" \
     -H "Content-Type: application/json" \
     -X POST \
     -d '{"example":"ping"}' \
     <?= htmlspecialchars(($settings['site_url'] ?? '').'/api/v1/endpoint') ?>

# PHP example (Guzzle)
$client = new \GuzzleHttp\Client();
$res = $client->post('<?= htmlspecialchars(($settings['site_url'] ?? '').'/api/v1/endpoint') ?>', [
  'headers' => ['Authorization' => 'Bearer <?= htmlspecialchars($user['api_token'] ?? 'YOUR_TOKEN_HERE') ?>'],
  'json'    => ['example' => 'ping'],
]);
echo $res->getBody();</code></pre>
                  <div class="text-muted small">Replace <span class="mono">/api/v1/endpoint</span> with your actual endpoint(s).</div>
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
    // Toggle token visibility
    document.getElementById('toggleToken')?.addEventListener('click', function() {
      const inp = document.getElementById('apiToken');
      if (!inp) return;
      inp.type = (inp.type === 'password') ? 'text' : 'password';
      this.innerHTML = (inp.type === 'password') ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
    });

    // Copy token
    document.getElementById('copyToken')?.addEventListener('click', function(e) {
      const inp = document.getElementById('apiToken');
      if (!inp) return;
      inp.type = 'text';
      inp.select();
      inp.setSelectionRange(0, 99999);
      try {
        document.execCommand('copy');
        const btn = e.currentTarget;
        const prev = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        setTimeout(() => { btn.innerHTML = prev; btn.classList.remove('btn-success'); btn.classList.add('btn-outline-primary'); }, 1600);
      } catch {}
      setTimeout(() => { inp.type = 'password'; }, 200);
    });

    // Auto-dismiss alerts
    setTimeout(() => {
      document.querySelectorAll('.alert-dismissible').forEach(el => {
        try { new bootstrap.Alert(el).close(); } catch (e) {}
      });
    }, 5000);
  </script>
</body>
</html>