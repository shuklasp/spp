<?php
$files = glob('c:/projects/apache/school1/src/lekhak/modules/*/module.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    preg_match_all('/public function ([a-zA-Z0-9_]+)\s*\(/', $content, $matches);
    $counts = array_count_values($matches[1]);
    $changed = false;
    foreach ($counts as $func => $count) {
        if ($count > 1) {
            echo "Duplicate $func in $file\n";
            $pattern = '/\s*\/\*\*.*?\*\/\s*public function ' . $func . '\s*\([^\)]*\)\s*\{\s*\/\/[^\n]*\s*\}/s';
            $content = preg_replace($pattern, '', $content, 1);
            $changed = true;
        }
    }
    if ($changed) {
        file_put_contents($file, $content);
    }
}
