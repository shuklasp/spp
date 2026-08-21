<?php
$data = json_decode(file_get_contents('c:\projects\apache\school1\src\ptable\data\master_elements.json'), true);
echo "EXTRACT HTML:\n";
var_dump($data['U']['extract_html']);
echo "\nSECTIONS:\n";
foreach($data['U']['sections'] as $k => $v) {
    echo "[$k]: " . substr($v, 0, 100) . "...\n";
}
