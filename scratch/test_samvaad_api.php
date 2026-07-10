<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/school1/samvaad?__spa=1&__svc=enterprise.live");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-SPP-Ajax: 1", "Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['action' => 'increment']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
echo "Response:\n" . $result . "\n";
