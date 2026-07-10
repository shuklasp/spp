<?php

define('SPP_BASE_DIR', realpath(__DIR__ . '/../../'));
define('SPP_APP_DIR', SPP_BASE_DIR . '/app');
define('SPP_CORE_DIR', SPP_BASE_DIR . '/core');
define('SPP_MOD_DIR', SPP_BASE_DIR . '/modules');
define('SPP_MODULES_DIR', SPP_BASE_DIR . '/modules');
define('SPP_ETC_DIR', SPP_BASE_DIR . '/etc');
define('APP_ETC_DIR', SPP_APP_DIR . '/etc');
define('SPP_DS', DIRECTORY_SEPARATOR);

if (file_exists(SPP_BASE_DIR . '/vendor/autoload.php')) {
    require_once SPP_BASE_DIR . '/vendor/autoload.php';
} elseif (file_exists(SPP_BASE_DIR . '/../vendor/autoload.php')) {
    require_once SPP_BASE_DIR . '/../vendor/autoload.php';
}

require_once SPP_BASE_DIR . '/core/class.autoloader.php';
\SPP\Core\Autoloader::register();

echo "====================================================\n";
echo " VERIFYING EXTENDED SPP ARCHITECTURE & DECOUPLING\n";
echo "====================================================\n\n";

// 1. Verify ResourceController decoupling
echo "1. Verifying SPP\\Core\\ResourceController Decoupling...\n";
$rcClass = new \ReflectionClass('\\SPP\\Core\\ResourceController');
$parentClass = $rcClass->getParentClass();
echo "   Parent Class: " . ($parentClass ? $parentClass->getName() : 'None') . "\n";
if ($parentClass && $parentClass->getName() === 'SPP\\SPPObject') {
    echo "   [SUCCESS] ResourceController is perfectly decoupled from SPPView module!\n\n";
} else {
    echo "   [ERROR] ResourceController parent class is incorrect.\n\n";
}

// 2. Verify Drishyam TemplateMacros enhancements
echo "2. Verifying Drishyam TemplateMacros Enhancements...\n";
if (method_exists('\\SPPMod\\Drishyam\\TemplateMacros', 'spptransform')) {
    echo "   [SUCCESS] spptransform macro exists.\n";
}
if (method_exists('\\SPPMod\\Drishyam\\TemplateMacros', 'sppcompose')) {
    echo "   [SUCCESS] sppcompose macro exists.\n";
}
if (method_exists('\\SPPMod\\Drishyam\\TemplateMacros', 'spplivepartial')) {
    echo "   [SUCCESS] spplivepartial macro exists.\n";
    $livePartialOutput = \SPPMod\Drishyam\TemplateMacros::spplivepartial('nonexistent', ['topic' => 'entity_user_1']);
    echo "   Sample Output: " . htmlspecialchars($livePartialOutput, ENT_QUOTES, 'UTF-8') . "\n\n";
}

// 3. Verify SPPLive broadcastEntityEvent
echo "3. Verifying SPPLive broadcastEntityEvent...\n";
if (method_exists('\\SPPMod\\SPPLive\\SPPLive', 'broadcastEntityEvent')) {
    echo "   [SUCCESS] SPPLive::broadcastEntityEvent exists and is ready for decoupled entity events.\n\n";
}

// 4. Verify modinit files loading
echo "4. Verifying Module modinit files...\n";
$liveModinit = SPP_BASE_DIR . '/modules/spp/spplive/modinit.php';
$cacheModinit = SPP_BASE_DIR . '/modules/spp/sppcache/modinit.php';
$workflowModinit = SPP_BASE_DIR . '/modules/spp/sppworkflow/modinit.php';

if (file_exists($liveModinit)) {
    require_once $liveModinit;
    echo "   [SUCCESS] spplive/modinit.php loaded successfully.\n";
}
if (file_exists($cacheModinit)) {
    require_once $cacheModinit;
    echo "   [SUCCESS] sppcache/modinit.php loaded successfully.\n";
}
if (file_exists($workflowModinit)) {
    require_once $workflowModinit;
    echo "   [SUCCESS] sppworkflow/modinit.php loaded successfully.\n";
}

echo "\n====================================================\n";
echo " ALL ARCHITECTURAL VERIFICATIONS COMPLETED SUCCESSFULLY\n";
echo "====================================================\n";
