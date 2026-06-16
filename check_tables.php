<?php
require 'spp/sppinit.php';
$db = new SPPMod\SPPDB\SPPDB();
print_r($db->execute_query('SHOW TABLES LIKE "%tokens%"'));
