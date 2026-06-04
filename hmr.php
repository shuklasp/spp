<?php
/**
 * SPP HMR SSE Server
 * Runs on a dedicated port to push events to the browser without blocking the main server.
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

$lastHash = '';
$watchDirs = [__DIR__ . '/src', __DIR__ . '/spp/admin'];

while (true) {
    $currentHash = '';
    foreach ($watchDirs as $dir) {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $currentHash .= $file->getMTime();
                }
            }
        }
    }
    
    $currentHash = md5($currentHash);

    if ($lastHash === '') {
        $lastHash = $currentHash;
    }

    if ($lastHash !== $currentHash) {
        $lastHash = $currentHash;
        echo "event: reload\n";
        echo "data: {\"action\": \"reload\"}\n\n";
        ob_flush();
        flush();
    } else {
        echo ": ping\n\n";
        ob_flush();
        flush();
    }

    sleep(1);
    
    // Break the loop if connection is closed
    if (connection_aborted()) {
        break;
    }
}
