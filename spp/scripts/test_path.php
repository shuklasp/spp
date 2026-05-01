<?php
$path = '/mnt/c/projects/apache/school1/resources/spp_docs/views/node.blade.php';
echo "Testing: $path\n";
echo "file_exists: " . var_export(file_exists($path), true) . "\n";
echo "is_file: " . var_export(is_file($path), true) . "\n";
echo "is_readable: " . var_export(is_readable($path), true) . "\n";

// Also test what SPP_APP_DIR resolves to
echo "\nSPP_APP_DIR equivalent via dirname: " . dirname(__DIR__) . "\n";
