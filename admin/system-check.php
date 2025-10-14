<?php
/**
 * PhPstrap Admin — System Checks (Preflight/Health)
 * - Safe: never prints secrets; masks values
 * - Matches your admin layout/auth pattern
 * - Root-level paths: /cache, /uploads, /logs (REQUIRED)
 * - Optional: /.env and any /storage/*
 * - Optional JSON output with ?format=json
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

/* ------------------------------- Includes --------------------------------- */
require_once '../config/app.php';
require_once '../config/database.php';

// Auth include (support multiple paths)
$auth_paths = ['admin-auth.php', 'includes/admin-auth.php', '../includes/admin-auth.php'];
foreach ($auth_paths as $path) { if (file_exists($path)) { require_once $path; break; } }

/* ------------------------------ App/Auth ---------------------------------- */
initializeApp();
if (function_exists('requireAdminAuth')) { requireAdminAuth(); }
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
  header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'system-check.php'));
  exit;
}

/* ------------------------------ Helpers ----------------------------------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ok($b){ return $b ? 'pass' : 'fail'; }
function mask($v, $keep = 4){
  $s = (string)$v; if ($s === '' || $s === '0') return '';
  $len = strlen($s); if ($len <= $keep) return str_repeat('•', $len);
  return str_repeat('•', max(0, $len - $keep)) . substr($s, -$keep);
}
function bytesToStr($b){
  if ($b === null) return '—';
  $u=['B','KB','MB','GB','TB']; $i=0; $b=(float)$b;
  while ($b>=1024 && $i<count($u)-1){ $b/=1024; $i++; }
  return number_format($b, $i?1:0) . ' ' . $u[$i];
}
function iniBytes($key){
  $v = ini_get($key);
  if ($v === false || $v === '') return null;
  $v = trim($v);
  $last = strtolower(substr($v,-1));
  $num = (int)$v;
  return match($last){
    'g' => $num*1024*1024*1024,
    'm' => $num*1024*1024,
    'k' => $num*1024,
    default => (int)$v
  };
}
function isWritablePath($p){ clearstatcache(true, $p); return is_dir($p) ? is_writable($p) : (file_exists($p) ? is_writable($p) : is_writable(dirname($p))); }
function serverHasApache(){
  $s = $_SERVER['SERVER_SOFTWARE'] ?? '';
  return stripos($s, 'apache') !== false || function_exists('apache_get_modules');
}
function hasRewrite(){
  // If Apache, check module; otherwise assume OK (Nginx typically handled in server config)
  if (function_exists('apache_get_modules')) {
    return in_array('mod_rewrite', apache_get_modules());
  }
  return true; // soft-ok for non-Apache
}
function pdoDrivers(){ return class_exists('PDO') ? PDO::getAvailableDrivers() : []; }

/* ------------------------------ Settings/Paths ----------------------------- */
// Project root (this file is in /admin)
$ROOT = realpath(__DIR__ . '/..') ?: dirname(__DIR__);

// Required paths (must exist + be writable)
$paths_required = [
  'cache'   => $ROOT . '/cache',
  'logs'    => $ROOT . '/logs',
  'uploads' => $ROOT . '/uploads',
  'config'  => $ROOT . '/config',
];

// Optional paths (ok if missing)
$paths_optional = [
  '.env'            => $ROOT . '/.env',
  'storage'         => $ROOT . '/storage',
  'storage/cache'   => $ROOT . '/storage/cache',
  'storage/logs'    => $ROOT . '/storage/logs',
];

// Site name (best-effort from DB)
$site_name = 'PhPstrap Admin';
try {
  $pdoTmp = getDbConnection();
  if ($pdoTmp instanceof PDO) {
    $stmt = $pdoTmp->query("SELECT value FROM settings WHERE `key`='site_name' LIMIT 1");
    if ($v = $stmt->fetchColumn()) $site_name = $v;
  }
} catch (Throwable $e) {}

/* ------------------------------ Checks ------------------------------------ */
$requiredPhp = '8.1.0'; // adjust if phpstrap supports lower
$extRequired = ['pdo','pdo_mysql','mbstring','json','curl','openssl'];
$extRecommended = ['intl','gd']; // or 'imagick' instead of gd if you prefer
$timezone = ini_get('date.timezone');

$checks = [];

/* PHP version */
$checks[] = [
  'group' => 'PHP',
  'name'  => 'PHP Version',
  'want'  => ">={$requiredPhp}",
  'have'  => PHP_VERSION,
  'pass'  => version_compare(PHP_VERSION, $requiredPhp, '>=')
];

/* Extensions required */
foreach ($extRequired as $ext) {
  $checks[] = [
    'group'=>'Extensions (Required)',
    'name'=>"ext/$ext",
    'want'=>'enabled',
    'have'=>extension_loaded($ext) ? 'enabled' : 'missing',
    'pass'=>extension_loaded($ext)
  ];
}

/* Extensions recommended */
foreach ($extRecommended as $ext) {
  $checks[] = [
    'group'=>'Extensions (Recommended)',
    'name'=>"ext/$ext",
    'want'=>'enabled',
    'have'=>extension_loaded($ext) ? 'enabled' : 'missing',
    'pass'=>extension_loaded($ext)
  ];
}

