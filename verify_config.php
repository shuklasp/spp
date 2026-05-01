<?php
require_once 'spp/sppinit.php';
$app = new \SPP\App('lekhak');
echo "Lekhak Conf Dir: " . $app->getAppConfDir() . "\n";
$file = $app->getAppConfDir() . '/settings.yml';
echo "Settings File exists: " . (file_exists($file) ? 'YES' : 'NO') . "\n";

\SPP\Scheduler::setContext('lekhak');
$config = \SPP\SPPConfig::get('app:', 'FAIL');
echo "Lekhak App Config: " . json_encode($config) . "\n";
