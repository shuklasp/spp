<?php
require 'c:/projects/apache/school1/spp/spp.php';
$db = new \SPPMod\SPPDB\SPPDB();
echo "--- SPPGROUPS ---\n";
print_r($db->execute_query("DESCRIBE sppgroups"));
echo "--- SPPGROUPMEMBERS ---\n";
print_r($db->execute_query("DESCRIBE sppgroupmembers"));
