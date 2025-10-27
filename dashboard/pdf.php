<?php
/**
 * pdf-extract.php — PDF → Text uploader with Apache Tika (no SSH) + fallbacks
 * - Primary: Apache Tika APP JAR (CLI) at /www/lexboard.ca/dashboard/_private/tika-app-3.2.3.jar
 * - Fallbacks: pdftotext (Poppler), smalot/pdfparser (Composer), optional OCR.Space (hosted)
 * - Includes diagnostics, size limits, PDF magic check, temp cleanup
 * - NEW: Rate limiting (10 uploads/IP/hour) with standard X-RateLimit headers
 *
 * Place this file at: /www/lexboard.ca/dashboard/pdf-extract.php
 * Place Tika JAR at:  /www/lexboard.ca/dashboard/_private/tika-app-3.2.3.jar
 * Block web access to /_private with an .htaccess containing:  Deny from all
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Keep extraction bounded
@set_time_limit(30);
ini_set('memory_limit', '256M');

session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
function csrf_check($t){ return hash_equals($_SESSION['csrf_token'] ?? '', (string)$t); }

/* ================= Configuration ================= */
const MAX_BYTES         = 20 * 1024 * 1024;     // 20 MB
const TRUST_UPLOAD_MIME = false;                // validate via magic header anyway

// Force Tika APP JAR path (under dashboard so PHP can read it)
const TIKA_APP_PATH_HINT = __DIR__ . '/_private/tika-app-3.2.3.jar';

// If host needs full Java path, set here; otherwise leave blank to use PATH "java"
const JAVA_CMD_HINT = ''; // e.g., '/usr/bin/java'

// Optional hosted OCR fallback (set true + API key to enable)
const USE_OCRSPACE     = false;
const OCRSPACE_API_KEY = ''; // put your key here if enabling OCR.Space

// Local OCR via tesseract (usually unavailable on shared hosting)
const ALLOW_OCR_LOCAL = false;

/* ================= Rate Limiting ================= */
// Limit: 10 uploads per IP per 1 hour window
const RL_LIMIT  = 10;
const RL_WINDOW = 3600; // seconds

// Returns [bool exceeded, int remaining, int reset_epoch]
function rl_check_and_increment(): array {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $bucket = sys_get_temp_dir().'/pdfx_rl_'.preg_replace('/[^a-z0-9_.:-]/i','_',$ip).'.json';

    $now   = time();
    $start = $now;
    $count = 0;

    if (is_file($bucket)) {
        $data  = json_decode(@file_get_contents($bucket), true) ?: [];
        $start = (int)($data['start'] ?? $now);
        $count = (int)($data['count'] ?? 0);

        // reset window if expired
        if ($now - $start >= RL_WINDOW) {
            $start = $now;
            $count = 0;
        }
    }

    // already at limit
    if ($count >= RL_LIMIT) {
        $reset     = $start + RL_WINDOW;
        $remaining = 0;
        return [true, $remaining, $reset];
    }

    // increment and persist
    $count++;
    @file_put_contents($bucket, json_encode(['start'=>$start, 'count'=>$count]));
    $remaining = max(RL_LIMIT - $count, 0);
    $reset     = $start + RL_WINDOW;
    return [false, $remaining, $reset];
}

/* Try Composer autoload if present (for smalot/pdfparser) */
foreach ([__DIR__.'/vendor/autoload.php', dirname(__DIR__).'/vendor/autoload.php'] as $auto) {
    if (is_file($auto)) { require_once $auto; break; }
}

/* ================= Helpers ================= */
function tmp_path($suffix=''){
  $base = sys_get_temp_dir();
  return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ('pdfx_'.bin2hex(random_bytes(8)).$suffix);
}
function disabled($fn){
  $d = array_map('trim', explode(',', (string)ini_get('disable_functions') ?: ''));
  return in_array($fn, $d, true) || !function_exists($fn);
}
function looks_like_pdf($path){
  $fh = @fopen($path,'rb'); if(!$fh) return false;
  $magic = fread($fh,5); fclose($fh);
  return $magic === '%PDF-';
}
function which($bin){
  if (disabled('shell_exec')) return null;
  $out = @trim((string)shell_exec('which '.escapeshellarg($bin).' 2>/dev/null'));
  return $out ?: null;
}
function java_cmd(): string { return JAVA_CMD_HINT ?: 'java'; }

