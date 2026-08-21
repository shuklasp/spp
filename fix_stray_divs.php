<?php
$dataFile = 'c:\projects\apache\school1\src\ptable\data\master_elements.json';
$masterData = json_decode(file_get_contents($dataFile), true);

$count = 0;
foreach ($masterData as $symbol => &$data) {
    if (isset($data['extract_html'])) {
        $data['extract_html'] = preg_replace('/^(\s*<\/(?:div|span|p|a|ul|li|table|tbody|tr|td|th|dl|dt|dd|section|article)>\s*)+/i', '', $data['extract_html']);
    }
    if (isset($data['sections'])) {
        foreach ($data['sections'] as $k => $v) {
            $data['sections'][$k] = preg_replace('/^(\s*<\/(?:div|span|p|a|ul|li|table|tbody|tr|td|th|dl|dt|dd|section|article)>\s*)+/i', '', $v);
        }
    }
    $count++;
}
file_put_contents($dataFile, json_encode($masterData, JSON_PRETTY_PRINT));
echo "Fixed stray divs in $count elements.\n";
