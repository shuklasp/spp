<?php
require_once __DIR__ . '/../spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$table = $argv[1] ?? 'content_types';
$res = $db->execute_query("DESCRIBE $table");
print_r($res);
