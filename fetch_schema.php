<?php
$url = 'http://localhost/school1/spp/modules/spp/sppreport/api.php?report_action=schema';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo "RESPONSE:\n";
print_r($res);
