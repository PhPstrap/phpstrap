<?php
// dashboard/lexboard_review.php — Review AI extraction with DeepSeek + fallback, same template as settings.php
require_once '../config/app.php';
require_once '../config/functions.php';

session_start();

// Require login
if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
    header('Location: ../login/');
    exit();
}

/* =======================
   Helpers (mirror settings.php style)
   ======================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_check($token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}

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
            case 'integer': $v = (int)$raw; break;
            case 'json':
            case 'array':
                $v = json_decode((string)$raw, true);
                if ($v === null && json_last_error() !== JSON_ERROR_NONE) $v = [];
                break;
            case 'text':
            case 'string':
            default: $v = (string)$raw; break;
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
    logError("lexboard_review auth: " . $e->getMessage());
    header('Location: ../login/');
    exit();
}

/* =======================
   Load settings/flags for theming
   ======================= */
try {
    $keys = [
        'site_name','theme_color','secondary_color','site_icon','site_url',
        'affiliate_program_enabled','api_enabled','lexboard_enabled'
    ];
    $settings = fetch_settings_by_keys($pdo, $keys);
} catch (PDOException $e) {
    logError("LexBoard review load settings error: " . $e->getMessage());
    $settings = [];
}
$site_name   = $settings['site_name']   ?? 'PhPstrap';
$theme_color = $settings['theme_color'] ?? '#0d6efd';

// Highlight the LexBoard Upload entry
$currentPage = 'lex_upload';

/* =======================
   Validate POST + CSRF
   ======================= */
$flashes = [];
$push = function($type,$msg) use (&$flashes){ $flashes[] = ['type'=>$type,'msg'=>$msg]; };

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['lex_err'] = 'Invalid request method.';
    header('Location: lexboard_upload.php');
    exit();
}
if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $_SESSION['lex_err'] = 'Security check failed. Please try again.';
    header('Location: lexboard_upload.php');
    exit();
}

/* =======================
   Input + basic file checks
   ======================= */
require_once __DIR__.'/../lib/pdf_text.php';
require_once __DIR__.'/../lib/ai_outcome.php';     // fallback rules (regex/heuristics)
require_once __DIR__.'/../lib/ai_deepseek.php';    // DeepSeek integration

$errors = [];
if (!isset($_FILES['decision_pdf']) || $_FILES['decision_pdf']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Please choose a PDF file.';
}

$jurCode   = $_POST['jurisdiction_code'] ?? 'ON';
$courtCode = $_POST['court_code'] ?? '';
$sourceUrl = trim($_POST['source_url'] ?? '');

if ($errors) {
    $_SESSION['lex_err'] = implode(' ', $errors);
    header('Location: lexboard_upload.php');
    exit();
}

$f = $_FILES['decision_pdf'];
if ($f['size'] > 10*1024*1024) {
    $_SESSION['lex_err'] = 'File too large (max 10MB).';
    header('Location: lexboard_upload.php'); exit();
}
$mime = mime_content_type($f['tmp_name']);
if (stripos($mime, 'pdf') === false) {
    $_SESSION['lex_err'] = 'File must be PDF.';
    header('Location: lexboard_upload.php'); exit();
}

/* =======================
   Store file
   ======================= */
$uploadsDir = __DIR__ . '/../storage/uploads';
if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);
$basename = preg_replace('/[^a-zA-Z0-9_\.-]/','_', basename($f['name']));
$dest = $uploadsDir.'/'.time().'_'.$basename;
if (!move_uploaded_file($f['tmp_name'], $dest)) {
    $_SESSION['lex_err'] = 'Could not store uploaded file.';
    header('Location: lexboard_upload.php'); exit();
}

/* =======================
   Extract text
   ======================= */
$text = lb_extract_pdf_text($dest);
if (strlen($text) < 200) {
    $_SESSION['lex_err'] = 'Could not extract enough text (add OCR later).';
    @unlink($dest);
    header('Location: lexboard_upload.php'); exit();
}

/* =======================
   Heuristics: judge + court + counsel
   ======================= */
