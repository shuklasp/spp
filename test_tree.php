<?php
require_once 'c:/projects/apache/school1/spp/sppinit.php';
require_once 'c:/projects/apache/school1/spp/modules/spp/sppdoc/src/DocParser.php';

$results = \SPPMod\SPPDoc\DocParser::parseCodebase();

$tree = [];
foreach ($results as $cat => $items) {
    $tree[$cat] = array_keys($items);
}
print_r($tree);
