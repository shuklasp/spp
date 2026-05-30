<?php
$ch = curl_init('http://localhost/school1/lekhak/api/sppmigrate/diff');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['hashes' => ['test' => '123']]));
$res = curl_exec($ch);
if (curl_errno($ch)) {
    echo "Curl error: " . curl_error($ch) . "\n";
}
echo "Response:\n";
var_dump($res);
