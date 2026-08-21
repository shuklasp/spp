<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$db->execute_query("ALTER TABLE users RENAME TO spp_users");
$db->execute_query("ALTER TABLE roles RENAME TO spp_roles");
$db->execute_query("ALTER TABLE rights RENAME TO spp_rights");
$db->execute_query("ALTER TABLE lek_userroles RENAME TO spp_userroles");
$db->execute_query("ALTER TABLE lek_roleright RENAME TO spp_roleright");
$db->execute_query("ALTER TABLE login_attempts RENAME TO spp_login_attempts");
echo "Renamed tables.";
