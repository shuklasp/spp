<?php
$masterFile = __DIR__ . '/src/ptable/data/master_elements.json';
$draftsDir = __DIR__ . '/src/ptable/data/drafts';
$masterData = json_decode(file_get_contents($masterFile), true);

$mergedCount = 0;
foreach (glob($draftsDir . '/*.json') as $file) {
    $elementName = basename($file, '.json');
    $draft = json_decode(file_get_contents($file), true);
    
    // Find symbol for name
    $symbol = null;
    foreach ($masterData as $s => $data) {
        if (strcasecmp($data['name'], $elementName) === 0) {
            $symbol = $s;
            break;
        }
    }

    if ($symbol && $draft) {
        if (isset($draft['extract_html'])) {
            $masterData[$symbol]['extract_html'] = $draft['extract_html'];
        }
        if (isset($draft['sections'])) {
            $masterData[$symbol]['sections'] = $draft['sections'];
        }
        $mergedCount++;
        echo "Merged $elementName into master database.\n";
    }
}

file_put_contents($masterFile, json_encode($masterData, JSON_PRETTY_PRINT));
echo "Successfully merged $mergedCount rewritten elements!\n";
