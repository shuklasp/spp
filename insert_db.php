<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$db->execute_query("INSERT INTO lekhak_modules (machine_name, status) VALUES ('sankhyaki', 1) ON DUPLICATE KEY UPDATE status = 1");
echo "Inserted sankhyaki into lekhak_modules DB.\n";
