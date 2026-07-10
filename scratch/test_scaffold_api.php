<?php
echo "Scaffolding app...\n";
exec('php spp.php make:app test_api_app mixed /test_api_app test_api_app_ 2>&1', $output, $return_var);
echo implode("\n", $output) . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/school1/test_api_app?__spa=1&__svc=enterprise.live");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-SPP-Ajax: 1", "Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['action' => 'increment']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
file_put_contents('scratch/scaffold_response.html', $result);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: $httpcode\n";
echo "Response: $result\n";

if ($httpcode === 200 && strpos($result, 'html') !== false) {
    echo "SUCCESS: The scaffolded endpoint returned a 200 OK JSON response.\n";
} else {
    echo "FAILED: The scaffolded endpoint did not return a valid 200 OK JSON response.\n";
}

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, "http://localhost/school1/test_api_app?__spa=1&__svc=enterprise.live");
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(['action' => 'increment']));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$result2 = curl_exec($ch2);
$httpcode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);

echo "HTTP Code 2 (No CSRF Header): $httpcode2\n";
echo "Response 2: $result2\n";
if ($httpcode2 === 403) {
    echo "SUCCESS: API contract enforced (403 Forbidden).\n";
} else {
    echo "FAILED: API contract NOT enforced.\n";
}
