<?php
$cookieFile = "cookie.txt";

// Login
$ch = curl_init("http://localhost/school1/lekhak/admin/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ["username" => "admin", "password" => "admin123"]);
curl_exec($ch);

// GET settings
$ch2 = curl_init("http://localhost/school1/lekhak/admin/settings");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookieFile);
$res = curl_exec($ch2);
if (strpos($res, "System Settings") !== false) echo "GET works.\n";
else echo "GET failed.\n";

// POST settings
$ch3 = curl_init("http://localhost/school1/lekhak/admin/settings");
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch3, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch3, CURLOPT_POST, true);
curl_setopt($ch3, CURLOPT_POSTFIELDS, [
    "lekhni_default_mode" => "code",
    "designer_autosave" => 60
]);
curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, true);
$res3 = curl_exec($ch3);

if (strpos($res3, "Configuration saved successfully!") !== false) {
    echo "POST works and redirected with saved=1.\n";
} else {
    echo "POST failed.\n";
}


