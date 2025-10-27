<?php
// dashboard/lexboard_upload.php — Upload decisions (PDF) using CLI tools (pdftotext/tesseract) for extraction
require_once '../config/app.php';
require_once '../config/functions.php';

session_start();

// Require login
if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
    header('Location: ../login/');
    exit();
}

/* =======================
   Helpers
   ======================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_check($token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
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
    logError("LexBoard upload load user error: " . $e->getMessage());
    header('Location: ../login/');
    exit();
}

/* =======================
   Load settings/flags
   ======================= */
function fetch_settings_by_keys(PDO $pdo, array $keys): array {
    if (!$keys) return [];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $sql = "SELECT `key`,`value`,`type`,`default_value` FROM settings WHERE `key` IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($keys);
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $k = $row['key'];
        $raw = ($row['value'] !== null && $row['value'] !== '') ? $row['value'] : $row['default_value'];
        $out[$k] = $raw;
    }
    return $out;
}

try {
    $keys = ['site_name','theme_color','secondary_color','site_icon','site_url'];
    $settings = fetch_settings_by_keys($pdo, $keys);
} catch (PDOException $e) {
    logError("LexBoard upload load settings error: " . $e->getMessage());
    $settings = [];
}

$site_name   = $settings['site_name']   ?? 'PhPstrap';
$theme_color = $settings['theme_color'] ?? '#0d6efd';

// Flash messages
$flashes = [];
if (!empty($_SESSION['lex_err'])) {
    $flashes[] = ['type' => 'danger', 'msg' => htmlspecialchars($_SESSION['lex_err'])];
    unset($_SESSION['lex_err']);
}

// Highlight in nav
$currentPage = 'lex_upload';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LexBoard — Upload | <?= htmlspecialchars($site_name) ?></title>
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
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
            <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
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
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endforeach; ?>

          <div class="row g-3">
            <div class="col-12 col-xl-8">
              <div class="card">
                <div class="card-header d-flex align-items-center">
                  <strong><i class="bi bi-file-earmark-arrow-up me-2"></i>Upload a decision (PDF)</strong>
                  <span class="ms-auto small text-muted">LexBoard</span>
                </div>
                <div class="card-body">
                  <form action="lexboard_review.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-3">
                      <label class="form-label">PDF file</label>
                      <input type="file" class="form-control" name="decision_pdf" accept="application/pdf" required>
                      <div class="form-text">PDF only, max 10 MB. We’ll extract text for AI analysis.</div>
                    </div>

                    <div class="row g-3">
                      <div class="col-md-4">
                        <label class="form-label">Jurisdiction</label>
                        <select name="jurisdiction_code" class="form-select" required>
                          <option value="ON" selected>Ontario</option>
                          <option disabled>— Other provinces coming soon</option>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Court</label>
                        <select name="court_code" class="form-select">
                          <option value="">Auto-detect</option>
                          <option value="ONSC">ONSC</option>
                          <option value="ONCA">ONCA</option>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Source URL (optional)</label>
                        <input type="url" class="form-control" name="source_url" placeholder="CanLII or court URL">
                      </div>
                    </div>

                    <div class="form-check mt-3">
                      <input class="form-check-input" type="checkbox" value="1" id="tryOcr" name="try_ocr" checked>
                      <label class="form-check-label" for="tryOcr">
                        Try OCR if text extraction fails (uses pdftoppm + tesseract)
                      </label>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                      <button class="btn btn-primary" type="submit">
                        <i class="bi bi-upload me-1"></i> Upload & Analyze
                      </button>
                      <a href="./" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Tips -->
            <div class="col-12 col-xl-4">
              <div class="card">
                <div class="card-header"><strong><i class="bi bi-info-circle me-2"></i>Tips</strong></div>
                <div class="card-body">
                  <ul class="small text-muted ps-3 mb-0">
                    <li>Native PDFs work best — scans use OCR.</li>
                    <li>You can correct extracted fields on the next step.</li>
                    <li>Link to the CanLII page if possible.</li>
                    <li>Supports pdftotext and tesseract on Linux/Unix servers.</li>
                  </ul>
                </div>
              </div>

              <div class="card mt-3">
                <div class="card-header"><strong><i class="bi bi-gear-wide-connected me-2"></i>Status</strong></div>
                <div class="card-body">
                  <p class="small text-muted mb-2">Current ingestion mode:</p>
                  <span class="badge text-bg-secondary">Manual upload</span>
                  <p class="small text-muted mt-3 mb-0">RSS/API integration coming soon.</p>
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
    document.querySelectorAll('.needs-validation').forEach(form=>{
      form.addEventListener('submit',e=>{
        if(!form.checkValidity()){e.preventDefault();e.stopPropagation();}
        form.classList.add('was-validated');
      });
    });
    setTimeout(()=>document.querySelectorAll('.alert-dismissible')
      .forEach(el=>{try{new bootstrap.Alert(el).close();}catch(e){};}),5000);
  </script>
</body>
</html>