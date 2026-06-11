<?php
$url = 'http://localhost/school1/sppadmin/api.php?action=report_api&modname=sppreport&report_action=schema';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
// Add a mock cookie for sppadmin_logged_in if needed, though my previous fix made it rely on $_SESSION.
// Actually, session is bound to cookies. I should make sure I pass a valid session or bypass it.
// To bypass it for test, I will just temporarily comment out the auth check in api.php.
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP Code: $httpCode\n\n";
echo $response;
