<?php
$dir = __DIR__ . '/spp/commands';
$missing = [];
foreach (glob($dir . '/*.php') as $file) {
    if (basename($file) === 'Command.php') continue;
    $content = file_get_contents($file);
    if (stripos($content, 'isCLIOnly') === false) {
        $missing[] = basename($file);
    }
}
echo implode("\n", $missing);
