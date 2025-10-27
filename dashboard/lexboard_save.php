<?php
// dashboard/lexboard_save.php
require_once '../config/app.php';
require_once '../config/functions.php';
session_start();

/* ========= Auth ========= */
if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
  header('Location: ../login/');
  exit();
}
try {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user) { session_destroy(); header('Location: ../login/'); exit(); }
} catch (PDOException $e) {
  logError("lexboard_save auth: " . $e->getMessage());
  header('Location: ../login/');
  exit();
}

/* ========= Helpers ========= */
function tbl_exists(PDO $pdo, string $table): bool {
  try {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
  } catch (Throwable $e) { return false; }
}
function col_exists(PDO $pdo, string $table, string $column): bool {
  try {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
  } catch (Throwable $e) { return false; }
}
function normalize_name(string $s): string {
  $s = trim(preg_replace('/\s+/', ' ', $s));
  return $s;
}

/**
 * Ensure a court row exists; return its id.
 * Creates minimal record if missing.
 */
function ensure_court(PDO $pdo, int $jurId, string $code, string $displayName = null): int {
  $code   = strtoupper(trim($code));
  $stmt   = $pdo->prepare("SELECT id FROM lex_court WHERE jurisdiction_id = ? AND UPPER(code) = ? LIMIT 1");
  $stmt->execute([$jurId, $code]);
  $cid = $stmt->fetchColumn();
  if ($cid) return (int)$cid;

  $name = $displayName ?: $code;
  $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
  $ins = $pdo->prepare("INSERT INTO lex_court (jurisdiction_id, code, name, slug) VALUES (?,?,?,?)");
  $ins->execute([$jurId, $code, $name, $slug]);
  return (int)$pdo->lastInsertId();
}

/**
 * Find or create a firm, return id (best-effort; only if tables/columns exist).
 */
function find_or_create_firm(PDO $pdo, ?string $firmName): ?int {
  if (!$firmName) return null;
  $firmName = normalize_name($firmName);

  // Try lex_firms OR lex_firm
  $tables = [];
  if (tbl_exists($pdo, 'lex_firms')) $tables[] = 'lex_firms';
  if (tbl_exists($pdo, 'lex_firm'))  $tables[] = 'lex_firm';
  if (!$tables) return null;

  $t = $tables[0];
  $nameCol = col_exists($pdo, $t, 'name') ? 'name' : (col_exists($pdo, $t, 'firm_name') ? 'firm_name' : null);
  if (!$nameCol) return null;

  // de-dupe by exact name
  $stmt = $pdo->prepare("SELECT id FROM {$t} WHERE {$nameCol} = ? LIMIT 1");
  $stmt->execute([$firmName]);
  $id = $stmt->fetchColumn();
  if ($id) return (int)$id;

  $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $firmName));
  $cols = [$nameCol];
  $vals = [$firmName];
  $qs   = ['?'];

  if (col_exists($pdo, $t, 'slug')) { $cols[]='slug'; $vals[]=$slug; $qs[]='?'; }
  if (col_exists($pdo, $t, 'jurisdiction_id')) { $cols[]='jurisdiction_id'; $vals[]=null; $qs[]='NULL'; } // unknown
  if (col_exists($pdo, $t, 'created_at')) { $cols[]='created_at'; $vals[] = date('Y-m-d H:i:s'); $qs[]='?'; }

  $sql = "INSERT INTO {$t} (".implode(',', $cols).") VALUES (".implode(',', $qs).")";
  // convert literal NULL placeholders
  $sql = str_replace('NULL', '?', $sql); // keep consistent binds
  $stmt = $pdo->prepare($sql);
  $stmt->execute($vals);
  return (int)$pdo->lastInsertId();
}

/**
 * Find or create a lawyer, return id (best-effort; only if tables/columns exist).
 */
function find_or_create_lawyer(PDO $pdo, ?string $lawyerName): ?int {
  if (!$lawyerName) return null;
  $lawyerName = normalize_name($lawyerName);

  $tables = [];
  if (tbl_exists($pdo, 'lex_lawyers')) $tables[] = 'lex_lawyers';
  if (tbl_exists($pdo, 'lex_lawyer'))  $tables[] = 'lex_lawyer';
  if (!$tables) return null;

  $t = $tables[0];
  // choose a person name column
  $nameCol = null;
  foreach (['full_name','name','lawyer_name'] as $c) {
    if (col_exists($pdo, $t, $c)) { $nameCol = $c; break; }
  }
  if (!$nameCol) return null;

  $stmt = $pdo->prepare("SELECT id FROM {$t} WHERE {$nameCol} = ? LIMIT 1");
  $stmt->execute([$lawyerName]);
  $id = $stmt->fetchColumn();
  if ($id) return (int)$id;

  $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $lawyerName));
  $cols = [$nameCol];
  $vals = [$lawyerName];
  $qs   = ['?'];

  if (col_exists($pdo, $t, 'slug')) { $cols[]='slug'; $vals[]=$slug; $qs[]='?'; }
  if (col_exists($pdo, $t, 'created_at')) { $cols[]='created_at'; $vals[] = date('Y-m-d H:i:s'); $qs[]='?'; }

  $sql = "INSERT INTO {$t} (".implode(',', $cols).") VALUES (".implode(',', $qs).")";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($vals);
  return (int)$pdo->lastInsertId();
}

