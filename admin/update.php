<?php
/**
 * PhPstrap Admin — Updater (unified UI)
 * - External sidebar include: /admin/includes/admin-sidebar.php
 * - Bootstrap 5.3 + Font Awesome + /assets/css/admin.css
 * - Preserves diagnostic updater behavior (download → preview → install)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/* ------------------------------- Includes --------------------------------- */
require_once '../config/app.php';
require_once '../config/database.php';

// Optional settings helpers (getSetting, setSetting)
$settings_paths = ['../includes/settings.php', 'includes/settings.php'];
foreach ($settings_paths as $sp) { if (file_exists($sp)) { require_once $sp; break; } }

// Auth include (support multiple paths)
$auth_paths = ['admin-auth.php', 'includes/admin-auth.php', '../includes/admin-auth.php'];
foreach ($auth_paths as $path) { if (file_exists($path)) { require_once $path; break; } }

/* ------------------------------ App/Auth ---------------------------------- */
initializeApp();
if (function_exists('requireAdminAuth')) { requireAdminAuth(); }
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'update.php'));
    exit;
}
$admin = [
    'id'    => $_SESSION['admin_id']    ?? 1,
    'name'  => $_SESSION['admin_name']  ?? 'Administrator',
    'email' => $_SESSION['admin_email'] ?? 'admin@example.com'
];
if (!isset($_SESSION['PhPstrap_csrf_token'])) {
    $_SESSION['PhPstrap_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['PhPstrap_csrf_token'];

if (function_exists('logAdminActivity')) { try { logAdminActivity('update_access', ['page' => 'update']); } catch (Throwable $e) {} }

/* -------------------------------- Utils ----------------------------------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function vnorm($v){ $v=trim((string)$v); return ($v!=='' && ($v[0]==='v'||$v[0]==='V'))?substr($v,1):$v; }
function cur_version(): string {
    if (defined('BOOTPHP_VERSION')) return (string) BOOTPHP_VERSION;
    if (function_exists('getSetting')) {
        $v = getSetting('app_version', null);
        if ($v) return (string)$v;
    }
    return '0.0.0';
}

/* ------------------------------ DB/meta ----------------------------------- */
$db_available = false;
$pdo = null;
try { $pdo = getDbConnection(); $db_available = $pdo instanceof PDO; } catch (Throwable $e) {}

$system_info = ['app_version' => cur_version()];
try {
    if ($pdo) {
        // Prefer settings table if present
        if (function_exists('getSetting')) {
            $system_info['app_version'] = getSetting('app_version', $system_info['app_version']);
        } else {
            $stmt = $pdo->query("SELECT value FROM settings WHERE `key`='app_version' LIMIT 1");
            if ($v = $stmt->fetchColumn()) $system_info['app_version'] = $v;
        }
    }
} catch (Throwable $e) {}

/* ------------------------------ Updater ----------------------------------- */
// Configurable via settings, otherwise defaults:
$REPO = function_exists('getSetting') ? (getSetting('update_repo', 'PhPstrap/phpstrap') ?: 'PhPstrap/phpstrap') : 'PhPstrap/phpstrap';
$UA   = 'PhPstrap-Updater/1.1 (+'.($_SERVER['HTTP_HOST'] ?? 'localhost').')';
$GITHUB_TOKEN = (function_exists('getSetting') ? (getSetting('github_token', null)) : null) ?: (defined('GITHUB_TOKEN') ? GITHUB_TOKEN : null) ?: getenv('GITHUB_TOKEN') ?: null;

define('ADMIN_PATH', __DIR__);
define('ROOT_PATH', dirname(__DIR__));
$CACHE_DIR   = ROOT_PATH . '/cache/updates';
$BACKUP_DIR  = ROOT_PATH . '/backups';
@is_dir($CACHE_DIR)  || @mkdir($CACHE_DIR, 0755, true);
@is_dir($BACKUP_DIR) || @mkdir($BACKUP_DIR, 0755, true);

// safe skip lists
$DEFAULT_SKIP_DIRS = ['config','uploads','logs','cache','.git','.github','installer'];
$DEFAULT_SKIP_FILES = [
    '.env','.env.local','.env.production',
    '.htaccess','admin/.htaccess',
    'config/database.php','config/app.php',
    'index.php'
];

/* ------------------------------ HTTP/FS ----------------------------------- */
function http_get_json($url,$ua,$token=null){
    $ch=curl_init($url);
    $h=['Accept: application/vnd.github+json','User-Agent: '.$ua];
    if($token)$h[]='Authorization: Bearer '.$token;
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>30]);
    $body=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if($body===false||$code>=400){$err=$body===false?curl_error($ch):"HTTP $code";curl_close($ch);throw new Exception("GitHub API failed: $err");}
    curl_close($ch); $data=json_decode($body,true);
    if(!is_array($data)) throw new Exception('Invalid JSON'); return $data;
}
function download_file($url,$dest,$ua,$token=null){
    $fp=fopen($dest,'w'); if(!$fp) throw new Exception("Cannot write $dest");
    $h=['User-Agent: '.$ua];
    if (strpos($url,'/releases/assets/')!==false) $h[]='Accept: application/octet-stream';
    if ($token) $h[]='Authorization: Bearer '.$token;
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>120]);
    $ok=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if($ok===false||$code>=400){$err=$ok===false?curl_error($ch):"HTTP $code";fclose($fp);@unlink($dest);curl_close($ch);throw new Exception("Download failed: $err");}
    curl_close($ch); fclose($fp); return true;
}
function unzip_archive($zip,$to){
    if(!class_exists('ZipArchive')) throw new Exception('ZipArchive missing');
    $z=new ZipArchive(); if($z->open($zip)!==true) throw new Exception("Cannot open zip $zip");
    @mkdir($to,0755,true); if(!$z->extractTo($to)){ $z->close(); throw new Exception('Extract failed'); }
    $root=null; if($z->numFiles>0){$first=$z->getNameIndex(0); $root=explode('/',$first)[0]??null;}
    $z->close(); return $root? rtrim($to,'/').'/'.$root : $to;
}
function path_is_skipped($rel,$SKIP_DIRS,$SKIP_FILES){
    $rel=ltrim($rel,'/');
    foreach($SKIP_FILES as $f){ if($rel===ltrim($f,'/')) return ['file',$f]; }
    foreach($SKIP_DIRS as $d){ $d=rtrim(ltrim($d,'/'),'/').'/'; if(strpos($rel.'/',$d)===0) return ['dir',rtrim($d,'/')]; }
    return false;
}
function same_content($src,$dst){
    if(!is_file($dst)) return false;
    if(filesize($src)===filesize($dst)){
        $a=md5_file($src); $b=md5_file($dst);
        return $a!==false && $a===$b;
    }
    return false;
}
function make_backup($BACKUP_DIR,$label=''){
    $stamp=date('Ymd_His').($label?'_'.$label:'');
    $dir=rtrim($BACKUP_DIR,'/').'/'.$stamp; @mkdir($dir,0755,true);
    $picks=['config','includes','modules','lang','assets','index.php','.htaccess','admin','dashboard','login'];
    foreach($picks as $item){
        $src=ROOT_PATH.'/'.$item; $dst=$dir.'/'.$item;
        if(is_dir($src)){
            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
            foreach($it as $p=>$info){ $rel=substr($p,strlen($src)+1); $td=$dst.'/'.$rel;
                if($info->isDir()){ @mkdir($td,0755,true); } else { @is_dir(dirname($td))||@mkdir(dirname($td),0755,true); @copy($p,$td); }
            }
        } elseif(is_file($src)) { @copy($src,$dst); }
    }
    return $dir;
}
function apply_tree($src,$dst,$baseSrcLen,$SKIP_DIRS,$SKIP_FILES,$preview=false){
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    $report=[]; $stats=['dirs'=>0,'copied'=>0,'updated'=>0,'skipped'=>0,'same'=>0,'notwritable'=>0,'total'=>0];
    foreach($it as $path=>$info){
        $rel = substr($path,$baseSrcLen+1);
        if ($rel===false) continue;
        $stats['total']++;
        if ($skip = path_is_skipped($rel,$SKIP_DIRS,$SKIP_FILES)) {
            $stats['skipped']++;
            $report[] = "SKIP-".$skip[0]."  $rel";
            continue;
        }
        $target = rtrim($dst,'/').'/'.$rel;
        if ($info->isDir()){
            if (!$preview && !is_dir($target)) {
                if (!@mkdir($target,0755,true)) { $stats['notwritable']++; $report[]="ERR   mkdir $rel"; continue; }
            }
            $stats['dirs']++;
            $report[] = "DIR   $rel";
        } else {
            if (!$preview) { @is_dir(dirname($target)) || @mkdir(dirname($target),0755,true); }
            if (same_content($path,$target)) {
                $stats['same']++; $report[]="SAME  $rel";
            } else {
                if ($preview) {
                    $stats['updated']++; $report[]="WOULD $rel";
                } else {
                    if (is_file($target) && !is_writable($target) && !is_writable(dirname($target))) {
                        $stats['notwritable']++; $report[]="ERR   nowrite $rel"; continue;
                    }
                    if (@copy($path,$target)) {
                        $stats[is_file($target)?'updated':'copied']++;
                        $report[]="FILE  $rel";
                    } else {
                        $stats['notwritable']++; $report[]="ERR   copy $rel";
                    }
                }
            }
        }
    }
    return [$report,$stats];
}

