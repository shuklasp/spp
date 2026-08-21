<?php
$dir = __DIR__ . '/spp/commands';
$files = glob($dir . '/*.php');
$needsFixing = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    // Looking for raw array accesses like $args[0], $args[1], $args['name'], etc.
    // Exclude $this->getArgument($args, x) which is correct, but wait, $args inside that is just a variable.
    // We are looking for $args[
    if (preg_match_all('/\$args\[[^\]]+\]/', $content, $matches)) {
        $unique = array_unique($matches[0]);
        // Filter out $args[0] if it's in a comment
        $lines = explode("\n", $content);
        $validMatches = [];
        foreach ($lines as $line) {
            if (strpos(trim($line), '//') === 0 || strpos(trim($line), '*') === 0) continue;
            if (preg_match('/\$args\[[^\]]+\]/', $line, $m)) {
                $validMatches[] = trim($line);
            }
        }
        if (!empty($validMatches)) {
            $needsFixing[basename($file)] = $validMatches;
        }
    }
}
echo json_encode($needsFixing, JSON_PRETTY_PRINT);
