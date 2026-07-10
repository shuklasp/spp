<?php
require 'spp/sppinit.php';
\SPP\Scheduler::setContext('samvaad');
App\Samvaad\Entities\ShowcaseItem::install();
echo "Install OK\n";
