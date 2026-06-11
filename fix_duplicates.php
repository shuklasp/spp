<?php
$dir = __DIR__ . '/src/lekhak/modules';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$count = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() === 'module.php') {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        
        // Find how many times the method signature appears
        $signature = '    public function hook_entity_view_alter(&$build, $context = []) {';
        $occurrences = substr_count($content, $signature);
        
        if ($occurrences > 1) {
            // Find the method body block regex: matches the signature and the body until the matching closing brace (simple version for this exact generated code)
            // The generated method is exactly 8 lines long:
            $methodBlock = <<<PHP
    public function hook_entity_view_alter(&\$build, \$context = []) {
        // Generic entity display modifier
        if (isset(\$build['#suffix'])) {
            \$build['#suffix'] .= '<!-- Processed by .*/'; // we will use regex
PHP;
            
            // Actually, we can just use preg_replace with a limit to remove all but the first occurrence
            // Since the method is identical, we can split by the signature and reconstruct.
            $parts = explode($signature, $content);
            $newContent = $parts[0] . $signature . $parts[1]; // keep first one
            
            // For the rest, we need to remove the method body.
            // The method body is:
            /*
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by ... -->';
        } else {
            $build['#suffix'] = '<!-- Processed by ... -->';
        }
    }
            */
            // Since it's exactly the same, let's just do a regex replace!
            $pattern = '/([ \t]*public function hook_entity_view_alter\(&\$build, \$context = \[\]\) \{.*?\n[ \t]*\}(?:\n|\r\n)+)/s';
            if (preg_match_all($pattern, $content, $matches)) {
                if (count($matches[0]) > 1) {
                    $first = $matches[0][0];
                    // remove ALL
                    $cleaned = preg_replace($pattern, '', $content);
                    // put the first one back where the first match was
                    // find position of first match
                    $pos = strpos($content, $first);
                    $newContent = substr_replace($cleaned, $first, $pos, 0);
                    
                    file_put_contents($path, $newContent);
                    echo "Fixed: $path\n";
                    $count++;
                }
            }
        }
    }
}
echo "Fixed $count files.\n";
