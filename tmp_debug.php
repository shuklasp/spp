<?php
require 'spp/sppinit.php';

try {
    require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/DocParser.php';
    $data = \SPPMod\SPPDoc\DocParser::parseCodebase();
    $json = json_encode($data);
    echo "Memory Usage: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
    echo "SUCCESS\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
