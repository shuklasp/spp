<?php
require 'c:\projects\apache\school1\spp\sppinit.php';
require 'c:\projects\apache\school1\global.php';
\SPP\Scheduler::setContext('Samvaad'); // Use exact case
$file = \SPP_APP_DIR . '/src/Samvaad/etc/services.yml';
$data = yaml_parse_file($file);
echo "Parsed YAML:\n";
print_r($data);

echo "\n--- SPPAjax Test ---\n";
// Manually init SPPAjax
\SPPMod\SPPAPI\SPPAjax::init();
$svc = \SPPMod\SPPAPI\SPPAjax::findService('enterprise.live');
var_dump($svc);