/* ---------------------------- Controller ---------------------------------- */
$errors=[]; $messages=[]; $latest=null;

// always check latest (fresh view each load)
try {
    $latest = http_get_json("https://api.github.com/repos/$REPO/releases/latest",$UA,$GITHUB_TOKEN);
    $latestTag  = $latest['tag_name'] ?? '';
    $latestName = $latest['name']     ?? $latestTag;
    $zipUrl     = $latest['zipball_url'] ?? null;
    $_SESSION['update_meta'] = [
        'tag' => $latestTag,
        'name'=> $latestName,
        'zip' => $zipUrl,
        'html'=> $latest['html_url']??null,
        'published_at'=>$latest['published_at']??null
    ];
} catch (Throwable $e) { $errors[] = "Failed to check latest release: ".$e->getMessage(); }

$cur = cur_version();
$lat = isset($_SESSION['update_meta']['tag']) ? vnorm($_SESSION['update_meta']['tag']) : null;
$updateAvailable = $lat && version_compare(vnorm($cur), $lat, '<');

// options (persisted by POST only)
$allow_core = isset($_POST['allow_core']) && $_POST['allow_core'] === '1';
$preview    = isset($_POST['preview']) && $_POST['preview'] === '1';

$SKIP_DIRS  = $DEFAULT_SKIP_DIRS;
$SKIP_FILES = $DEFAULT_SKIP_FILES;
if ($allow_core) {
    $SKIP_FILES = array_values(array_diff($SKIP_FILES, ['index.php','.htaccess']));
}

