<?php
$excludePatterns = [
    '.git/',
    '.gemini/',
    'spp/etc/cache/',
    'node_modules/',
    'vendor/',
    'tmp/',
    'uploads/'
];

$baseDir = 'c:\projects\apache\school1';
$path = 'c:\projects\apache\school1\.gemini';

$normalizedPath = str_replace('\\', '/', $path);
$normalizedBaseDir = str_replace('\\', '/', $baseDir);

$relativePath = ltrim(str_replace($normalizedBaseDir, '', $normalizedPath), '/');

echo "Rel: " . $relativePath . "\n";

function shouldExclude($path, $excludePatterns) {
    foreach ($excludePatterns as $pattern) {
        $normalizedPattern = trim(str_replace('\\', '/', $pattern), '/');
        if (str_contains('/' . $path . '/', '/' . $normalizedPattern . '/')) {
            return true;
        }
        if (str_starts_with($path, $normalizedPattern . '/')) {
            return true;
        }
    }
    return false;
}

echo "Exclude: " . (shouldExclude($relativePath, $excludePatterns) ? "YES" : "NO") . "\n";
