<?php
/**
 * PhPstrap Admin — Settings (Dynamic)
 * Keeps original functionality; only layout/nav updated to shared style.
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

/* -------------------------------- Includes -------------------------------- */
require_once '../config/database.php';
require_once '../config/app.php';

// Auth include (support multiple paths)
$auth_paths = ['admin-auth.php', 'includes/admin-auth.php', '../includes/admin-auth.php'];
foreach ($auth_paths as $path) { if (file_exists($path)) { require_once $path; break; } }

/* ------------------------------ App/Auth ---------------------------------- */
initializeApp();
if (function_exists('requireAdminAuth')) { requireAdminAuth(); }
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'settings.php'));
    exit;
}

$admin = [
    'id'    => $_SESSION['admin_id']    ?? 1,
    'name'  => $_SESSION['admin_name']  ?? 'Administrator',
    'email' => $_SESSION['admin_email'] ?? 'admin@example.com',
];

// CSRF
if (!isset($_SESSION['PhPstrap_csrf_token'])) {
    $_SESSION['PhPstrap_csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['PhPstrap_csrf_token'];

$message = '';
$error = false;
$fieldErrors = [];

/* -------------------------- Data access & helpers -------------------------- */

/**
 * Fetch full settings schema with values.
 * @return array{by_category: array<string, array>, list: array}
 */
function fetchSettingsSchema(PDO $pdo): array {
    $stmt = $pdo->query("SELECT id, `key`, `value`, `type`, `description`, `category`,
                                is_public, is_required, validation_rules, default_value, sort_order, updated_at
                         FROM settings
                         ORDER BY COALESCE(NULLIF(category,''),'general'), sort_order, `key`");
    $byCat = [];
    $list  = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['category']    = $row['category'] ?: 'general';
        $row['type']        = $row['type'] ?: 'string';
        $row['sort_order']  = (int)($row['sort_order'] ?? 0);
        $row['is_public']   = (int)($row['is_public'] ?? 0);
        $row['is_required'] = (int)($row['is_required'] ?? 0);
        // Effective (display) value falls back to default_value
        $row['_effective']  = ($row['value'] !== null && $row['value'] !== '') ? $row['value'] : $row['default_value'];

        $byCat[$row['category']][] = $row;
        $list[] = $row;
    }
    return ['by_category' => $byCat, 'list' => $list];
}

/**
 * Validation (type + rules: min, max, enum, regex; auto email/url by key name)
 * @return array{0: bool, 1: string}
 */
function validateSetting(array $field, $rawInput): array {
    $type = $field['type'] ?? 'string';
    $rules = [];
    if (!empty($field['validation_rules'])) {
        $tmp = json_decode($field['validation_rules'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) $rules = $tmp;
    }

    // Required (booleans handled separately)
    if ($type !== 'boolean' && !empty($field['is_required']) && ($rawInput === '' || $rawInput === null)) {
        return [false, 'This field is required.'];
    }

    switch ($type) {
        case 'integer':
            if ($rawInput === '' || $rawInput === null) $val = null;
            elseif (!is_numeric($rawInput) || (string)(int)$rawInput !== (string)trim($rawInput)) return [false, 'Please enter a valid integer.'];
            else $val = (int)$rawInput;
            if (isset($rules['min']) && $val !== null && $val < (int)$rules['min']) return [false, 'Value must be at least '.$rules['min'].'.'];
            if (isset($rules['max']) && $val !== null && $val > (int)$rules['max']) return [false, 'Value cannot exceed '.$rules['max'].'.'];
            return [true, ''];

        case 'boolean':
            return [true, ''];

        case 'json':
        case 'array':
            if ($rawInput === '' || $rawInput === null) return [true, ''];
            $decoded = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) return [false, 'Invalid JSON format.'];
            return [true, ''];

        case 'text':
        case 'string':
        default:
            $val = (string)$rawInput;

            if (stripos($field['key'], 'email') !== false && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                return [false, 'Please enter a valid email address.'];
            }
            if (stripos($field['key'], 'url') !== false && $val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                return [false, 'Please enter a valid URL.'];
            }
            if (isset($rules['min']) && mb_strlen($val) < (int)$rules['min']) return [false, 'Must be at least '.$rules['min'].' characters.'];
            if (isset($rules['max']) && mb_strlen($val) > (int)$rules['max']) return [false, 'Cannot exceed '.$rules['max'].' characters.'];
            if (isset($rules['enum']) && is_array($rules['enum']) && !in_array($val, $rules['enum'], true)) {
                return [false, 'Value must be one of: '.implode(', ', $rules['enum']).'.'];
            }
            if (isset($rules['regex']) && @preg_match('/'.$rules['regex'].'/', '') !== false) {
                if (!preg_match('/'.$rules['regex'].'/', $val)) return [false, 'Does not match required format.'];
            }
            return [true, ''];
    }
}

/** Normalize for DB storage by type */
function normalizeForStorage(string $type, $rawInput) {
    switch ($type) {
        case 'integer': return ($rawInput === '' || $rawInput === null) ? null : (string)((int)$rawInput);
        case 'boolean': return !empty($rawInput) ? '1' : '0';
        case 'json':
        case 'array':
            if ($rawInput === '' || $rawInput === null) return null;
            $decoded = json_decode($rawInput, true);
            return json_encode($decoded, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        default: return (string)$rawInput;
    }
}

/** Upsert single setting */
function upsertSetting(PDO $pdo, string $key, $value): bool {
    $stmt = $pdo->prepare("
        INSERT INTO settings (`key`, `value`, `updated_at`)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
    ");
    return $stmt->execute([$key, $value]);
}

/** Icons by category */
function getCategoryIcon(string $category): string {
    $icons = [
        'general'=>'fa-solid fa-gear',
        'system'=>'fa-solid fa-server',
        'users'=>'fa-solid fa-users',
        'security'=>'fa-solid fa-shield-halved',
        'affiliate'=>'fa-solid fa-share-nodes',
        'invites'=>'fa-solid fa-envelope',
        'localization'=>'fa-solid fa-globe',
        'appearance'=>'fa-solid fa-palette',
        'api'=>'fa-solid fa-code',
        'email'=>'fa-regular fa-envelope-open',
        'uploads'=>'fa-solid fa-upload',
        'backup'=>'fa-solid fa-database',
        'analytics'=>'fa-solid fa-chart-line',
        'performance'=>'fa-solid fa-gauge-high',
        'legal'=>'fa-solid fa-scale-balanced',
    ];
    return $icons[$category] ?? 'fa-regular fa-folder';
}

/* --------------------------- DB connection --------------------------- */
try { $pdo = getDbConnection(); }
catch (Throwable $e) { http_response_code(500); die('Database connection failed.'); }

/* ------------------------------- POST -------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action']) && $_POST['action'] === 'update_dynamic') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['PhPstrap_csrf_token'] ?? '', $_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.'; $error = true;
    } else {
        try {
            $schema  = fetchSettingsSchema($pdo);
            $errors  = [];
            $updated = 0;

            // Booleans: ensure unchecked => 0
            $booleanKeys = [];
            foreach ($schema['list'] as $field) {
                if (($field['type'] ?? 'string') === 'boolean') $booleanKeys[$field['key']] = true;
            }

            $posted = $_POST['s'] ?? [];
            foreach ($booleanKeys as $k => $_) {
                if (!array_key_exists($k, $posted)) $posted[$k] = '0';
            }

            foreach ($schema['list'] as $field) {
                $key  = $field['key'];
                $type = $field['type'] ?? 'string';
                $raw  = $posted[$key] ?? null;
                [$ok, $err] = validateSetting($field, $raw);
                if (!$ok) { $errors[$key] = $err; $fieldErrors[$key] = $err; continue; }
                $norm = normalizeForStorage($type, $raw);
                if (!upsertSetting($pdo, $key, $norm)) {
                    $errors[$key] = 'Failed to save.'; $fieldErrors[$key] = 'Failed to save.';
                } else { $updated++; }
            }

            if ($errors) { $message = 'Some fields could not be saved. Please review errors below.'; $error = true; }
            else { $message = "Settings updated successfully ($updated fields)."; $error = false; $fieldErrors = []; }

            if (function_exists('logAdminActivity')) {
                logAdminActivity('settings_update', ['category'=>'dynamic','updated'=>$updated,'has_errors'=>(bool)$error]);
            }
        } catch (Throwable $e) {
            $message = 'An error occurred while updating settings. Please try again.'; $error = true;
            error_log('Dynamic settings update error: '.$e->getMessage());
        }
    }
}

/* ---------------------------- Fetch schema ---------------------------- */
try { $schema = fetchSettingsSchema($pdo); }
catch (Throwable $e) { $schema = ['by_category'=>[],'list'=>[]]; $message = 'Failed to load settings schema.'; $error = true; }

/* --------------------------- Small view helpers --------------------------- */
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function effectiveValue(array $f){ return ($f['value'] !== null && $f['value'] !== '') ? $f['value'] : ($f['default_value'] ?? ''); }

/* ------------------------ Title + version for UI ------------------------ */
$siteName = 'PhPstrap Admin';
foreach (($schema['list'] ?? []) as $__row) {
    if (!empty($__row['key']) && $__row['key'] === 'site_name') {
        $siteName = ($__row['_effective'] ?? $__row['value'] ?? $__row['default_value'] ?? $siteName) ?: $siteName;
        break;
    }
}
$system_info = ['app_version'=>'1.0.0'];
try {
    $v = $pdo->query("SELECT value FROM settings WHERE `key`='app_version' LIMIT 1")->fetchColumn();
    if ($v) $system_info['app_version'] = $v;
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings - <?= e($siteName) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet"><!-- shared admin styles -->
<style>
/* page-specific polish */
.settings-card{border-radius:12px}
.settings-nav .nav-link{border:0;border-radius:.5rem}
.settings-nav .nav-link.active{background-color:#e9ecef}
.setting-field{background:#f8f9fa;border:1px solid #e9ecef;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1rem}
.setting-field.has-error{border-color:#dc3545;background:#fff5f5}
.key-label{font-family:ui-monospace, Menlo, Consolas, monospace}
.category-badge{display:inline-flex;align-items:center;padding:.25rem .6rem;border-radius:1rem;font-size:.75rem;background:#eef1ff;color:#3f51b5}
.sticky-save{position:sticky;bottom:0;z-index:5;background:#fff;border-top:1px solid #e9ecef;padding:12px}
</style>
</head>
<body class="bg-body-tertiary">
<div class="d-flex">
  <!-- Sidebar (shared include) -->
  <aside class="admin-sidebar bg-dark text-white">
    <div class="p-3 border-bottom border-secondary-subtle">
      <div class="d-flex align-items-center">
        <i class="fa-solid fa-shield-halved me-2"></i><strong>PhPstrap Admin</strong>
      </div>
      <div class="small text-secondary mt-1">v<?= e($system_info['app_version']) ?></div>
    </div>
<?php
  $activeKey     = 'settings';
  $sidebarBadges = [];
  $appVersion    = $system_info['app_version'] ?? '1.0.0';
  $adminName     = $admin['name'] ?? 'Admin';
  include __DIR__ . '/includes/admin-sidebar.php';
?>
  </aside>

  <!-- Main -->
  <main class="flex-grow-1">
    <!-- Header -->
    <header class="bg-white border-bottom">
      <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-link d-md-none p-0 me-1" onclick="toggleSidebar()" aria-label="Toggle sidebar">
              <i class="fa-solid fa-bars fa-lg text-body"></i>
            </button>
            <div>
              <h1 class="h4 mb-0">Settings</h1>
              <div class="small text-body-secondary mt-1"><i class="fa-solid fa-gear me-1"></i>System Configuration</div>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="text-body-secondary">Welcome, <strong><?= e($admin['name']) ?></strong></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
          </div>
        </div>
      </div>
    </header>

    <div class="admin-content container-fluid">
      <!-- Alerts -->
      <?php if ($message): ?>
        <div class="alert alert-<?= $error ? 'danger' : 'success' ?> alert-dismissible fade show my-3">
          <i class="fa-solid fa-<?= $error ? 'triangle-exclamation' : 'circle-check' ?> me-2"></i><?= e($message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm settings-card my-3">
        <div class="card-header bg-body-tertiary">
          <div class="d-flex align-items-center justify-content-between">
            <div class="fw-semibold"><i class="fa-solid fa-sliders me-2"></i>System Settings</div>
            <small class="text-body-secondary"><?= count($schema['list']) ?> total settings</small>
          </div>
        </div>

        <div class="card-body">
          <div class="row g-4">
            <!-- Left: categories -->
            <div class="col-md-3">
              <div class="settings-nav">
                <div class="nav flex-column nav-pills" id="settings-nav" role="tablist" aria-orientation="vertical">
                  <?php
                    $categories = array_keys($schema['by_category']);
                    if (!$categories) $categories = ['general'];
                    $first = true;
                    foreach ($categories as $cat):
                      $tabId = 'tab-'.preg_replace('/[^a-z0-9]+/i','-',$cat);
                      $count = count($schema['by_category'][$cat] ?? []);
                  ?>
                    <button class="nav-link text-start <?= $first?'active':'' ?>" id="<?= e($tabId) ?>-btn"
                            data-bs-toggle="pill" data-bs-target="#<?= e($tabId) ?>" type="button" role="tab">
                      <i class="<?= e(getCategoryIcon($cat)) ?> me-2"></i><?= e(ucfirst($cat)) ?>
                      <span class="badge text-bg-secondary ms-2"><?= $count ?></span>
                    </button>
                  <?php $first=false; endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Right: fields -->
            <div class="col-md-9">
              <form method="post" id="settingsForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($CSRF) ?>">
                <input type="hidden" name="action" value="update_dynamic">

                <div class="tab-content" id="settings-content">
                  <?php
                    $first = true;
                    foreach ($schema['by_category'] as $cat => $fields):
                      $tabId = 'tab-'.preg_replace('/[^a-z0-9]+/i','-',$cat);
                  ?>
                  <div class="tab-pane fade <?= $first?'show active':'' ?>" id="<?= e($tabId) ?>" role="tabpanel">
                    <div class="d-flex align-items-center mb-3">
                      <i class="<?= e(getCategoryIcon($cat)) ?> me-2 text-primary"></i>
                      <h5 class="mb-0"><?= e(ucfirst($cat)) ?> Settings</h5>
                      <span class="category-badge ms-2"><?= count($fields) ?> <?= count($fields)===1?'setting':'settings' ?></span>
                    </div>

                    <?php foreach ($fields as $f):
                        $key   = $f['key'];
                        $type  = $f['type'] ?: 'string';
                        $desc  = $f['description'] ?: '';
                        $val   = effectiveValue($f);
                        $isReq = !empty($f['is_required']);
                        $isPub = !empty($f['is_public']);
                        $name  = "s[$key]";
                        $validation = $f['validation_rules'];
                        $hasError   = isset($fieldErrors[$key]);
                        $errorMsg   = $fieldErrors[$key] ?? '';
                    ?>
                      <div class="setting-field <?= $hasError ? 'has-error' : '' ?>">
                        <div class="d-flex align-items-start justify-content-between">
                          <div class="mb-2">
                            <label class="form-label fw-semibold mb-1"><?= e(ucwords(str_replace(['_','-'],' ', $key))) ?></label>
                            <div class="d-flex flex-wrap gap-2">
                              <span class="key-label text-body-secondary small"><?= e($key) ?></span>
                              <span class="badge <?= $isReq?'text-bg-danger':'text-bg-secondary' ?>"><?= $isReq?'required':'optional' ?></span>
                              <?php if ($isPub): ?><span class="badge text-bg-info">public API</span><?php endif; ?>
                              <span class="badge text-bg-light text-dark border"><?= e($type) ?></span>
                            </div>
                          </div>
                        </div>

                        <?php if ($desc): ?>
                          <div class="text-body-secondary small mb-2"><?= e($desc) ?></div>
                        <?php endif; ?>

                        <?php if ($type === 'boolean'): ?>
                          <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="f-<?= e($key) ?>" name="<?= e($name) ?>" value="1"
                                   <?= ($val==='1'||$val===1||$val===true||$val==='true'||$val==='on')?'checked':'' ?>>
                            <label class="form-check-label" for="f-<?= e($key) ?>">Enable this feature</label>
                          </div>

                        <?php elseif ($type === 'integer'): ?>
                          <input type="number" class="form-control <?= $hasError ? 'is-invalid' : '' ?>"
                                 id="f-<?= e($key) ?>" name="<?= e($name) ?>" value="<?= e($val) ?>" placeholder="Enter a number">

                        <?php elseif ($type === 'text'): ?>
                          <textarea class="form-control <?= $hasError ? 'is-invalid' : '' ?>"
                                    id="f-<?= e($key) ?>" name="<?= e($name) ?>" rows="4"
                                    placeholder="Enter text..."><?= e($val) ?></textarea>

                        <?php elseif ($type === 'json' || $type === 'array'): ?>
                          <textarea class="form-control font-monospace <?= $hasError ? 'is-invalid' : '' ?>"
                                    id="f-<?= e($key) ?>" name="<?= e($name) ?>" rows="6"
                                    data-type="json" placeholder='{"example":"value"}'><?= e($val) ?></textarea>
                          <div class="form-text"><i class="fa-regular fa-circle-question me-1"></i>Enter valid JSON.</div>

                        <?php else: /* string */ ?>
                          <input type="text" class="form-control <?= $hasError ? 'is-invalid' : '' ?>"
                                 id="f-<?= e($key) ?>" name="<?= e($name) ?>" value="<?= e($val) ?>" placeholder="Enter value">
                        <?php endif; ?>

                        <?php if ($hasError): ?>
                          <div class="invalid-feedback"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= e($errorMsg) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($validation)): ?>
                          <div class="form-text small mt-2">
                            <i class="fa-solid fa-shield-halved me-1"></i>
                            <span class="text-body-secondary">Validation: </span>
                            <code class="small bg-light px-2 py-1 rounded"><?= e($validation) ?></code>
                          </div>
                        <?php endif; ?>

                        <?php if ($f['default_value']): ?>
                          <div class="form-text small">
                            <i class="fa-solid fa-rotate-left me-1"></i>
                            Default: <code><?= e($f['default_value']) ?></code>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php $first=false; endforeach; ?>
                </div>

                <div class="sticky-save mt-2">
                  <div class="d-flex align-items-center justify-content-between">
                    <small class="text-body-secondary">
                      <i class="fa-regular fa-clock me-1"></i>
                      <?php
                        $last = 'Never';
                        foreach ($schema['list'] as $setting) {
                          if ($setting['key'] === 'last_update_at') { $last = $setting['_effective'] ?: 'Never'; break; }
                        }
                        echo e($last);
                      ?>
                    </small>
                    <div>
                      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Update All Settings</button>
                      <a href="settings.php" class="btn btn-outline-secondary ms-2"><i class="fa-solid fa-rotate-left me-1"></i>Reset</a>
                    </div>
                  </div>
                </div>

              </form>
            </div>
          </div>
        </div>
      </div><!-- /card -->
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle for mobile
function toggleSidebar(){ document.querySelector('.admin-sidebar')?.classList.toggle('show'); }
window.addEventListener('resize', ()=>{ if (window.innerWidth > 768) document.querySelector('.admin-sidebar')?.classList.remove('show'); });

// JSON validation on blur
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('textarea[data-type="json"]').forEach(el=>{
    el.addEventListener('blur', function(){
      const v = this.value.trim();
      if (!v) { this.classList.remove('is-invalid'); return; }
      try { JSON.parse(v); this.classList.remove('is-invalid'); }
      catch(e){ this.classList.add('is-invalid'); }
    });
  });

  // Large-change confirmation
  const form = document.getElementById('settingsForm');
  form.addEventListener('submit', function(e){
    const changed = Array.from(form.elements).filter(el=>{
      if (el.type === 'checkbox') return el.checked !== el.defaultChecked;
      if (el.tagName==='TEXTAREA' || el.tagName==='INPUT') return el.value !== el.defaultValue;
      return false;
    });
    if (changed.length > 10) {
      if (!confirm(`You are about to update ${changed.length} settings. Continue?`)) e.preventDefault();
    }
  });

  // Local draft autosave (optional)
  const autoSaveKey = 'phpstrap_settings_draft';
  let t;
  function autoSave(){
    clearTimeout(t);
    t = setTimeout(()=>{
      const fd = new FormData(form), data = {};
      for (const [k,v] of fd.entries()) if (k.startsWith('s[')) data[k]=v;
      try { localStorage.setItem(autoSaveKey, JSON.stringify(data)); } catch(_) {}
    }, 1500);
  }
  form.addEventListener('input', autoSave);
  form.addEventListener('change', autoSave);
  form.addEventListener('submit', ()=>{ try { localStorage.removeItem(autoSaveKey); } catch(_){} });
});
</script>
</body>
</html>