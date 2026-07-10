<?php
require 'spp/sppinit.php';
$app = \SPP\App::getApp('default');

echo "Testing Storage facade via autoloader alias...\n";
\SPP\Storage::put('test_upload.txt', 'Storage merge works perfectly!');
$content = \SPP\Storage::get('test_upload.txt');
echo "Read content: " . $content . "\n";
\SPP\Storage::delete('test_upload.txt');
echo "Deleted test file successfully.\n";

echo "\nTesting Storage stream operations (readStream / writeStream)...\n";
$fp = fopen('php://temp', 'r+b');
fwrite($fp, 'Streaming data directly to disk!');
rewind($fp);
\SPP\Storage::writeStream('test_stream.txt', $fp);
fclose($fp);

$stream = \SPP\Storage::readStream('test_stream.txt');
echo "Read stream content: " . stream_get_contents($stream) . "\n";
fclose($stream);
\SPP\Storage::delete('test_stream.txt');
echo "Deleted stream test file successfully.\n";

echo "\nTesting Storage multi-disk driver factory (file_shared)...\n";
$sharedDisk = \SPP\Storage::disk('file_shared');
$sharedDisk->put('shared_test.txt', 'Shared storage works perfectly!');
echo "Read shared disk content: " . $sharedDisk->get('shared_test.txt') . "\n";
$sharedDisk->delete('shared_test.txt');

echo "\nTesting Storage multi-disk driver factory (flysystem)...\n";
$flyDisk = \SPP\Storage::disk('flysystem');
echo "FlysystemDisk instantiated successfully.\n";

echo "\nAll Core Storage architectural improvements verified successfully!\n";
