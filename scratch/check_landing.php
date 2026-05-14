<?php
require_once __DIR__ . '/../spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$res = $db->execute_query("SELECT * FROM lek_nodes WHERE bundle='landing_page'");
print_r($res);
