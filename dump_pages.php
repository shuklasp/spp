<?php
require_once('spp/sppinit.php');
$db = new \SPPMod\SPPDB\SPPDB();
$rows = $db->execute_query('SELECT * FROM sppview_pages');
print_r($rows);