/* PDO + driver */
$drivers = pdoDrivers();
$checks[] = [
  'group'=>'Database',
  'name'=>'PDO available',
  'want'=>'class PDO + pdo_mysql',
  'have'=> 'drivers: [' . implode(', ', $drivers) . ']',
  'pass'=> class_exists('PDO') && in_array('mysql', $drivers, true)
];

// DB connection
$dbConnected = false; $dbErr = '';
try { $pdo = getDbConnection(); $dbConnected = $pdo instanceof PDO; }
catch (Throwable $e) { $dbErr = $e->getMessage(); }
$checks[] = [
  'group'=>'Database',
  'name'=>'DB connection',
  'want'=>'successful',
  'have'=> $dbConnected ? 'OK' : ('ERROR: ' . (function_exists('mb_strimwidth') ? mb_strimwidth($dbErr,0,120,'…','UTF-8') : substr($dbErr,0,120))),
  'pass'=> $dbConnected
];

/* Ini settings (baseline sane defaults) */
$wantMemory = 128*1024*1024; // 128MB+
$wantUpload = 16*1024*1024;  // 16MB+
$wantExec   = 30;            // 30s+

$memLimit = iniBytes('memory_limit'); // null (-1) handled below
$checks[] = [
  'group'=>'PHP INI',
  'name'=>'memory_limit',
  'want'=> '>= 128 MB or -1',
  'have'=> $memLimit !== null ? bytesToStr($memLimit) : 'Unlimited (-1 / not set)',
  'pass'=> ($memLimit === null || $memLimit >= $wantMemory)
];

$uploadMax = iniBytes('upload_max_filesize');
$postMax   = iniBytes('post_max_size');
$checks[] = [
  'group'=>'PHP INI',
  'name'=>'upload_max_filesize',
  'want'=> '>= 16 MB',
  'have'=> $uploadMax !== null ? bytesToStr($uploadMax) : '—',
  'pass'=> ($uploadMax !== null && $uploadMax >= $wantUpload)
];
$checks[] = [
  'group'=>'PHP INI',
  'name'=>'post_max_size',
  'want'=> '>= 16 MB (>= upload_max_filesize)',
  'have'=> $postMax !== null ? bytesToStr($postMax) : '—',
  'pass'=> ($postMax !== null && $postMax >= max($wantUpload, $uploadMax ?? 0))
];

$maxExec = (int)ini_get('max_execution_time');
$checks[] = [
  'group'=>'PHP INI',
  'name'=>'max_execution_time',
  'want'=> '>= 30 sec',
  'have'=> $maxExec ?: 0,
  'pass'=> ($maxExec === 0 || $maxExec >= $wantExec) // 0 means unlimited
];

$checks[] = [
  'group'=>'PHP INI',
  'name'=>'date.timezone',
  'want'=> 'set',
  'have'=> $timezone ?: 'not set',
  'pass'=> !empty($timezone)
];

$checks[] = [
  'group'=>'Sessions',
  'name'=>'session.save_path writable',
  'want'=> 'writable',
  'have'=> ($p = ini_get('session.save_path')) ? $p : 'default',
  'pass'=> ($p = ini_get('session.save_path')) ? isWritablePath($p) : true
];

/* Web server / rewrite */
$checks[] = [
  'group'=>'Web Server',
  'name'=>'Server',
  'want'=>'Apache/Nginx (FPM/LSAPI ok)',
  'have'=> $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
  'pass'=> true // informational
];
$checks[] = [
  'group'=>'Web Server',
  'name'=>'URL rewriting',
  'want'=>'enabled',
  'have'=> serverHasApache() && function_exists('apache_get_modules') ? (in_array('mod_rewrite', apache_get_modules()) ? 'mod_rewrite' : 'missing') : 'assumed OK',
  'pass'=> hasRewrite()
];

/* Paths/permissions (required) */
foreach ($paths_required as $label => $p) {
  $exists = file_exists($p);
  $w = $exists ? isWritablePath($p) : false;
  $checks[] = [
    'group'=>'Paths (Required)',
    'name'=> $label,
    'want'=>'exists + writable',
    'have'=> ($exists ? 'exists' : 'missing') . ' / ' . ($w ? 'writable' : 'not writable') . ' (' . $p . ')',
    'pass'=> $exists && $w
  ];
}

/* Paths/permissions (optional) — missing is OK */
foreach ($paths_optional as $label => $p) {
  $exists = file_exists($p);
  $w = $exists ? isWritablePath($p) : false;
  $checks[] = [
    'group'=>'Paths (Optional)',
    'name'=> $label,
    'want'=>'exists + writable (optional)',
    'have'=> ($exists ? 'exists' : 'missing') . ($exists ? (' / ' . ($w ? 'writable' : 'not writable')) : '') . ' (' . $p . ')',
    'pass'=> true  // informational only
  ];
}