/* ================= Extractors ================= */
// 1) Apache Tika APP JAR (CLI)
function extract_with_tika_app(string $pdf, string $tikaJar, &$why=null): ?string {
  if (!$tikaJar || !is_file($tikaJar)) { $why = 'tika-app jar not found'; return null; }
  if (disabled('shell_exec')) { $why = 'shell_exec disabled'; return null; }

  $javaCheck = @shell_exec(escapeshellcmd(java_cmd()) . ' -version 2>&1');
  if (!$javaCheck) { $why = 'java not available on host'; return null; }

  // -t = text; -eUTF-8 = force UTF-8; suppress stderr noise
  $cmd = escapeshellcmd(java_cmd()) . ' -jar ' . escapeshellarg($tikaJar) . ' -t -eUTF-8 ' . escapeshellarg($pdf) . ' 2>/dev/null';
  $out = @shell_exec($cmd);
  if (!is_string($out)) { $why = 'tika-app returned null'; return null; }
  $out = trim($out);
  if ($out === '') { $why = 'tika-app extracted empty'; return null; }
  return $out;
}

// 2) pdftotext (Poppler)
function extract_with_pdftotext(string $pdf, &$why=null): ?string {
  if (disabled('shell_exec')) { $why = 'shell_exec disabled'; return null; }
  $bin = which('pdftotext');
  if (!$bin) { $why = 'pdftotext not found'; return null; }
  $cmd = escapeshellcmd($bin).' -layout -enc UTF-8 '.escapeshellarg($pdf).' - 2>/dev/null';
  $out = @shell_exec($cmd);
  if (!is_string($out)) { $why = 'pdftotext returned null'; return null; }
  $out = trim($out);
  if ($out === '') { $why = 'pdftotext extracted empty'; return null; }
  return $out;
}

// 3) smalot/pdfparser (pure PHP)
function extract_with_smalot(string $pdf, &$why=null): ?string {
  if (!class_exists(\Smalot\PdfParser\Parser::class)) { $why = 'smalot/pdfparser not installed'; return null; }
  try {
    $parser = new \Smalot\PdfParser\Parser();
    $doc = $parser->parseFile($pdf);
    $text = trim($doc->getText() ?? '');
    if ($text === '') { $why = 'smalot extracted empty'; return null; }
    return $text;
  } catch (\Throwable $e) {
    $why = 'smalot error: '.$e->getMessage();
    return null;
  }
}

// 4) Hosted OCR.Space (optional)
function extract_with_ocrspace(string $pdf, string $apiKey, &$why=null): ?string {
  if (!$apiKey) { $why = 'OCR.Space key missing'; return null; }
  if (!function_exists('curl_init')) { $why = 'cURL not available'; return null; }
  $ch = curl_init('https://api.ocr.space/parse/image');
  $post = [
    'apikey' => $apiKey,
    'file'   => new CURLFile($pdf, 'application/pdf', basename($pdf)),
    'OCREngine' => 2,
    'isTable'   => 'true',
    'isOverlayRequired' => 'false',
  ];
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$post, CURLOPT_TIMEOUT=>120]);
  $raw = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);
  if ($raw === false || !$raw) { $why = 'OCR.Space request failed'.($err?": $err":''); return null; }
  $j = json_decode($raw, true);
  $txt = is_array($j) ? ($j['ParsedResults'][0]['ParsedText'] ?? '') : '';
  $txt = is_string($txt) ? trim($txt) : '';
  if ($txt === '') { $why = 'OCR.Space returned empty'; return null; }
  return $txt;
}

// 5) Local OCR via tesseract (off by default on shared hosting)
function extract_with_ocr_local(string $pdf, &$why=null): ?string {
  if (!ALLOW_OCR_LOCAL){ $why='local OCR disabled'; return null; }
  if (disabled('shell_exec')) { $why='shell_exec disabled'; return null; }
  $convert = which('magick') ?: which('convert');
  $tesseract = which('tesseract');
  if (!$convert)  { $why='ImageMagick not found'; return null; }
  if (!$tesseract){ $why='tesseract not found'; return null; }
  $tmpDir = tmp_path('_ocr'); if (!@mkdir($tmpDir,0700,true)) { $why='cannot create ocr tmp dir'; return null; }
  $pattern = $tmpDir.DIRECTORY_SEPARATOR.'page-%03d.png';
  @shell_exec(escapeshellcmd($convert).' -density 300 '.escapeshellarg($pdf).' -alpha off -depth 8 '.escapeshellarg($pattern).' 2>/dev/null');
  $files = glob($tmpDir.DIRECTORY_SEPARATOR.'page-*.png');
  if (!$files){ $why='no rasterized pages'; @rmdir($tmpDir); return null; }
  $all = [];
  foreach ($files as $img){
    $base = preg_replace('/\.png$/i','',$img);
    @shell_exec(escapeshellcmd($tesseract).' '.escapeshellarg($img).' '.escapeshellarg($base).' -l eng 2>/dev/null');
    $txt = @file_get_contents($base.'.txt');
    if ($txt) $all[] = trim($txt);
    @unlink($img); @unlink($base.'.txt');
  }
  @rmdir($tmpDir);
  $joined = trim(implode("\n\n",$all));
  if ($joined===''){ $why='tesseract returned empty'; return null; }
  return $joined;
}

