<?php
require_once('vendor/autoload.php');
require_once('spp/sppinit.php');

\SPP\Scheduler::setContext('lekhak');

echo "Installing LandingPage...\n";
\SPPMod\Lekhak\Core\LandingPage::install();

echo "Installing LandingBlock...\n";
\SPPMod\Lekhak\Core\LandingBlock::install();

echo "Done.\n";
?>