// Handle POST actions (with CSRF)
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf, $_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action==='download') {
                if (empty($_SESSION['update_meta']['zip']) || empty($_SESSION['update_meta']['tag']))
                    throw new Exception('No release to download');
                $tag=$_SESSION['update_meta']['tag']; $zip=$_SESSION['update_meta']['zip'];
                $zipPath = $CACHE_DIR.'/phpstrap_'.preg_replace('/[^a-zA-Z0-9_.-]/','-',$tag).'.zip';
                download_file($zip,$zipPath,$UA,$GITHUB_TOKEN);
                $_SESSION['update_zip']=$zipPath;
                $messages[]="Downloaded <strong>".h($tag)."</strong> to <code>".h($zipPath)."</code>.";
            } elseif ($action==='install' || $action==='preview') {
                if (empty($_SESSION['update_zip'])) throw new Exception('No downloaded update zip. Click “Download” first.');
                $zipPath   = $_SESSION['update_zip'];
                $extractTo = $CACHE_DIR.'/extracted_'.time();
                $repoRoot  = unzip_archive($zipPath,$extractTo);

                // force preview on preview action
                if ($action==='preview') $preview = true;

                if (!$preview) {
                    $backup = make_backup($BACKUP_DIR, 'preupdate');
                    $messages[] = "Backup created at <code>".h($backup)."</code>";
                }

                list($report,$stats) = apply_tree($repoRoot, ROOT_PATH, strlen($repoRoot), $SKIP_DIRS, $SKIP_FILES, $preview);

                if (!$preview && function_exists('setSetting') && !empty($_SESSION['update_meta']['tag'])) {
                    @setSetting('app_version', vnorm($_SESSION['update_meta']['tag']), 'string');
                    @setSetting('last_update_at', date('Y-m-d H:i:s'), 'string');
                }

                $summary = ($preview?'Preview ':'')."Results — total: {$stats['total']}, dirs: {$stats['dirs']}, updated: {$stats['updated']}, copied: {$stats['copied']}, same: {$stats['same']}, skipped: {$stats['skipped']}, not-writable: {$stats['notwritable']}";
                $messages[] = $summary;

                if ($stats['updated']==0 && $stats['copied']==0 && $stats['notwritable']==0) {
                    $messages[] = "Nothing changed. Either files are identical OR they were skipped by safety filters. Enable “Allow core overwrites” if you expect <code>index.php</code> or <code>.htaccess</code> to update.";
                }

                $max = 500;
                if (count($report) > $max) {
                    $head = array_slice($report, 0, 120);
                    $tail = array_slice($report, -380);
                    $report = array_merge($head, ["... (".(count($report)-$max)." lines trimmed) ..."], $tail);
                }
                $_SESSION['last_report'] = $report;

                if (function_exists('logAdminActivity')) {
                    try { logAdminActivity($action==='preview'?'update_preview':'update_install', ['allow_core'=>$allow_core]); } catch (Throwable $e) {}
                }
            } else {
                $errors[] = 'Invalid action.';
            }
        } catch (Throwable $e) { $errors[] = $e->getMessage(); }
    }
}

