<?php
require 'spp/spp.php';
$db = new \SPPMod\SPPDB\SPPDB();
$db->exec('DROP TABLE IF EXISTS spp_showcase_items');
\App\Samvaad\Entities\ShowcaseItem::install();
echo "Table recreated successfully.\n";