/* Optional crypto */
$checks[] = [
  'group'=>'Security',
  'name'=>'openssl_random_pseudo_bytes',
  'want'=>'available',
  'have'=> function_exists('openssl_random_pseudo_bytes') ? 'available' : 'missing',
  'pass'=> function_exists('openssl_random_pseudo_bytes')
];

/* Cron/queue (optional ping file) — root-friendly */
$cronCandidates = [
  $paths_required['logs'] . '/cron.last',
  $paths_required['cache'] . '/cron.last',
];
$cronFile = null;
foreach ($cronCandidates as $cand) { if (file_exists($cand)) { $cronFile = $cand; break; } }

if ($cronFile) {
  $age = time() - (int)@filemtime($cronFile);
  $checks[] = [
    'group'=>'Background',
    'name'=>'cron heartbeat',
    'want'=>'recent (< 15 min)',
    'have'=> $age . 's ago (' . $cronFile . ')',
    'pass'=> $age < 900
  ];
} else {
  $checks[] = [
    'group'=>'Background',
    'name'=>'cron heartbeat',
    'want'=>'optional cron.last in /logs or /cache',
    'have'=>'not found (optional)',
    'pass'=> true
  ];
}

/* ------------------------------ Group/Score -------------------------------- */
$groups = [];
$scorePass = 0; $scoreTotal = 0;
foreach ($checks as $c) {
  $groups[$c['group']][] = $c;
  $scoreTotal++;
  if ($c['pass']) $scorePass++;
}

// JSON output
if (isset($_GET['format']) && $_GET['format'] === 'json') {
  header('Content-Type: application/json');
  echo json_encode([
    'summary' => [
      'passed' => $scorePass,
      'total'  => $scoreTotal,
    ],
    'checks'  => $checks
  ]);
  exit;
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>System Checks - <?= h($site_name) ?></title>

<link href="/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet">
<style>
.badge-pass{background:#28a745;} .badge-fail{background:#dc3545;}
.table-checks td, .table-checks th { vertical-align: middle; }
</style>
</head>
<body class="bg-body-tertiary">
<div class="d-flex">
  <!-- Sidebar -->
  <aside class="admin-sidebar bg-dark text-white">
    <div class="p-3 border-bottom border-secondary-subtle d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <i class="fa-solid fa-screwdriver-wrench me-2"></i>
        <strong>PhPstrap Admin</strong>
      </div>
      <button class="btn btn-sm btn-outline-light d-lg-none" id="btnSidebarToggle" aria-label="Close menu">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <?php $activeKey = 'system-check'; include __DIR__ . '/includes/admin-sidebar.php'; ?>
  </aside>

  <!-- Content -->
  <main class="flex-grow-1">
    <header class="bg-white border-bottom">
      <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center">
            <button type="button" class="btn btn-outline-secondary d-lg-none me-3" id="btnSidebarOpen" aria-label="Open menu">
              <i class="fa-solid fa-bars"></i>
            </button>
            <div class="d-flex align-items-center gap-3">
              <h1 class="h4 mb-0">System Checks</h1>
              <div class="small text-body-secondary mt-1">
                <span class="badge text-bg-secondary"><?= (int)$scorePass ?>/<?= (int)$scoreTotal ?> passed</span>
              </div>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="?"><i class="fa-solid fa-rotate me-1"></i> Re-run</a>
            <a class="btn btn-sm btn-outline-primary" href="?format=json" target="_blank"><i class="fa-solid fa-code me-1"></i> JSON</a>
          </div>
        </div>
      </div>
    </header>

    <div class="admin-content container-fluid">
      <?php foreach ($groups as $gName => $rows): ?>
        <section class="mb-3">
          <div class="card shadow-sm">
            <div class="card-header bg-body-tertiary fw-semibold">
              <i class="fa-solid fa-circle-check me-2"></i><?= h($gName) ?>
            </div>
            <div class="table-responsive">
              <table class="table table-checks table-striped align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:40%">Check</th>
                    <th>Required</th>
                    <th>Detected</th>
                    <th style="width:90px" class="text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td class="fw-semibold"><?= h($r['name']) ?></td>
                    <td class="small text-body-secondary"><?= h($r['want']) ?></td>
                    <td class="small"><?= h($r['have']) ?></td>
                    <td class="text-center">
                      <span class="badge <?= $r['pass'] ? 'badge-pass' : 'badge-fail' ?>">
                        <?= $r['pass'] ? 'PASS' : 'FAIL' ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      <?php endforeach; ?>

      <!-- Tips -->
      <div class="alert alert-info my-3">
        <i class="fa-solid fa-lightbulb me-2"></i>
        Tip: red items usually fix by enabling extensions, adjusting <code>php.ini</code>, or setting write permissions on
        <code>/cache</code>, <code>/logs</code>, <code>/uploads</code>, and <code>/config</code>. Optional items (like <code>.env</code> or <code>/storage/*</code>) don’t block running.
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('btnSidebarOpen')?.addEventListener('click', () => {
  document.querySelector('.admin-sidebar')?.classList.add('show');
});
document.getElementById('btnSidebarToggle')?.addEventListener('click', () => {
  document.querySelector('.admin-sidebar')?.classList.remove('show');
});
</script>
</body>
</html>