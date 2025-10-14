<?php
/**
 * Returns DB rollups for charts:
 * - New users/day (last 14 days)
 * - Admin activity/hour (last 24 hours)
 */
header('Content-Type: application/json');

require_once '../../config/database.php';

try { $pdo = getDbConnection(); } catch (Throwable $e) { $pdo = null; }

$users = ['labels'=>[], 'data'=>[]];
$admin = ['labels'=>[], 'data'=>[]];

if ($pdo instanceof PDO) {
  // New users per day (last 14)
  $stmt = $pdo->prepare("
    SELECT DATE(created_at) as d, COUNT(*) as c
    FROM users
    WHERE created_at >= (CURDATE() - INTERVAL 13 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d ASC
  ");
  $stmt->execute();
  $map = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $map[$r['d']] = (int)$r['c']; }
  for ($i=13; $i>=0; $i--) {
    $day = (new DateTime())->sub(new DateInterval("P{$i}D"))->format('Y-m-d');
    $users['labels'][] = $day;
    $users['data'][]   = $map[$day] ?? 0;
  }

  // Admin activity per hour (last 24h)
  // Uses admin_activity_log.created_at
  $stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as h, COUNT(*) as c
    FROM admin_activity_log
    WHERE created_at >= (NOW() - INTERVAL 23 HOUR)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H')
    ORDER BY h ASC
  ");
  try { $stmt->execute(); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $rows = []; }
  $map2 = [];
  foreach ($rows as $r) { $map2[$r['h']] = (int)$r['c']; }
  for ($i=23; $i>=0; $i--) {
    $dt = (new DateTime())->sub(new DateInterval("PT{$i}H"))->setTime((int)date('H')-$i<0?0:(int)date('H')-$i, 0, 0);
    $lbl = $dt->format('Y-m-d H:00:00');
    $admin['labels'][] = $lbl;
    $admin['data'][]   = $map2[$lbl] ?? 0;
  }
}

echo json_encode([
  'users_last14' => $users,
  'admin_last24' => $admin,
]);