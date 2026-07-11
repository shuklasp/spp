<?php

$dir = __DIR__ . '/spp/core';
$files = glob($dir . '/class.*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (preg_match('/class\s+([a-zA-Z0-9_]+)/i', $content, $matches)) {
        $className = $matches[1];
        $newName = $dir . '/' . $className . '.php';
        
        if ($file !== $newName) {
            echo "Renaming " . basename($file) . " -> " . basename($newName) . "\n";
            rename($file, $newName);
        }
    }
}
