<?php
$files = glob('c:/projects/apache/school1/src/lekhak/modules/*/module.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'class ') === false && preg_match('/instance\' => new ([A-Za-z0-9_]+)\(\)/', $content, $matches)) {
        $className = $matches[1];
        
        // Find the namespace line
        if (preg_match('/namespace\s+([^;]+);/', $content, $ns_matches)) {
            $ns_line = $ns_matches[0];
            $content = str_replace($ns_line, $ns_line . "\n\nclass " . $className . " {\n", $content);
            file_put_contents($file, $content);
            echo "Restored class $className in $file\n";
        }
    }
}