$report = $_SESSION['last_report'] ?? [];

// Settings for the header
$settings = [];
try {
    if (function_exists('getSetting')) {
        $settings['site_name'] = getSetting('site_name', 'PhPstrap Admin');
    } else {
        $stmt = $pdo?->query("SELECT value FROM settings WHERE `key`='site_name' LIMIT 1");
        $settings['site_name'] = $stmt && ($v=$stmt->fetchColumn()) ? $v : 'PhPstrap Admin';
    }
} catch (Throwable $e) { $settings['site_name'] = 'PhPstrap Admin'; }

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Updater - <?= h($settings['site_name'] ?? 'PhPstrap Admin') ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet">
<style>
/* Small extras for the report pane */
pre.update-report{max-height:380px;overflow:auto;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:10px}
</style>
</head>
<body class="bg-body-tertiary">

<div class="d-flex">
  <!-- Sidebar -->
  <aside class="admin-sidebar bg-dark text-white">
    <div class="p-3 border-bottom border-secondary-subtle">
      <div class="d-flex align-items-center">
        <i class="fa-solid fa-shield-halved me-2"></i>
        <strong>PhPstrap Admin</strong>
      </div>
      <div class="small text-secondary mt-1">v<?= h($system_info['app_version']) ?></div>
    </div>
<?php
  // External sidebar include
  $activeKey     = 'update';                 // key must match your sidebar mapping
  $sidebarBadges = [];                       // e.g. ['update' => 1]
  $appVersion    = $system_info['app_version'] ?? '1.0.0';
  $adminName     = $admin['name'] ?? 'Admin';
  include __DIR__ . '/includes/admin-sidebar.php';
