<?php
$ptable = json_decode(file_get_contents('ptable_data.json'), true)['elements'];
$masterPath = 'src/ptable/data/master_elements.json';
$master = json_decode(file_get_contents($masterPath), true);

$ptableMap = [];
foreach ($ptable as $el) {
    $ptableMap[$el['symbol']] = $el;
}

foreach ($master as $sym => &$data) {
    if (empty($data['local_image']) && isset($ptableMap[$sym]['image']['url'])) {
        $data['local_image'] = $ptableMap[$sym]['image']['url'];
    }
    
    if (empty($data['extract_html']) || strpos($data['extract_html'], 'Information not available') !== false) {
        if (!empty($ptableMap[$sym]['summary'])) {
            $data['extract_html'] = '<p>' . htmlspecialchars($ptableMap[$sym]['summary']) . '</p>';
        }
    }
}

file_put_contents($masterPath, json_encode($master, JSON_PRETTY_PRINT));
echo "Patched master_elements.json with image URLs!\n";
