<?php
require_once('spp/sppinit.php');
$appname = \SPP\Scheduler::getContext() ?: 'default';
$cacheFile = SPP_BASE_DIR . '/var/cache/routes_' . $appname . '.php';
echo "Appname: $appname\n";
echo "Cache file: $cacheFile\n";
echo "Exists? " . (file_exists($cacheFile) ? "YES" : "NO") . "\n";
$isDev = getenv('APP_ENV') === 'local' || (defined('SPP_DEBUG') && SPP_DEBUG);
echo "IsDev? " . ($isDev ? "YES" : "NO") . "\n";