/**
 * Attach counsel to a case using whatever mapping table exists:
 * - lex_case_counsel (preferred)
 * - lex_counsel (fallback)
 */
function attach_case_counsel(PDO $pdo, int $caseId, array $rows): void {
  if (!$rows) return;

  $mapTable = null;
  foreach (['lex_case_counsel','lex_counsel'] as $t) {
    if (tbl_exists($pdo, $t)) { $mapTable = $t; break; }
  }
  if (!$mapTable) return;

  // Identify available columns
  $has = [
    'case_id'   => col_exists($pdo, $mapTable, 'case_id'),
    'lawyer_id' => col_exists($pdo, $mapTable, 'lawyer_id'),
    'firm_id'   => col_exists($pdo, $mapTable, 'firm_id'),
    'side'      => col_exists($pdo, $mapTable, 'side'),
    'role'      => col_exists($pdo, $mapTable, 'role'),
    'display'   => col_exists($pdo, $mapTable, 'display_name'), // catch-all if no ids
    'created'   => col_exists($pdo, $mapTable, 'created_at'),
  ];

  foreach ($rows as $r) {
    $vals = [];
    $cols = [];
    $qs   = [];

    if ($has['case_id']) { $cols[]='case_id'; $vals[]=$caseId; $qs[]='?'; }
    if ($has['lawyer_id'] && !empty($r['lawyer_id'])) { $cols[]='lawyer_id'; $vals[]=$r['lawyer_id']; $qs[]='?'; }
    if ($has['firm_id']   && !empty($r['firm_id']))   { $cols[]='firm_id';   $vals[]=$r['firm_id'];   $qs[]='?'; }
    if ($has['side'])   { $cols[]='side'; $vals[] = substr($r['side'] ?? 'unknown', 0, 64); $qs[]='?'; }
    if ($has['role'])   { $cols[]='role'; $vals[] = 'counsel'; $qs[]='?'; }
    if ($has['display'] && (empty($r['lawyer_id']) || empty($r['firm_id']))) {
      // store a readable fall-back if we couldn't get IDs
      $disp = trim(($r['lawyer_name'] ?? '').($r['firm_name'] ? (', '.$r['firm_name']) : ''));
      $cols[]='display_name'; $vals[]=$disp; $qs[]='?';
    }
    if ($has['created']) { $cols[]='created_at'; $vals[] = date('Y-m-d H:i:s'); $qs[]='?'; }

    if (!$cols) continue;
    $sql = "INSERT INTO {$mapTable} (".implode(',', $cols).") VALUES (".implode(',', $qs).")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);
  }
}

/* ========= Inputs ========= */
$pdfPath       = $_POST['pdf_path'] ?? '';
$sourceUrl     = trim($_POST['source_url'] ?? '');
$jurCode       = $_POST['jurisdiction_code'] ?? 'ON';
$courtCode     = $_POST['court_code'] ?? '';               // may be blank; we fix below
$neutral       = trim($_POST['neutral_citation'] ?? '');
$caseName      = trim($_POST['case_name'] ?? '');
$decisionDate  = ($_POST['decision_date'] ?? '') ?: null;
$judge         = trim($_POST['judge'] ?? '');

$proceeding    = $_POST['proceeding_type'] ?? 'other';
$outcomeNorm   = $_POST['outcome_norm'] ?? 'unknown';
$winnerSide    = $_POST['winner_side'] ?? 'unknown';
$scope         = $_POST['disposition_scope'] ?? 'unknown';
$costsOrder    = $_POST['costs_order'] ?? 'other';
$costsAmount   = strlen($_POST['costs_amount'] ?? '') ? (float)$_POST['costs_amount'] : null;
$outcomeLabel  = trim($_POST['outcome_label'] ?? '');

$aiConfidenceIn = $_POST['ai_confidence'] ?? null;
$aiConfidence   = is_numeric($aiConfidenceIn) ? max(0.0, min(1.0, (float)$aiConfidenceIn)) : 0.80;

/* Counsel payload (WAF-friendly base64 JSON) */
$counselRows = [];
if (!empty($_POST['counsel_payload_b64'])) {
  $raw = base64_decode((string)$_POST['counsel_payload_b64'], true);
  if ($raw !== false) {
    $j = json_decode($raw, true);
    if (is_array($j)) $counselRows = $j;
  }
}

