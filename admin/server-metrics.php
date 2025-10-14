<?php
/**
 * PhPstrap Admin — Server Metrics Dashboard
 * - Bootstrap 5.3 + Chart.js
 * - Uses /admin/api/metrics.php (live system) and /admin/api/stats.php (DB)
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/app.php';

// Auth include (support multiple paths)
$auth_paths = ['admin-auth.php', 'includes/admin-auth.php', '../includes/admin-auth.php'];
foreach ($auth_paths as $path) { if (file_exists($path)) { require_once $path; break; } }

initializeApp();
if (function_exists('requireAdminAuth')) { requireAdminAuth(); }
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
  header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'server-metrics.php'));
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Settings (site name/version)
$pdo = null; $db_ok = false; $site_name = 'PhPstrap Admin';
try {
  $pdo = getDbConnection(); $db_ok = $pdo instanceof PDO;
  if ($db_ok) {
    $stmt = $pdo->query("SELECT value FROM settings WHERE `key`='site_name' LIMIT 1");
    if ($v = $stmt->fetchColumn()) $site_name = $v;
  }
} catch (Throwable $e) {}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Server Metrics - <?= h($site_name) ?></title>

<link href="/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<style>
  .metric-card { border-radius: 1rem; }
  .chart-wrap { min-height: 260px; }
</style>
</head>
<body class="bg-body-tertiary">
<div class="d-flex">
  <!-- Sidebar -->
  <aside class="admin-sidebar bg-dark text-white">
    <div class="p-3 border-bottom border-secondary-subtle d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <i class="fa-solid fa-server me-2"></i><strong>PhPstrap Admin</strong>
      </div>
      <button class="btn btn-sm btn-outline-light d-lg-none" id="btnSidebarToggle" aria-label="Close menu">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <?php
      $activeKey = 'server-metrics';
      include __DIR__ . '/includes/admin-sidebar.php';
    ?>
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
              <h1 class="h4 mb-0">Server Metrics</h1>
              <div class="small text-body-secondary mt-1">
                <i class="fa-regular fa-clock me-1"></i><span id="nowTime"></span>
                <span class="badge ms-2 <?= $db_ok ? 'text-bg-success' : 'text-bg-danger' ?>">
                  <?= $db_ok ? 'Database Connected' : 'Database Offline' ?>
                </span>
              </div>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:220px">
              <span class="input-group-text"><i class="fa-solid fa-rotate"></i></span>
              <select id="refreshMs" class="form-select">
                <option value="2000">2s</option>
                <option value="5000" selected>5s</option>
                <option value="10000">10s</option>
                <option value="30000">30s</option>
              </select>
              <button id="btnPause" class="btn btn-outline-secondary">Pause</button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <div class="admin-content container-fluid">
      <!-- Top stats -->
      <section class="my-3">
        <div class="row g-3">
          <div class="col-12 col-md-3">
            <div class="card metric-card shadow-sm">
              <div class="card-body">
                <div class="small text-body-secondary">Load Avg (1/5/15m)</div>
                <div class="fs-4 fw-semibold" id="loadAvg">–</div>
                <div class="small text-body-secondary" id="uptimeTxt">Uptime: –</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="card metric-card shadow-sm">
              <div class="card-body">
                <div class="small text-body-secondary">Memory Used</div>
                <div class="fs-4 fw-semibold"><span id="memUsed">–</span> / <span id="memTotal">–</span></div>
                <div class="progress mt-2" style="height:6px"><div id="memBar" class="progress-bar" style="width:0%"></div></div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="card metric-card shadow-sm">
              <div class="card-body">
                <div class="small text-body-secondary">Disk Used (/)</div>
                <div class="fs-4 fw-semibold"><span id="diskUsed">–</span> / <span id="diskTotal">–</span></div>
                <div class="progress mt-2" style="height:6px"><div id="diskBar" class="progress-bar" style="width:0%"></div></div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="card metric-card shadow-sm">
              <div class="card-body">
                <div class="small text-body-secondary">Net (Δ per refresh)</div>
                <div class="fs-5"><i class="fa-solid fa-arrow-down-long me-1"></i><span id="netIn">–</span> ·
                  <i class="fa-solid fa-arrow-up-long ms-2 me-1"></i><span id="netOut">–</span></div>
                <div class="small text-body-secondary" id="ifaceName">iface: –</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Charts -->
      <section class="my-4">
        <div class="row g-3">
          <div class="col-12 col-xl-6">
            <div class="card shadow-sm">
              <div class="card-header bg-body-tertiary fw-semibold"><i class="fa-solid fa-microchip me-2"></i>Load Average (live)</div>
              <div class="card-body"><canvas id="loadChart" class="chart-wrap"></canvas></div>
            </div>
          </div>
          <div class="col-12 col-xl-6">
            <div class="card shadow-sm">
              <div class="card-header bg-body-tertiary fw-semibold"><i class="fa-solid fa-memory me-2"></i>Memory Usage (live)</div>
              <div class="card-body"><canvas id="memChart" class="chart-wrap"></canvas></div>
            </div>
          </div>

          <div class="col-12 col-xl-6">
            <div class="card shadow-sm">
              <div class="card-header bg-body-tertiary fw-semibold"><i class="fa-solid fa-user-plus me-2"></i>New Users per Day (last 14)</div>
              <div class="card-body"><canvas id="usersChart" class="chart-wrap"></canvas></div>
            </div>
          </div>
          <div class="col-12 col-xl-6">
            <div class="card shadow-sm">
              <div class="card-header bg-body-tertiary fw-semibold"><i class="fa-solid fa-list-check me-2"></i>Admin Activity per Hour (last 24)</div>
              <div class="card-body"><canvas id="adminChart" class="chart-wrap"></canvas></div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const $ = (q) => document.querySelector(q);
function fmtBytes(v){ if(v==null) return '–'; const u=['B','KB','MB','GB','TB']; let i=0; while(v>=1024 && i<u.length-1){ v/=1024; i++; } return v.toFixed(1)+' '+u[i]; }
function nowStr(){ const d=new Date(); return d.toLocaleString(); }
$('#nowTime').textContent = nowStr(); setInterval(()=>$('#nowTime').textContent=nowStr(),1000);

let timer=null, paused=false;
$('#btnPause').addEventListener('click', () => { paused = !paused; $('#btnPause').textContent = paused ? 'Resume' : 'Pause'; });
function restartTimer(){
  if (timer) clearInterval(timer);
  timer = setInterval(()=>{ if(!paused) fetchMetrics(); }, parseInt($('#refreshMs').value, 10));
}
$('#refreshMs').addEventListener('change', restartTimer);

// Charts
const loadChart = new Chart($('#loadChart'), { type:'line', data:{ labels:[], datasets:[{label:'Load (1m)', data:[]}] }, options:{ animation:false, scales:{ y:{ beginAtZero:true } }, plugins:{legend:{display:true}} } });
const memChart  = new Chart($('#memChart'),  { type:'line', data:{ labels:[], datasets:[{label:'Memory Used (GB)', data:[]}] }, options:{ animation:false, scales:{ y:{ beginAtZero:true } } } });
const usersChart = new Chart($('#usersChart'),{ type:'bar',  data:{ labels:[], datasets:[{label:'New Users', data:[]}] }, options:{ scales:{ y:{ beginAtZero:true, precision:0 } } } });
const adminChart = new Chart($('#adminChart'),{ type:'bar',  data:{ labels:[], datasets:[{label:'Admin Events', data:[]}] }, options:{ scales:{ y:{ beginAtZero:true, precision:0 } } } });

let lastNet=null;
async function fetchMetrics(){
  try{
    const r = await fetch('api/metrics.php');
    const j = await r.json();

    // Top widgets
    $('#loadAvg').textContent = j.load_avg ? j.load_avg.join(' / ') : '–';
    $('#uptimeTxt').textContent = j.uptime || 'Uptime: –';

    if (j.mem_total && j.mem_used != null) {
      $('#memTotal').textContent = fmtBytes(j.mem_total);
      $('#memUsed').textContent  = fmtBytes(j.mem_used);
      const pct = Math.min(100, Math.round((j.mem_used/j.mem_total)*100));
      $('#memBar').style.width = pct+'%';
      $('#memBar').className = 'progress-bar ' + (pct>85 ? 'bg-danger' : pct>70 ? 'bg-warning' : 'bg-success');
    }

    if (j.disk_total && j.disk_used != null) {
      $('#diskTotal').textContent = fmtBytes(j.disk_total);
      $('#diskUsed').textContent  = fmtBytes(j.disk_used);
      const pct = Math.min(100, Math.round((j.disk_used/j.disk_total)*100));
      $('#diskBar').style.width = pct+'%';
      $('#diskBar').className = 'progress-bar ' + (pct>90 ? 'bg-danger' : pct>75 ? 'bg-warning' : 'bg-success');
    }

    if (j.net && j.net.iface) {
      $('#ifaceName').textContent = 'iface: ' + j.net.iface;
      if (lastNet && lastNet.iface===j.net.iface) {
        const din  = Math.max(0, j.net.rx_bytes - lastNet.rx_bytes);
        const dout = Math.max(0, j.net.tx_bytes - lastNet.tx_bytes);
        $('#netIn').textContent  = fmtBytes(din)  + '/Δ';
        $('#netOut').textContent = fmtBytes(dout) + '/Δ';
      }
      lastNet = j.net;
    }

    // Live charts
    const t = new Date().toLocaleTimeString();
    if (loadChart.data.labels.length>120){ loadChart.data.labels.shift(); loadChart.data.datasets[0].data.shift(); }
    loadChart.data.labels.push(t);
    loadChart.data.datasets[0].data.push(j.load_avg ? j.load_avg[0] : null);
    loadChart.update();

    if (memChart.data.labels.length>120){ memChart.data.labels.shift(); memChart.data.datasets[0].data.shift(); }
    memChart.data.labels.push(t);
    memChart.data.datasets[0].data.push(j.mem_used_gb ?? null);
    memChart.update();

  }catch(e){ /* swallow */ }
}

async function fetchDbStats(){
  try{
    const r = await fetch('api/stats.php');
    const j = await r.json();

    // Users per day (last 14)
    usersChart.data.labels = j.users_last14.labels;
    usersChart.data.datasets[0].data = j.users_last14.data;
    usersChart.update();

    // Admin activity per hour (last 24)
    adminChart.data.labels = j.admin_last24.labels;
    adminChart.data.datasets[0].data = j.admin_last24.data;
    adminChart.update();

  }catch(e){}
}

fetchMetrics();
fetchDbStats();
restartTimer();
</script>
</body>
</html>