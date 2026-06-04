<?php
$portFile = 'var/shared/bridge/daemons/manual.port';
if (!file_exists($portFile)) {
    die("manual.port does not exist.\n");
}
$port = (int)trim(file_get_contents($portFile));
echo "Connecting to 127.0.0.1:{$port}...\n";

$start = microtime(true);
$fp = @fsockopen("127.0.0.1", $port, $errno, $errstr, 5);
if (!$fp) {
    echo "fsockopen failed. Errno: {$errno}, Errstr: {$errstr}\n";
    exit(1);
}
echo "Connected! Time: " . round(microtime(true) - $start, 4) . "s\n";
echo "Sending payload...\n";

$payload = json_encode(['func' => 'generate', 'args' => ['Test direct connection']]) . "\n";
fwrite($fp, $payload);

$response = '';
while (!feof($fp)) {
    $chunk = fgets($fp, 4096);
    if ($chunk === false) break;
    $response .= $chunk;
    if (str_ends_with($chunk, "\n")) break;
}
fclose($fp);

echo "Response: " . trim($response) . "\n";
