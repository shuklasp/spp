<?php
require 'spp/spp.php';
$data = \SPPMod\SPPDoc\DocParser::parseCodebase();
$configs = [];
foreach($data as $cat => $classes) {
    foreach($classes as $key => $cls) {
        if(isset($cls['type']) && $cls['type'] === 'config') {
            $configs[] = $cat . ' => ' . $key;
        }
    }
}
print_r($configs);
