<?php
include 'spp/sppinit.php';
include 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$sql = "SELECT * FROM " . \SPPMod\SPPDB\SPPDB::sppTable('users') . " WHERE username = 'admin'";
$res = $db->execute_query($sql);
$user = $res[0] ?? null;
echo "USERNAME: " . ($user['username'] ?? 'NOT FOUND') . "\n";
print_r($user);
