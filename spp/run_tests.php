<?php
define('SPP_BASE_DIR', __DIR__);
define('SPP_APP_DIR', __DIR__);
require 'C:/projects/apache/school1/spp/modules/spp/parikshak/src/InteractsWithMockery.php';
require 'C:/projects/apache/school1/spp/modules/spp/parikshak/src/InteractsWithBrowser.php';
require 'C:/projects/apache/school1/spp/modules/spp/parikshak/src/SPPTestCase.php';
require 'C:/projects/apache/school1/spp/modules/spp/parikshak/src/SPPTestRunner.php';
$runner = new \SPPMod\Parikshak\SPPTestRunner();
print_r($runner->run('sppapi'));
