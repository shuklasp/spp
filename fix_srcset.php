<?php
$dataFile = __DIR__ . '/src/ptable/data/master_elements.json';
$masterData = json_decode(file_get_contents($dataFile), true);

$count = 0;
foreach ($masterData as &$data) {
    if (!empty($data['extract_html'])) {
        $data['extract_html'] = preg_replace('/srcset="[^"]*"/i', '', $data['extract_html'], -1, $c);
        $count += $c;
    }
    if (!empty($data['sections'])) {
        foreach ($data['sections'] as $key => &$html) {
            $html = preg_replace('/srcset="[^"]*"/i', '', $html, -1, $c);
            $count += $c;
        }
    }
}
file_put_contents($dataFile, json_encode($masterData, JSON_PRETTY_PRINT));
echo "Removed $count srcset attributes.\n";
