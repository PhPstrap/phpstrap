<?php
header('Content-Type: text/plain; charset=UTF-8');

echo "PWD:        ", __DIR__, PHP_EOL;
echo "open_basedir:", ini_get('open_basedir') ?: '(none)', PHP_EOL, PHP_EOL;

// Try common locations relative to dashboard/
$tries = [
  __DIR__ . '/../../_private/tika-app-3.2.3.jar',   // /www/_private/...
  __DIR__ . '/../_private/tika-app-3.2.3.jar',      // /www/lexboard.ca/_private/...
  __DIR__ . '/../../../_private/tika-app-3.2.3.jar',// one more up, just in case
  '/www/_private/tika-app-3.2.3.jar',               // absolute if allowed
];

foreach ($tries as $p) {
  $exists = is_file($p) ? 'YES' : 'NO';
  echo $p, PHP_EOL, "  exists: $exists", PHP_EOL;
  if ($exists === 'YES') {
    echo "  realpath: ", realpath($p), PHP_EOL;
    echo "  size:     ", filesize($p), " bytes", PHP_EOL;
  }
  echo PHP_EOL;
}