<?php
$dir = __DIR__ . '/spp/commands';
$files = glob($dir . '/*.php');
$count = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Pattern to match $this->json(...)->notify(...);
    $pattern = '/\$this->json\((.*?)\)->notify\((.*?)\);/s';
    
    $newContent = preg_replace_callback($pattern, function($matches) {
        $jsonArgs = $matches[1];
        $notifyArgs = $matches[2];
        return '$this->notify(' . $notifyArgs . ");\n        \$this->json(" . $jsonArgs . ');';
    }, $content, -1, $replacements);
    
    if ($replacements > 0) {
        file_put_contents($file, $newContent);
        echo "Patched {$replacements} occurrences in " . basename($file) . "\n";
        $count += $replacements;
    }
}

echo "\nTotal occurrences patched: {$count}\n";
