<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$res = $db->execute_query('SELECT COUNT(*) as cnt FROM spp_users');
echo "Count: " . $res[0]['cnt'] . "\n";
