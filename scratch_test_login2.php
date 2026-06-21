<?php
require 'vendor/autoload.php';
require 'spp/sppinit.php';

try {
    $res = \SPPMod\SPPAuth\SPPUser::verifyUserPassword('admin', 'admin');
    var_dump("verifyUserPassword('admin', 'admin') returned:", $res);
} catch (\Throwable $e) {
    var_dump("Exception:", $e->getMessage());
}

$db = new \SPPMod\SPPDB\SPPDB();
try {
    $rows = $db->execute_query("SELECT * FROM spp_users WHERE username = 'admin'");
    var_dump("DB Users:", $rows);
} catch (\Throwable $e) {
    var_dump("DB Exception:", $e->getMessage());
}
