<?php
require_once('vendor/autoload.php');
require_once('spp/sppinit.php');

\SPP\Scheduler::setContext('lekhak');

echo "Installing ContentType...\n";
try {
    \SPPMod\Lekhak\Core\ContentType::install();
    echo "ContentType installed.\n";
} catch (\Exception $e) {
    echo "Error installing ContentType: " . $e->getMessage() . "\n";
}

echo "Done.\n";
?>
