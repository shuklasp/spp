<?php
require_once __DIR__ . '/../spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$table = $argv[1] ?? 'lek_field_alias';
$res = $db->execute_query("SELECT * FROM $table");
print_r($res);
