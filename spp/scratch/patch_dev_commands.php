<?php
foreach (glob(dirname(__DIR__) . '/commands/*.php') as $file) {
    $content = file_get_contents($file);
    $content = str_replace('$la->', '$this->', $content);
    file_put_contents($file, $content);
    echo "Patched " . basename($file) . "\n";
}
