<?php
define('SPP_APP_DIR', __DIR__);
require_once __DIR__ . '/spp/autoload.php';

$db = new \SPPMod\SPPDB\SPPDB();
$tbl = \SPPMod\SPPDB\SPPDB::sppTable('landing_blocks');
$rows = $db->execute_query("SELECT * FROM {$tbl}");
echo json_encode($rows, JSON_PRETTY_PRINT);
