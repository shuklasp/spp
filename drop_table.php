<?php require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$db->execute_query('DROP TABLE lek_sankhyaki_log');
echo 'dropped';