/* ========= Resolve jurisdiction & court ========= */
try {
  $pdo->beginTransaction();

  // Jurisdiction
  $jurStmt = $pdo->prepare("SELECT id FROM lex_jurisdiction WHERE code = ? LIMIT 1");
  $jurStmt->execute([$jurCode]);
  $jurId = (int)$jurStmt->fetchColumn();
  if (!$jurId) throw new Exception('Jurisdiction not found');

  // Court: default to ONSC when Ontario and no/unknown code
  if (!$courtCode && strtoupper($jurCode) === 'ON') {
    $courtCode = 'ONSC';
  }

  // Ensure valid court_id (schema requires NOT NULL)
  $courtId = ensure_court($pdo, $jurId, $courtCode ?: 'ONSC', $courtCode ?: 'Ontario Superior Court of Justice');

  /* ========= Prep file URLs + hash ========= */
  $url_pdf  = $pdfPath ? ('/storage/uploads/' . basename($pdfPath)) : null;
  $url_html = $sourceUrl ?: '';

  $hashContent = null;
  if ($pdfPath && is_file($pdfPath)) {
    $hashContent = @hash_file('sha256', $pdfPath) ?: null;
  }

  /* ========= Insert case ========= */
  $sql = "
    INSERT INTO lex_cases
      (jurisdiction_id, court_id, neutral_citation, case_name, decision_date, judge,
       proceeding_type, outcome_norm, disposition_scope, winner_side, outcome_label, outcome_raw,
       costs_order, costs_amount, url_html, url_pdf, source_rss, hash_content, published_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    $jurId, $courtId, $neutral, $caseName, $decisionDate, $judge ?: null,
    $proceeding, $outcomeNorm, $scope, $winnerSide, $outcomeLabel ?: null, null,
    $costsOrder, $costsAmount, $url_html, $url_pdf, null, $hashContent
  ]);
  $caseId = (int)$pdo->lastInsertId();

  /* ========= Persist counsel if tables exist ========= */
  $attached = [];
  if ($counselRows) {
    foreach ($counselRows as $row) {
      $lawyerName = isset($row['lawyer_name']) ? normalize_name($row['lawyer_name']) : null;
      $firmName   = isset($row['firm_name'])   ? normalize_name($row['firm_name'])   : null;
      $side       = $row['side'] ?? 'unknown';

      if (!$lawyerName && !$firmName) continue;

      $lawyerId = find_or_create_lawyer($pdo, $lawyerName);
      $firmId   = find_or_create_firm($pdo, $firmName);

      $attached[] = [
        'lawyer_id'   => $lawyerId,
        'firm_id'     => $firmId,
        'lawyer_name' => $lawyerName,
        'firm_name'   => $firmName,
        'side'        => $side,
      ];
    }

    if ($attached) {
      attach_case_counsel($pdo, $caseId, $attached);
    }
  }

  /* ========= Audit ========= */
  $audit = [
    'winner_side'       => $winnerSide,
    'outcome_norm'      => $outcomeNorm,
    'disposition_scope' => $scope,
    'costs_order'       => $costsOrder,
    'costs_amount'      => $costsAmount,
    'neutral_citation'  => $neutral,
    'case_name'         => $caseName,
    'judge'             => $judge,
    'decision_date'     => $decisionDate,
    'source_url'        => $sourceUrl,
    'jurisdiction_code' => $jurCode,
    'court_code'        => $courtCode,
    'hash_pdf_sha256'   => $hashContent,
    'counsel'           => $attached, // what we actually linked (ids + names)
  ];

  // Model name (from config/ai.php if present)
  $modelUsed = 'rules-stub-v1';
  try {
    require_once __DIR__ . '/../config/ai.php';
    if (!empty($AI_CONFIG['model'])) $modelUsed = (string)$AI_CONFIG['model'];
  } catch (Throwable $e) { /* ignore */ }

  $stmt = $pdo->prepare("
    INSERT INTO lex_ai_outcome_audit (case_id, model_name, prompt_version, outcome_json, confidence)
    VALUES (?,?,?,?,?)
  ");
  $stmt->execute([
    $caseId,
    $modelUsed,
    'v1',
    json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    $aiConfidence
  ]);

  $pdo->commit();

  $_SESSION['verification_success'] = "Saved case “" . ($neutral ?: $caseId) . "”.";
  header("Location: ../search.php?q=" . urlencode($neutral ?: (string)$caseId));
  exit();

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  logError("lexboard_save error: " . $e->getMessage());
  $_SESSION['lex_err'] = "Save failed: " . $e->getMessage();
  header('Location: lexboard_upload.php');
  exit();
}