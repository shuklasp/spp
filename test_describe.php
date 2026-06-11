<?php
require_once __DIR__ . '/spp/modules/spp/sppdb/class.sppdb.php';
$db = new \SPPMod\SPPDB\SPPDB();
$tables = $db->execute_query("SHOW TABLES");
print_r($tables);
$columns = $db->execute_query("DESCRIBE users");
print_r($columns);
