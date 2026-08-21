<?php
echo "Starting background Wikipedia sections fetch...\n";
$dataFile = __DIR__ . '/src/ptable/data/master_elements.json';
$masterData = json_decode(file_get_contents($dataFile), true);

$targets = ['Isotopes', 'Occurrence', 'Applications', 'Uses', 'Biological role', 'History', 'Characteristics', 'Physical properties', 'Chemical properties'];
$count = 0;

foreach ($masterData as $symbol => &$data) {
    if (empty($data['sections'])) {
        $name = $data['name'];
        echo "Fetching sections for {$name}...\n";
        
        $actionUrl = 'https://en.wikipedia.org/w/api.php?action=parse&page=' . urlencode($name) . '&prop=sections|text&format=json';
        $ch = curl_init($actionUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SPP-PeriodicTable-Bot/2.0 (my.unique.email@domain.com)');
        $actionRes = curl_exec($ch);
        curl_close($ch);

        $actionData = json_decode($actionRes, true) ?? [];
        $sectionsData = [];
        
        if (isset($actionData['parse']['sections'])) {
            $fullHtml = $actionData['parse']['text']['*'] ?? '';
            foreach ($actionData['parse']['sections'] as $sec) {
                $line = $sec['line'];
                if (in_array(trim($line), $targets)) {
                    $anchor = $sec['anchor'];
                    $pattern = '/<h[2-6][^>]*id="' . preg_quote($anchor, '/') . '"[^>]*>.*?<\/h[2-6]>(.*?)(?=<h[2-6]|$)/is';
                    if (preg_match($pattern, $fullHtml, $matches)) {
                        $sectionsData[$line] = trim($matches[1]);
                    }
                }
            }
            if (!empty($sectionsData)) {
                $data['sections'] = $sectionsData;
                echo " - Found " . count($sectionsData) . " sections.\n";
            } else {
                echo " - No target sections found in parse tree.\n";
            }
        } else {
            echo " - API Error or Rate Limit reached.\n";
        }
        
        $count++;
        // Save incrementally so if it crashes, we don't lose progress
        file_put_contents($dataFile, json_encode($masterData, JSON_PRETTY_PRINT));
        
        // Very important: respect Wikipedia's rate limits
        sleep(1);
    }
}

echo "Finished fetching missing sections.\n";
