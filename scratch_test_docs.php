<?php
require_once 'c:/projects/apache/school1/spp/sppinit.php';

try {
    $parserPath = SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/DocParser.php';
    require_once $parserPath;
    
    // Catch any warnings or notices
    ob_start();
    $data = \SPPMod\SPPDoc\DocParser::parseCodebase();
    $output = ob_get_clean();
    
    if ($output) {
        echo "OUTPUT INTERCEPTED:\n" . $output . "\n";
    }
    
    echo "DATA TYPE: " . gettype($data) . "\n";
    if (is_string($data)) {
        echo "DATA PREVIEW: " . substr($data, 0, 100) . "\n";
    } elseif (is_array($data)) {
        echo "DATA KEYS: " . implode(", ", array_keys($data)) . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
