<?php
$dir = __DIR__ . '/spp/commands';
$files = glob($dir . '/*Command.php');

$partialsBaseDir = __DIR__ . '/spp/admin/partials/generated';
if (!is_dir($partialsBaseDir)) {
    mkdir($partialsBaseDir, 0777, true);
}

foreach ($files as $file) {
    $basename = basename($file);
    if (!str_starts_with($basename, 'Admin') && !str_starts_with($basename, 'Dev')) {
        continue;
    }

    $content = file_get_contents($file);
    $tokens = token_get_all($content);

    $newContent = '';
    $i = 0;
    $count = count($tokens);

    $partialIndex = 1;

    while ($i < $count) {
        $token = $tokens[$i];

        if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === '$html') {
            // Found $html
            $j = $i + 1;
            // skip whitespace
            while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            
            if ($j < $count && ((is_string($tokens[$j]) && $tokens[$j] === '=') || (is_array($tokens[$j]) && $tokens[$j][0] === T_CONCAT_EQUAL))) {
                $isConcat = (is_array($tokens[$j]) && $tokens[$j][0] === T_CONCAT_EQUAL);
                // Found $html = or $html .=
                $k = $j + 1;
                while ($k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k++;

                // If the next token is a double quote string or heredoc, we might want to extract it
                // To be safe, we will capture everything up to the semicolon
                $exprTokens = [];
                $semicolonFound = false;
                $m = $k;
                $containsHtmlTags = false;

                while ($m < $count) {
                    $t = $tokens[$m];
                    $exprTokens[] = $t;
                    
                    if (is_array($t) && in_array($t[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML])) {
                        if (strpos($t[1], '<div') !== false || strpos($t[1], '<table') !== false || strpos($t[1], '<h3') !== false || strpos($t[1], '<p>') !== false) {
                            $containsHtmlTags = true;
                        }
                    }

                    if (is_string($t) && $t === ';') {
                        $semicolonFound = true;
                        break;
                    }
                    $m++;
                }

                if ($semicolonFound && $containsHtmlTags) {
                    // We found a block of HTML assignment!
                    // Let's extract it.
                    $exprString = '';
                    foreach ($exprTokens as $et) {
                        $exprString .= is_array($et) ? $et[1] : $et;
                    }
                    // Remove trailing semicolon for the extraction
                    $exprString = rtrim($exprString, ';');
                    
                    $partialName = strtolower(str_replace('Command.php', '', $basename)) . '_' . $partialIndex . '.php';
                    $partialPath = $partialsBaseDir . '/' . $partialName;
                    
                    // The partial file content
                    $partialContent = "<?php\n// Extracted from {$basename}\n";
                    if ($isConcat) {
                        $partialContent .= "echo " . $exprString . ";\n";
                    } else {
                        $partialContent .= "echo " . $exprString . ";\n";
                    }
                    
                    file_put_contents($partialPath, $partialContent);

                    // Replace the assignment in the original file
                    if ($isConcat) {
                        $newContent .= "ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/{$partialName}'; \$html .= ob_get_clean();";
                    } else {
                        $newContent .= "ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/{$partialName}'; \$html = ob_get_clean();";
                    }
                    
                    $partialIndex++;
                    $i = $m + 1;
                    continue;
                }
            }
        }

        $newContent .= is_array($token) ? $token[1] : $token;
        $i++;
    }

    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Refactored {$basename} (Extracted " . ($partialIndex - 1) . " partials)\n";
    }
}
echo "Extraction complete.\n";
