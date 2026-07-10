<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$tables = $db->query("SHOW TABLES");
foreach ($tables as $t) {
    echo implode(", ", array_values($t)) . "\n";
}
