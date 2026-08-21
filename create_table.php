<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$db->execute_query("CREATE TABLE spp_login_attempts (username varchar(100), ip_address varchar(100), attempts int, last_attempt datetime)");
echo "Created spp_login_attempts.";