function lb_guess_court_from_text(string $t, string $jurCode): ?string {
    $U = strtoupper($t);
    if ($jurCode === 'ON') {
        if (str_contains($U, 'COURT OF APPEAL FOR ONTARIO') || preg_match('/\bONCA\b/', $U)) return 'ONCA';
        // default to ONSC for Ontario if nothing else detected
        return 'ONSC';
    }
    return null;
}

function lb_extract_judge(string $t): ?string {
    // Try “Before: …” “Judge: …” “Justice …” lines and trailing “— J.” signatures
    $lines = preg_split("/\R/u", $t);
    foreach ($lines as $ln) {
        if (preg_match('/^\s*(Before|Judge|Justice|The Honourable|Hon\.?)\s*[:\-]\s*(.+)$/iu', $ln, $m)) {
            $name = trim($m[2]);
            $name = preg_replace('/\s+(J\.|J\.)$/', '', $name);
            if ($name) return $name;
        }
        if (preg_match('/\b(Justice|J\.)\b\s+([A-Z][\p{L}\.\- ]+)/u', $ln, $m)) {
            $name = trim($m[2]);
            if ($name) return $name;
        }
    }
    if (preg_match('/^\s*([A-Z][\p{L}\.\- ]+)\s+J\.\s*$/um', $t, $m)) {
        return trim($m[1]);
    }
    return null;
}

function lb_extract_counsel_pairs(string $t): array {
    // Finds “X for the Y” style chunks and “Counsel:” lines
    $pairs = [];
    $sideMap = [
        'applicant'  => 'applicant',
        'respondent' => 'respondent',
        'plaintiff'  => 'plaintiff',
        'defendant'  => 'defendant',
        'appellant'  => 'appellant',
        'respondent on appeal' => 'respondent_on_appeal',
        'moving party' => 'moving_party',
        'intervener' => 'intervener',
    ];

    $lines = preg_split("/\R/u", $t);
    foreach ($lines as $ln) {
        // split multiple clauses separated by ; or bullets
        $parts = preg_split('/[;•]+/u', $ln);
        foreach ($parts as $frag) {
            if (preg_match('/^\s*(.+?)\s+for\s+the\s+([a-z ]+)\s*$/iu', trim($frag), $m)) {
                $lawyers  = trim($m[1]);
                $sideRaw  = strtolower(trim($m[2]));
                $sideEnum = $sideMap[$sideRaw] ?? 'unknown';

                // multiple lawyers separated by " and " or ","
                $cands = preg_split('/\s+and\s+|,\s*/u', $lawyers);
                foreach ($cands as $c) {
                    $c = trim($c, " \t\n\r\0\x0B,;");
                    if ($c === '' || preg_match('/^for\s+/i', $c)) continue;
                    $name = $c; $firm = null;
                    // try “Jane Doe, XYZ LLP”
                    if (preg_match('/^(.+?),\s*(.*LLP\.?|.*LLP)$/u', $c, $mm)) {
                        $name = trim($mm[1]); $firm = trim($mm[2]);
                    }
                    $pairs[] = ['lawyer_name'=>$name, 'firm_name'=>$firm, 'side'=>$sideEnum];
                }
            }
        }
    }
    if (!$pairs) {
        if (preg_match('/^\s*Counsel\s*:\s*(.+)$/imu', $t, $m)) {
            $blob = $m[1];
            $chunks = preg_split('/[;•]+/u', $blob);
            foreach ($chunks as $c) {
                $c = trim($c);
                if ($c==='') continue;
                $sideEnum = 'unknown';
                if (preg_match('/(.+?)\s+for\s+the\s+([a-z ]+)$/iu', $c, $mm)) {
                    $c = trim($mm[1]); $sideRaw = strtolower(trim($mm[2]));
                    $sideEnum = $sideMap[$sideRaw] ?? 'unknown';
                }
                $name = $c; $firm = null;
                if (preg_match('/^(.+?),\s*(.*LLP\.?|.*LLP)$/u', $c, $mm2)) {
                    $name = trim($mm2[1]); $firm = trim($mm2[2]);
                }
                $pairs[] = ['lawyer_name'=>$name, 'firm_name'=>$firm, 'side'=>$sideEnum];
            }
        }
    }
    // de-dup by (name,side)
    $uniq = []; $out = [];
    foreach ($pairs as $p) {
        $k = mb_strtolower(($p['lawyer_name'] ?? '') . '|' . ($p['side'] ?? ''));
        if ($k && !isset($uniq[$k])) { $uniq[$k]=1; $out[]=$p; }
    }
    return $out;
}

