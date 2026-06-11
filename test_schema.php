<?php
require_once __DIR__ . '/spp/modules/spp/sppreport/class.sppreport.php';
$report = new SPPReport();
$schema = $report->getSchema();
echo "Driver: \n";
print_r($schema);
