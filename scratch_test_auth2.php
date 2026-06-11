<?php
$data = ['username' => 'admin', 'password' => 'admin'];
$ch = curl_init('http://localhost/school1/api/v1/auth/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);
echo "Auth Token Response:\n" . $response . "\n";