/* ================= Request handling ================= */
$result = null; $error = null; $downloadName = null; $diag = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {

  // --- Enforce rate limiting for POST uploads ---
  [$exceeded, $remaining, $reset] = rl_check_and_increment();
  header('X-RateLimit-Limit: '.RL_LIMIT);
  header('X-RateLimit-Remaining: '.$remaining);
  header('X-RateLimit-Reset: '.$reset);
  if ($exceeded) {
    http_response_code(429);
    echo 'Rate limit reached. Try again after '.max($reset - time(), 0).' seconds.';
    exit;
  }

  // Download path
  if (isset($_POST['download']) && isset($_POST['txt_path']) && is_file($_POST['txt_path'])) {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Length: '.filesize($_POST['txt_path']));
    $dlName = (preg_replace('/[^a-z0-9._-]+/i','-', $_POST['download_name'] ?? 'extracted')).'.txt';
    header('Content-Disposition: attachment; filename="'.$dlName.'"');
    readfile($_POST['txt_path']);
    @unlink($_POST['txt_path']);
    exit;
  }

  // Upload/extract path
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid request. Refresh and try again.';
  } elseif (empty($_FILES['pdf']['tmp_name']) || !is_uploaded_file($_FILES['pdf']['tmp_name'])) {
    $error = 'No file uploaded.';
  } else {
    $f = $_FILES['pdf'];
    if (($f['error'] ?? UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) {
      $error = 'Upload error code: '.(int)$f['error'];
    } elseif (($f['size'] ?? 0) > MAX_BYTES) {
      $error = 'File too large (max '.number_format(MAX_BYTES/(1024*1024),1).' MB).';
    } else {
      $tmpPdf = tmp_path('.pdf');
      if (!@move_uploaded_file($f['tmp_name'], $tmpPdf)) {
        $error = 'Could not save upload.';
      } elseif (!looks_like_pdf($tmpPdf)) {
        @unlink($tmpPdf);
        $error = 'The file does not look like a valid PDF.';
      } else {
        // (Optional) MIME check (uncomment to enable)
        // $finfo = finfo_open(FILEINFO_MIME_TYPE);
        // $mime  = $finfo ? finfo_file($finfo, $tmpPdf) : null;
        // if ($finfo) finfo_close($finfo);
        // if (!in_array($mime, ['application/pdf','application/x-pdf'], true)) {
        //     @unlink($tmpPdf);
        //     $error = 'Invalid file type.';
        // }

        // Diagnostics snapshot
        $diag = [
          'shell_exec'       => !disabled('shell_exec') ? 'ENABLED' : 'DISABLED',
          'java_cmd'         => JAVA_CMD_HINT ?: 'java (PATH)',
          'java_detect'      => !disabled('shell_exec') ? trim((string)@shell_exec(escapeshellcmd(java_cmd()).' -version 2>&1')) ?: 'NOT FOUND' : 'N/A',
          'tika_app_hint'    => TIKA_APP_PATH_HINT,
          'tika_app_exists'  => is_file(TIKA_APP_PATH_HINT) ? 'YES' : 'NO',
          'pdftotext'        => which('pdftotext') ?: 'NOT FOUND',
          'composer_autoload'=> (class_exists(\Smalot\PdfParser\Parser::class) ? 'OK' : (is_file(__DIR__.'/vendor/autoload.php')?'autoload present (pdfparser missing)':'missing')),
          'smalot_pdfparser' => class_exists(\Smalot\PdfParser\Parser::class) ? 'OK' : 'NOT INSTALLED',
        ];

        $why1=$why2=$why3=$why4=null;
        $text = extract_with_tika_app($tmpPdf, TIKA_APP_PATH_HINT, $why1)
          ?? extract_with_pdftotext($tmpPdf, $why2)
          ?? extract_with_smalot($tmpPdf, $why3)
          ?? (USE_OCRSPACE ? extract_with_ocrspace($tmpPdf, OCRSPACE_API_KEY, $why4) : null)
          ?? extract_with_ocr_local($tmpPdf, $why4);

        if ($text === null || $text === '') {
          $error = 'Sorry—couldn’t extract text.';
          $diag['reasons'] = [
            'tika_app'   => $why1,
            'pdftotext'  => $why2,
            'smalot'     => $why3,
            'ocr_fallback'=> $why4,
          ];
        } else {
          $text = preg_replace("/\r\n?/", "\n", $text);
          $downloadName = preg_replace('/[^a-z0-9._-]+/i','-', pathinfo($f['name'], PATHINFO_FILENAME)) ?: 'extracted';
          $txtPath = tmp_path('.txt');
          file_put_contents($txtPath, $text);
          $result = ['preview'=>mb_substr($text,0,100000), 'txt_path'=>$txtPath];
        }
        @unlink($tmpPdf);
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>PDF → Text Extractor (Tika-ready)</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
<style>
  main { max-width: 960px; margin: 2rem auto; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
  textarea { min-height: 320px; }
  .muted { opacity: .8; }
  .grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); gap:.75rem; }
  code.kv { display:inline-block; padding:.15rem .35rem; border-radius:.3rem; background:#f3f3f3; }
</style>
</head>
<body>
<main>
  <h1>PDF → Text Extractor</h1>
  <p class="muted">This uses <strong>Apache Tika (CLI JAR)</strong> at <code>/www/lexboard.ca/dashboard/_private/tika-app-3.2.3.jar</code>, with fallbacks if needed. Rate limited to <?= RL_LIMIT ?> uploads/IP/hour.</p>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <label for="pdf">Choose a PDF (max <?= number_format(MAX_BYTES/(1024*1024),1) ?> MB)</label>
    <input type="file" id="pdf" name="pdf" accept="application/pdf" required>
    <button type="submit">Extract Text</button>
  </form>

  <?php if ($error): ?>
  <article class="contrast" style="margin-top:1rem;">
    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
  </article>
  <?php endif; ?>

  <?php if (!empty($diag)): ?>
  <details open style="margin-top:1rem;">
    <summary><strong>Diagnostics</strong></summary>
    <div class="grid">
      <div><strong>shell_exec:</strong> <code class="kv"><?= htmlspecialchars((string)$diag['shell_exec']) ?></code></div>
      <div><strong>Java cmd:</strong> <code class="kv"><?= htmlspecialchars((string)$diag['java_cmd']) ?></code></div>
      <div><strong>Java detect:</strong> <code class="kv"><?= htmlspecialchars(mb_strimwidth((string)$diag['java_detect'],0,120,'…')) ?></code></div>
      <div><strong>Tika app (hint):</strong> <code class="kv"><?= htmlspecialchars((string)$diag['tika_app_hint']) ?></code></div>
      <div><strong>Tika app exists:</strong> <code class="kv"><?= htmlspecialchars((string)$diag['tika_app_exists']) ?></code></div>
      <div><strong>pdftotext:</strong> <code class="kv"><?= htmlspecialchars((string)$diag['pdftotext']) ?></code></div>
      <div><strong>Composer autoload:</strong> <code class="kv"><?= htmlspecialchars((string)$diag['composer_autoload']) ?></code></div>
      <div><strong>smalot/pdfparser:</strong> <code class="kv"><?= htmlspecialchars((string)$diag['smalot_pdfparser']) ?></code></div>
    </div>
    <?php if (!empty($diag['reasons'])): ?>
      <p class="muted" style="margin-top:.5rem;">
        Reasons: Tika app → <?= htmlspecialchars((string)$diag['reasons']['tika_app']) ?>,
        pdftotext → <?= htmlspecialchars((string)$diag['reasons']['pdftotext']) ?>,
        smalot → <?= htmlspecialchars((string)$diag['reasons']['smalot']) ?>,
        OCR → <?= htmlspecialchars((string)$diag['reasons']['ocr_fallback']) ?>.
      </p>
    <?php endif; ?>
  </details>
  <?php endif; ?>

  <?php if ($result): ?>
    <h2>Preview</h2>
    <textarea class="mono" readonly><?= htmlspecialchars($result['preview']) ?></textarea>
    <form method="post" style="margin-top:1rem;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="txt_path" value="<?= htmlspecialchars($result['txt_path']) ?>">
      <input type="hidden" name="download_name" value="<?= htmlspecialchars($downloadName) ?>">
      <button type="submit" name="download" value="1">Download as .txt</button>
    </form>
  <?php endif; ?>

  <details style="margin-top:2rem;">
    <summary>Tips</summary>
    <ul>
      <li><strong>Keep the JAR private:</strong> ensure <code>/www/lexboard.ca/dashboard/_private/.htaccess</code> contains <code>Deny from all</code>.</li>
      <li>If Diagnostics shows <em>java not available</em>, use the pure-PHP fallback by uploading a Composer <code>vendor/</code> with <code>smalot/pdfparser</code>, or enable OCR.Space.</li>
      <li>You can set a full Java path via <code>JAVA_CMD_HINT</code> if your host requires it.</li>
    </ul>
  </details>
</main>
</body>
</html>