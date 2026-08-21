<?php
echo "Starting data restoration for elements 35-118...\n";
$dataFile = __DIR__ . '/src/ptable/data/master_elements.json';
$masterData = json_decode(file_get_contents($dataFile), true);

$targets = ['Isotopes', 'Occurrence', 'Applications', 'Uses', 'Biological role', 'History', 'Characteristics', 'Physical properties', 'Chemical properties'];
$count = 0;

foreach ($masterData as $symbol => &$data) {
    if (isset($data['atomic']) && $data['atomic'] >= 35) {
        $name = $data['name'];
        echo "Fetching raw data for {$name}...\n";
        
        // Fetch full parse (including extract and sections)
        $actionUrl = 'https://en.wikipedia.org/w/api.php?action=parse&page=' . urlencode($name) . '&prop=sections|text&format=json';
        $ch = curl_init($actionUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SPP-PeriodicTable-Bot/2.0 (admin@example.com)');
        $actionRes = curl_exec($ch);
        curl_close($ch);

        $actionData = json_decode($actionRes, true) ?? [];
        $sectionsData = [];
        
        if (isset($actionData['parse']['text']['*'])) {
            $fullHtml = $actionData['parse']['text']['*'];
            
            // Extract the main intro (extract_html)
            // Typically everything before the first <h2
            $introHtml = '';
            if (preg_match('/^(.*?)(?=<h2)/is', $fullHtml, $m)) {
                $introHtml = $m[1];
            } else {
                $introHtml = $fullHtml;
            }
            $data['extract_html'] = $introHtml;

            // Extract sections
            if (isset($actionData['parse']['sections'])) {
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
            }
            $data['sections'] = $sectionsData;
            echo " - Restored intro and " . count($sectionsData) . " sections.\n";
            $count++;
        } else {
            echo " - API Error or Rate Limit reached.\n";
            break; // Stop if rate limited
        }
        
        // Save incrementally
        file_put_contents($dataFile, json_encode($masterData, JSON_PRETTY_PRINT));
        sleep(1); // Crucial to respect API limits
    }
}

echo "Finished restoring {$count} elements.\n";
