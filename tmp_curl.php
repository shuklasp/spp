<?php
$ch = curl_init('http://localhost:8000/api?cmd=get_codebase_structure');
// Try just fetching it, we might need to login, but let's see if we get the same 500 error!
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
echo "RESPONSE LENGTH: " . strlen($response) . "\n";
echo substr($response, 0, 500);
