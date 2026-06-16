<?php
require 'sppinit.php';
$db = new SPPMod\SPPDB\SPPDB();
print_r($db->execute_query('SELECT * FROM lek_personal_access_tokens'));
