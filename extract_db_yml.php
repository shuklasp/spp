<?php
require 'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$modulesDir = 'c:/projects/apache/school1/spp/modules/spp';
$modules = ['sppaudit', 'sppauth', 'sppconfig', 'sppdb', 'spplogger', 'sppview'];

foreach ($modules as $mod) {
    $ymlPath = $modulesDir . '/' . $mod . '/module.yml';
    $dbPath = $modulesDir . '/' . $mod . '/db.yml';
    
    if (file_exists($ymlPath)) {
        $data = Yaml::parseFile($ymlPath);
        if (isset($data['module']['installation'])) {
            $installation = $data['module']['installation'];
            
            // Write to db.yml
            file_put_contents($dbPath, Yaml::dump($installation, 6, 4));
            
            // Remove from module.yml
            unset($data['module']['installation']);
            file_put_contents($ymlPath, Yaml::dump($data, 6, 4));
            
            echo "Extracted $mod to db.yml\n";
        }
    }
}
echo "Done.\n";
