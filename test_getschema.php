<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/spp/etc/initrc.php';
\SPP\System::init();

require_once __DIR__ . '/spp/modules/spp/sppreport/class.sppreport.php';

$report = new \SPPReport();
$schema = $report->getSchema();
print_r($schema['users']);
