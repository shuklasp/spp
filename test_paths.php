<?php
require_once 'c:/projects/apache/school1/spp/sppinit.php';
require_once 'c:/projects/apache/school1/spp/modules/spp/sppdoc/src/DocParser.php';

$results = \SPPMod\SPPDoc\DocParser::parseCodebase();

$configs = [];
foreach ($results as $cat => $items) {
    foreach ($items as $k => $v) {
        if (isset($v['type']) && $v['type'] === 'config') {
            $configs[$k] = $v['file'];
        }
    }
}
print_r($configs);