?>
  </aside>

  <!-- Content -->
  <main class="flex-grow-1">
    <header class="bg-white border-bottom">
      <div class="container-fluid py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h1 class="h4 mb-0">Updater</h1>
            <div class="small text-body-secondary mt-1">
              <i class="fa-regular fa-clock me-1"></i><?= date('l, F j, Y g:i A') ?>
              <span class="badge ms-2 <?= $db_available ? 'text-bg-success' : 'text-bg-danger' ?>">
                <?= $db_available ? 'Database Connected' : 'Database Offline' ?>
              </span>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="text-body-secondary">Welcome, <strong><?= h($admin['name']) ?></strong></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
          </div>
        </div>
      </div>
    </header>

    <div class="admin-content container-fluid">

      <?php if ($errors): ?>
        <div class="alert alert-danger my-3">
          <strong><i class="fa-solid fa-triangle-exclamation me-1"></i>Errors</strong>
          <ul class="mb-0">
            <?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($messages): ?>
        <div class="alert alert-success my-3">
          <?php foreach($messages as $m): ?>
            <div class="mb-1"><?= $m ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Status -->
      <section class="mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary fw-semibold">
            <i class="fa-solid fa-code-branch me-2"></i>Status
          </div>
          <div class="card-body d-flex flex-wrap align-items-center gap-4">
            <div>
              <div class="text-body-secondary">Current</div>
              <div class="fs-4"><code><?= h($cur) ?></code></div>
            </div>
            <div>
              <div class="text-body-secondary">Latest</div>
              <div class="fs-4">
                <?php if(!empty($_SESSION['update_meta']['tag'])): ?>
                  <code><?= h($_SESSION['update_meta']['tag']) ?></code>
                <?php else: ?><span class="text-body-secondary">unknown</span><?php endif; ?>
              </div>
            </div>
            <div>
              <?php if ($updateAvailable): ?>
                <span class="badge text-bg-success">Update available</span>
              <?php else: ?>
                <span class="badge text-bg-secondary">Up to date</span>
              <?php endif; ?>
            </div>
            <div class="ms-auto">
              <?php if (!empty($_SESSION['update_meta']['html'])): ?>
                <a target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm" href="<?= h($_SESSION['update_meta']['html']) ?>">
                  <i class="fa-brands fa-github me-1"></i>View on GitHub
                </a>
              <?php endif; ?>
            </div>
          </div>
          <?php if (!empty($_SESSION['update_meta']['published_at'])): ?>
            <div class="card-footer small text-body-secondary">
              Published: <?= h(date('Y-m-d H:i', strtotime($_SESSION['update_meta']['published_at']))) ?>
              <span class="ms-3">Repo: <code><?= h($REPO) ?></code></span>
              <?php if ($GITHUB_TOKEN): ?><span class="ms-3 badge text-bg-info">Token in use</span><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Controls -->
      <section class="mb-3">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary fw-semibold d-flex align-items-center justify-content-between">
            <span><i class="fa-solid fa-screwdriver-wrench me-2"></i>Update Controls</span>
            <span class="small text-body-secondary">Repo: <?= h($REPO) ?></span>
          </div>
          <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <!-- Download -->
            <form method="post" class="me-auto d-flex flex-wrap align-items-center gap-3">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="download">
              <button class="btn btn-primary" <?= empty($_SESSION['update_meta']['zip'])?'disabled':''; ?>>
                <i class="fa-solid fa-download me-1"></i>1) Download
              </button>

              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="allow_core" name="allow_core" value="1" <?= $allow_core?'checked':''?>>
                <label class="form-check-label" for="allow_core">
                  Allow core overwrites <span class="small text-body-secondary">(index.php, .htaccess)</span>
                </label>
              </div>
            </form>

            <!-- Preview -->
            <form method="post" class="d-flex flex-wrap align-items-center gap-2">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="preview">
              <input type="hidden" name="preview" value="1">
              <input type="hidden" name="allow_core" value="<?= $allow_core? '1':'0' ?>">
              <button class="btn btn-outline-secondary" <?= empty($_SESSION['update_zip'])?'disabled':''; ?>>
                <i class="fa-regular fa-eye me-1"></i>2) Preview changes
              </button>
            </form>

            <!-- Install -->
            <form method="post" class="d-flex flex-wrap align-items-center gap-2">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="install">
              <input type="hidden" name="allow_core" value="<?= $allow_core? '1':'0' ?>">
              <button class="btn btn-success" <?= empty($_SESSION['update_zip'])?'disabled':''; ?>>
                <i class="fa-solid fa-circle-check me-1"></i>3) Install
              </button>
            </form>

            <a href="./" class="btn btn-outline-dark"><i class="fa-solid fa-house me-1"></i>Admin Home</a>
          </div>
          <?php if (!empty($_SESSION['update_zip'])): ?>
            <div class="card-footer small text-body-secondary">
              ZIP: <code><?= h($_SESSION['update_zip']) ?></code>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Report -->
      <?php if ($report): ?>
      <section class="mb-4">
        <div class="card shadow-sm">
          <div class="card-header bg-body-tertiary fw-semibold">
            <i class="fa-solid fa-file-lines me-2"></i>File operations (latest run)
          </div>
          <div class="card-body">
            <pre class="update-report"><?php echo h(implode("\n",$report)); ?></pre>
            <div class="small text-body-secondary">
              Legend: <code>DIR</code> directory; <code>FILE</code> copied/updated; <code>SAME</code> identical; <code>SKIP-dir/file</code> preserved; <code>WOULD</code> dry-run change; <code>ERR</code> permission/copy error
            </div>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <p class="small text-body-secondary">
        Tip: Using a GitHub token helps avoid API rate limits. If you expect core files to change, enable “Allow core overwrites.”
      </p>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Optional mobile sidebar toggle (wire a button in header if you add one)
function toggleSidebar(){ document.querySelector('.admin-sidebar')?.classList.toggle('show'); }
</script>
</body>
</html>