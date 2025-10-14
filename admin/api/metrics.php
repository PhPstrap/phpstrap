<?php
/**
 * Returns system metrics as JSON
 * Safe, no shell required; Linux-friendly.
 */
header('Content-Type: application/json');

function readFirstNonLoopbackIface() {
  $base = '/sys/class/net';
  if (!is_dir($base)) return null;
  foreach (scandir($base) as $if) {
    if ($if === '.' || $if === '..' || $if === 'lo') continue;
    $rx = "$base/$if/statistics/rx_bytes";
    $tx = "$base/$if/statistics/tx_bytes";
    if (is_readable($rx) && is_readable($tx)) {
      return [
        'iface' => $if,
        'rx_bytes' => (int)@file_get_contents($rx),
        'tx_bytes' => (int)@file_get_contents($tx),
      ];
    }
  }
  return null;
}

$load = function_exists('sys_getloadavg') ? @sys_getloadavg() : null;

// Memory (Linux /proc/meminfo)
$mem_total = $mem_free = $mem_avail = null;
if (is_readable('/proc/meminfo')) {
  $lines = @file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($lines) {
    foreach ($lines as $ln) {
      if (strpos($ln, 'MemTotal:') === 0) $mem_total = (int)filter_var($ln, FILTER_SANITIZE_NUMBER_INT) * 1024;
      if (strpos($ln, 'MemAvailable:') === 0) $mem_avail = (int)filter_var($ln, FILTER_SANITIZE_NUMBER_INT) * 1024;
    }
  }
}
// Fallback memory (PHP process, not system)
if ($mem_total === null) {
  $mem_total = null; // unknown
  $mem_avail = null;
}

$mem_used = ($mem_total !== null && $mem_avail !== null) ? max(0, $mem_total - $mem_avail) : null;

// Disk (root)
$disk_total = @disk_total_space('/');
$disk_free  = @disk_free_space('/');
$disk_used  = ($disk_total !== false && $disk_free !== false) ? max(0, $disk_total - $disk_free) : null;

// Uptime (Linux)
$uptime = null;
if (is_readable('/proc/uptime')) {
  $u = @file_get_contents('/proc/uptime');
  if ($u !== false) {
    $secs = (int)floatval(explode(' ', trim($u))[0]);
    $d = intdiv($secs, 86400); $h = intdiv($secs%86400, 3600); $m = intdiv($secs%3600, 60);
    $uptime = "Uptime: {$d}d {$h}h {$m}m";
  }
}

echo json_encode([
  'ts'         => time(),
  'load_avg'   => is_array($load) ? array_map(fn($v)=>round($v,2), $load) : null,
  'mem_total'  => $mem_total,
  'mem_used'   => $mem_used,
  'mem_used_gb'=> $mem_used ? round($mem_used/1024/1024/1024, 3) : null,
  'disk_total' => $disk_total !== false ? $disk_total : null,
  'disk_used'  => $disk_used,
  'uptime'     => $uptime,
  'net'        => readFirstNonLoopbackIface(),
]);