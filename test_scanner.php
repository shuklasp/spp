<?php
require 'spp/sppinit.php';
$scanner = new \SPPMod\SPPMigrate\Scanner\ProjectScanner();
$start = microtime(true);
$hashes = $scanner->scan(SPP_APP_DIR);
$end = microtime(true);
echo "Scanned " . count($hashes) . " files in " . ($end - $start) . " seconds.\n";
