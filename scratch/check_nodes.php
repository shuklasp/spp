<?php
require_once 'vendor/autoload.php';
require_once 'spp/sppinit.php';

$db = new \SPPMod\SPPDB\SPPDB();
$res = $db->execute_query('SELECT * FROM lek_nodes LIMIT 5');
print_r($res);
