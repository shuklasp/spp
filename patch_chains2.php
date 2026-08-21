<?php
$files = glob(__DIR__ . '/spp/commands/*.php');
$count = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Pattern to match $this->json(ANYTHING_ON_SAME_LINE)->notify(ANYTHING_ON_SAME_LINE);
    // [^\r\n] matches any character except newline.
    // This prevents swallowing multiple lines.
    $pattern = '/\$this->json\((.*?)\)->notify\((.*?)\);/m';
    
    // Actually, (.*?) with /m will STILL match across newlines if there is no /s, wait, in PHP . doesn't match newline without /s.
    // So /m just makes ^ and $ match line boundaries.
    // So /.*?/ WITHOUT /s will NOT cross newlines! This is safe!
    $pattern = '/\$this->json\((.*?)\)->notify\((.*?)\);/';
    
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

echo "\nTotal occurrences patched safely: {$count}\n";
