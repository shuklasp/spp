<?php
require 'spp/spp.php';
$db = new \SPPMod\SPPDB\SPPDB();
print_r($db->execute_query('DESCRIBE ' . \SPPMod\SPPDB\SPPDB::sppTable('loginrec')));