// derive missing court from text when jurisdiction is ON
if (empty($courtCode)) {
    $auto = lb_guess_court_from_text($text, $jurCode);
    if ($auto) $courtCode = $auto;
}

/* =======================
   AI analysis: DeepSeek → fallback merge
   ======================= */
$hints = ['jurisdiction'=>$jurCode,'court'=>$courtCode,'source_url'=>$sourceUrl];
$deep     = lex_ai_analyze_case_with_deepseek($pdo, $text, $hints);
$fallback = lb_ai_classify_outcome($text);

// strengthen judge
$deep['judge'] = $deep['judge'] ?: lb_extract_judge($text);

// parse counsel
$counselPairs  = lb_extract_counsel_pairs($text);

// Merge: prefer DeepSeek values when present & meaningful
$merged = $fallback;
foreach ($deep as $k => $v) {
    if ($k === '_meta') { $merged['_meta'] = $v; continue; }
    if ($v === null || $v === '' || $v === 'unknown') continue;
    $merged[$k] = $v;
}

// Defaults for form
$neutral    = $merged['neutral_citation']  ?? '';
$caseName   = $merged['case_name']         ?? '';
$judge      = $merged['judge']             ?? '';
$decDate    = $merged['decision_date']     ?? '';
$confidence = $merged['confidence']        ?? 0.0;
$label      = $merged['outcome_label']     ?? '';

