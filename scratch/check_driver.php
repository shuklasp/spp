<?php
define('SPP_APP_DIR', __DIR__ . '/..');
require_once __DIR__ . '/../spp/system/settings.php';
require_once __DIR__ . '/../spp/core/class.autoloader.php';
$driver = \SPPMod\SPPCache\SPPCacheManager::getDriver();
echo get_class($driver) . "\n";
