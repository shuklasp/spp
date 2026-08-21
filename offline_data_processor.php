<?php
echo "Starting offline data processing...\n";
$appDir = __DIR__;
$dataFile = $appDir . '/src/ptable/data/master_elements.json';
$masterData = json_decode(file_get_contents($dataFile), true);

$inlineImgDir = $appDir . '/src/ptable/resources/themes/default/images/inline';
if (!is_dir($inlineImgDir)) {
    mkdir($inlineImgDir, 0777, true);
}

// Prepare element dictionary for cross-linking
$allElements = [];
foreach ($masterData as $el) {
    $allElements[] = ['symbol' => $el['symbol'], 'name' => $el['name']];
}
usort($allElements, function($a, $b) {
    return strlen($b['name']) - strlen($a['name']);
});

function processHtml($html, $allElements, $inlineImgDir) {
    if (empty($html)) return $html;

    // 1. Strip Wikipedia Edit Links and Citations COMPLETELY from HTML
    $html = preg_replace('/<span class="mw-editsection">.*?<\/span>\s*<\/span>/is', '', $html);
    $html = preg_replace('/<sup[^>]*class="reference"[^>]*>.*?<\/sup>/is', '', $html);
    $html = preg_replace('/<div class="hatnote[^"]*">.*?<\/div>/is', '', $html);

    // 2. Download and Replace Inline Images
    $html = preg_replace_callback('/<img([^>]+)src="([^"]+)"([^>]*)>/i', function($m) use ($inlineImgDir) {
        $before = $m[1];
        $src = $m[2];
        $after = $m[3];

        if (strpos($src, 'upload.wikimedia.org') !== false) {
            $url = (strpos($src, '//') === 0) ? 'https:' . $src : $src;
            $filename = md5($url) . '.' . (pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg');
            $localPath = $inlineImgDir . '/' . $filename;
            
            if (!file_exists($localPath)) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'SPP-PeriodicTable-Bot/2.0');
                $imgData = curl_exec($ch);
                if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 && $imgData) {
                    file_put_contents($localPath, $imgData);
                }
                curl_close($ch);
            }
            $newSrc = '/school1/ptable/theme-assets/default/images/inline/' . $filename;
            return "<img{$before}src=\"{$newSrc}\"{$after}>";
        }
        return $m[0];
    }, $html);

    // 3. Strip all external Wikipedia links (keeping inner text)
    $html = preg_replace('/<a href="\/wiki\/[^"]*"[^>]*>(.*?)<\/a>/i', '$1', $html);
    // Also strip https://en.wikipedia.org/wiki/ just in case
    $html = preg_replace('/<a href="https?:\/\/en\.wikipedia\.org\/wiki\/[^"]*"[^>]*>(.*?)<\/a>/i', '$1', $html);

    // 4. Cross-link to internal elements
    $baseUrl = '/school1/ptable';
    foreach ($allElements as $el) {
        $name = $el['name'];
        $symbol = $el['symbol'];
        $link = "<a href=\"{$baseUrl}/element/{$symbol}\" class=\"wiki-link\" title=\"{$name} ({$symbol})\">\$1</a>";
        $pattern = '/(?![^<]*>)\b(' . preg_quote($name, '/') . ')\b/i';
        $html = preg_replace($pattern, $link, $html, 3);
    }

    return $html;
}

foreach ($masterData as $symbol => &$data) {
    echo "Processing {$data['name']}...\n";
    $data['extract_html'] = processHtml($data['extract_html'], $allElements, $inlineImgDir);
    
    if (!empty($data['sections'])) {
        foreach ($data['sections'] as $key => $html) {
            $data['sections'][$key] = processHtml($html, $allElements, $inlineImgDir);
        }
    }
}

file_put_contents($dataFile, json_encode($masterData, JSON_PRETTY_PRINT));
echo "Finished processing all offline data. JSON updated!\n";
