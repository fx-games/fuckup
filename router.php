<?php
// php -S ignores .htaccess; this blocks what must never be served over HTTP.
$root = strtolower(__DIR__);
$file = strtolower(realpath(__DIR__ . rawurldecode(strtok($_SERVER['REQUEST_URI'], '?'))) ?: '');
$deny = ['/router.php', '/start.sh', '/webstats', '/xterm/network', '/xterm/sub', '/xterm/niswes.db', '/xterm/intel.sql'];
foreach ($deny as $d) {
    if (str_starts_with($file, $root . $d) && $file !== $root . '/xterm/sub/backend.php') {
        http_response_code(403);
        exit;
    }
}
if (str_contains(substr($file, strlen($root)), '/.') || str_ends_with($file, '.log')) {
    http_response_code(403);
    exit;
}
return false;
