<?php
require_once 'c:/projects/apache/school1/spp/sppinit.php';
require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/DocParser.php';

$data = \SPPMod\SPPDoc\DocParser::parseCodebase();
$json = json_encode($data);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Encode Error: " . json_last_error_msg() . "\n";
}

// Find any string value that starts with "<" in the array structure
function findBadStrings($array, $path = "") {
    foreach ($array as $key => $value) {
        $currentPath = $path ? "$path -> $key" : $key;
        if (is_string($value) && $value === "<") {
            echo "BAD STRING FOUND at $currentPath\n";
        } elseif (is_string($value) && str_starts_with($value, "<")) {
            echo "HTML STRING FOUND at $currentPath (Value starts with <)\n";
        } elseif (is_array($value)) {
            findBadStrings($value, $currentPath);
        }
    }
}

// But wait, the error is Cannot create property '_full_name' on string '<'
// This means the array structure is `$data[$category][$className] = '<'` !
foreach ($data as $category => $classes) {
    if (!is_array($classes)) {
        echo "CATEGORY NOT ARRAY: $category (Value: " . print_r($classes, true) . ")\n";
    } else {
        foreach ($classes as $className => $classData) {
            if (!is_array($classData)) {
                echo "CLASS DATA NOT ARRAY: $category -> $className (Value: " . print_r($classData, true) . ")\n";
            }
        }
    }
}
echo "Check finished.\n";
