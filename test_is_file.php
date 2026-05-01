<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dir = dirname(__DIR__); // Assuming this is placed in school1/spp/scripts or school1/
$path1 = $dir . '/resources/spp_docs/views/node.blade.php';
// Let's also check SPP_APP_DIR directly
$path2 = '/mnt/c/projects/apache/school1/resources/spp_docs/views/node.blade.php';

echo "Path 1 (dirname): $path1\n";
echo "file_exists: " . var_export(file_exists($path1), true) . "\n";
echo "is_file: " . var_export(is_file($path1), true) . "\n<br>";

echo "Path 2 (hardcoded WSL): $path2\n";
echo "file_exists: " . var_export(file_exists($path2), true) . "\n";
echo "is_file: " . var_export(is_file($path2), true) . "\n<br>";

echo "OS: " . PHP_OS . "\n<br>";
echo "User: " . get_current_user() . "\n<br>";