// Base64 counsel payload for safe POST (WAF-friendly)
$counsel_b64 = base64_encode(json_encode($counselPairs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LexBoard — Review | <?= htmlspecialchars($site_name) ?></title>

  <!-- Bootstrap 5 CSS -->
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root { --primary-color: <?= htmlspecialchars($theme_color) ?>; }
    body { background: #f5f7fb; }
    .navbar .navbar-brand { font-weight: 700; color: var(--primary-color); }
    .card { border: none; border-radius: 1rem; box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,.05); }
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

        <!-- Content -->
        <div class="p-4">

          <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show" role="alert">
              <?= $f['msg'] ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endforeach; ?>

          <div class="row justify-content-center">
            <div class="col-lg-10">
              <div class="card border-0">
                <div class="card-header d-flex align-items-center">
                  <strong><i class="bi bi-robot me-2"></i>Review AI extraction</strong>
                  <span class="ms-auto small text-muted">
                    Engine:
                    <?php if (!empty($merged['_meta']['provider']) && $merged['_meta']['provider']==='deepseek'): ?>
                      <span class="badge text-bg-primary">DeepSeek</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">Rules</span>
                    <?php endif; ?>
                  </span>
                </div>
                <div class="card-body">
                  <div class="alert alert-info">Verify and correct fields before saving.</div>

                  <form method="post" action="lexboard_save.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="pdf_path" value="<?= htmlspecialchars($dest) ?>">
                    <input type="hidden" name="source_url" value="<?= htmlspecialchars($sourceUrl) ?>">
                    <input type="hidden" name="jurisdiction_code" value="<?= htmlspecialchars($jurCode) ?>">
                    <input type="hidden" name="court_code" value="<?= htmlspecialchars($courtCode ?: 'ONSC') ?>">
                    <input type="hidden" name="ai_confidence" value="<?= htmlspecialchars((string)$confidence) ?>">
                    <input type="hidden" name="counsel_payload_b64" value="<?= htmlspecialchars($counsel_b64) ?>">

                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label">Neutral citation</label>
                        <input class="form-control" name="neutral_citation" value="<?= htmlspecialchars($neutral) ?>" required>
                        <div class="invalid-feedback">Neutral citation is required.</div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Case name</label>
                        <input class="form-control" name="case_name" value="<?= htmlspecialchars($caseName) ?>" required>
                        <div class="invalid-feedback">Case name is required.</div>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Decision date</label>
                        <input type="date" class="form-control" name="decision_date" value="<?= htmlspecialchars($decDate) ?>">
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Judge</label>
                        <input class="form-control" name="judge" value="<?= htmlspecialchars($judge) ?>">
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Proceeding</label>
                        <select name="proceeding_type" class="form-select">
                          <?php $opts=['motion','application','trial','appeal','other']; foreach($opts as $o){ $sel=$o===($merged['proceeding_type']??'other')?'selected':''; echo "<option $sel>$o</option>"; } ?>
                        </select>
                      </div>

                      <div class="col-md-4">
                        <label class="form-label">Outcome (normalized)</label>
                        <select name="outcome_norm" class="form-select">
                          <?php $o2=['granted','dismissed','allowed','varied','partial','unknown']; foreach($o2 as $o){ $sel=$o===($merged['outcome_norm']??'unknown')?'selected':''; echo "<option $sel>$o</option>"; } ?>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Winner side</label>
                        <select name="winner_side" class="form-select">
                          <?php $w=['applicant','respondent','plaintiff','defendant','appellant','respondent_on_appeal','moving_party','other','unknown']; foreach($w as $o){ $sel=$o===($merged['winner_side']??'unknown')?'selected':''; echo "<option $sel>$o</option>"; } ?>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Disposition scope</label>
                        <select name="disposition_scope" class="form-select">
                          <?php $sc=['full','partial','mixed','unknown']; foreach($sc as $o){ $sel=$o===($merged['disposition_scope']??'unknown')?'selected':''; echo "<option $sel>$o</option>"; } ?>
                        </select>
                      </div>

                      <div class="col-md-6">
                        <label class="form-label">Costs order</label>
                        <select name="costs_order" class="form-select">
                          <?php $co=['none','to_moving','to_responding','reserved','other']; foreach($co as $o){ $sel=$o===($merged['costs_order']??'other')?'selected':''; echo "<option $sel>$o</option>"; } ?>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Costs amount</label>
                        <input type="number" step="0.01" class="form-control" name="costs_amount" value="<?= htmlspecialchars((string)($merged['costs_amount']??'')) ?>">
                      </div>

                      <div class="col-12">
                        <label class="form-label">Outcome label (human)</label>
                        <input class="form-control" name="outcome_label" value="<?= htmlspecialchars($label) ?>">
                      </div>

                      <div class="col-12">
                        <label class="form-label">AI confidence</label>
                        <input type="text" class="form-control-plaintext" readonly value="<?= number_format(($confidence??0)*100,1) ?>%">
                      </div>

                      <div class="col-12">
                        <label class="form-label">Detected counsel (preview)</label>
                        <div class="form-control-plaintext small">
                          <?php if (!empty($counselPairs)): ?>
                            <?php foreach ($counselPairs as $cp): ?>
                              <div>• <?= htmlspecialchars($cp['lawyer_name']) ?><?= !empty($cp['firm_name']) ? ' — '.htmlspecialchars($cp['firm_name']) : '' ?> (<?= htmlspecialchars($cp['side']) ?>)</div>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <em class="text-muted">None detected</em>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                      <button class="btn btn-success" type="submit">
                        <i class="bi bi-check2-circle me-1"></i> Save to Database
                      </button>
                      <a href="lexboard_upload.php" class="btn btn-outline-secondary">Back</a>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Optional: raw file info -->
              <div class="card mt-3">
                <div class="card-body small text-muted">
                  <div>Stored file: <code><?= htmlspecialchars(basename($dest)) ?></code></div>
                  <?php if ($sourceUrl): ?>
                    <div>Source URL: <a href="<?= htmlspecialchars($sourceUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($sourceUrl) ?></a></div>
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
    // Bootstrap validation
    document.querySelectorAll('.needs-validation').forEach(form => {
      form.addEventListener('submit', evt => {
        if (!form.checkValidity()) { evt.preventDefault(); evt.stopPropagation(); }
        form.classList.add('was-validated');
      });
    });

    // Auto-dismiss alerts
    setTimeout(() => {
      document.querySelectorAll('.alert-dismissible').forEach(el => {
        try { new bootstrap.Alert(el).close(); } catch(e){}
      });
    }, 5000);
  </script>
</body>
</html>