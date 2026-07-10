<?php
require 'vendor/autoload.php';
require 'spp/sppinit.php';
$app = \SPP\App::getApp('default');

$db = new \SPPMod\SPPDB\SPPDB();
$table = \SPPMod\SPPDB\SPPDB::sppTable('spp_modules');
$db->execute_query("CREATE TABLE IF NOT EXISTS $table (name TEXT, version TEXT)");
$res = $db->execute_query("SELECT name, version FROM $table");
if (empty($res)) {
    $db->execute_query("INSERT INTO $table (name, version) VALUES ('sppreport', '2.0.0')");
    $res = $db->execute_query("SELECT name, version FROM $table");
}
foreach ($res as $row) {
    echo $row['name'] . ' (v' . $row['version'] . ")\n";
}
