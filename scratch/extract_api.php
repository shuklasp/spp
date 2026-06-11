<?php
$apiFile = 'c:/projects/apache/school1/spp/admin/api.php';
$content = file_get_contents($apiFile);

// Find the start of the massive try block
$startStr = "try {";
$startPos = strpos($content, $startStr);

// We want to extract blocks like:
// if ($action === 'list_revisions') {
//     ...
// }
// using regex
preg_match_all('/if\s*\(\$action\s*===\s*\'([a-zA-Z0-9_]+)\'(?:\s*\|\|\s*\$action\s*===\s*\'[a-zA-Z0-9_]+\')*\)\s*\{/s', $content, $matches, PREG_OFFSET_CAPTURE);

$legacyCode = "<?php\n/**\n * Auto-extracted legacy services\n */\n\n";

foreach ($matches[1] as $index => $match) {
    $actionName = $match[0];
    $pos = $matches[0][$index][1];
    
    // Find matching closing brace
    $braceCount = 0;
    $endPos = $pos;
    $started = false;
    for ($i = $pos; $i < strlen($content); $i++) {
        if ($content[$i] === '{') {
            $braceCount++;
            $started = true;
        } elseif ($content[$i] === '}') {
            $braceCount--;
        }
        
        if ($started && $braceCount === 0) {
            $endPos = $i;
            break;
        }
    }
    
    $block = substr($content, $pos, $endPos - $pos + 1);
    
    // Transform block into function
    $funcName = "live_" . $actionName;
    
    // Quick regex to convert sendResponse(true, ['data' => X], "msg") to $la->setData(['data' => X])->notify("msg", "success")
    $body = preg_replace_callback('/sendResponse\((true|false)\s*,\s*(\[.*?\])\s*(?:,\s*"(.*?)")?\);/s', function($m) {
        $isSuccess = $m[1] === 'true';
        $data = $m[2];
        $msg = isset($m[3]) ? $m[3] : '';
        
        $out = "return \$la";
        if (!$isSuccess) {
            $out .= "->setStatus('error')";
        }
        if ($data !== '[]') {
            $out .= "->setData($data)";
        }
        if ($msg) {
            $out .= "->notify(\"$msg\", \"" . ($isSuccess ? 'success' : 'error') . "\")";
        }
        $out .= ";";
        return $out;
    }, $block);
    
    // Replace sendResponse(false, [], "msg") variants
    $body = preg_replace('/sendResponse\((true|false)\s*,\s*\[\]\s*,\s*"(.*?)"\);/s', 'return $la->setStatus(\'$1\' === \'true\' ? \'success\' : \'error\')->notify("$2");', $body);
    
    // Replace $_POST and $_GET with $params
    $body = str_replace('$_POST', '$params', $body);
    $body = str_replace('$_GET', '$params', $body);
    $body = str_replace('$_REQUEST', '$params', $body);
    
    $legacyCode .= "if (!function_exists('$funcName')) {\n";
    $legacyCode .= "    function $funcName(\$la, \$params) {\n";
    $legacyCode .= "        \$appContext = \$params['appname'] ?? 'default';\n";
    // remove the "if ($action === '...')" wrapping
    $bodyLines = explode("\n", $body);
    array_shift($bodyLines); // remove if (...) {
    array_pop($bodyLines); // remove }
    $legacyCode .= implode("\n", $bodyLines);
    $legacyCode .= "\n    }\n}\n\n";
}

file_put_contents('c:/projects/apache/school1/spp/admin/services/Legacy.php', $legacyCode);
echo "Extracted " . count($matches[0]) . " actions into Legacy.php\n";
