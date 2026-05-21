<?php
$cookieFile = "cookie.txt";
$ch = curl_init("http://localhost/school1/lekhak/admin/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ["username" => "admin", "password" => "admin123"]);
curl_exec($ch);

$ch2 = curl_init("http://localhost/school1/lekhak/admin/content");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookieFile);
echo curl_exec($ch2);

