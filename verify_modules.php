<?php
require 'vendor/autoload.php';
require 'spp/core/class.app.php';
$app = \SPP\App::getApp('default');

$db = new \SPPMod\SppDb\SPPDB();
$table = \SPPMod\SppDb\SPPDB::sppTable('spp_modules');
$res = $db->execute_query("SELECT name, version FROM $table");
foreach ($res as $row) {
    echo $row['name'] . ' (v' . $row['version'] . ")\n";
}
