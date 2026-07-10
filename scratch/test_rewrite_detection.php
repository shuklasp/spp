<?php
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$phpSelf = $_SERVER['PHP_SELF'] ?? '';

echo "REQUEST_URI: $reqUri\n";
echo "SCRIPT_NAME: $scriptName\n";
echo "PHP_SELF: $phpSelf\n";

if (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) {
    echo "Apache mod_rewrite is loaded natively.\n";
}

$hasRewriting = false;
if (strpos($reqUri, 'index.php') === false && strpos($scriptName, 'index.php') !== false) {
    echo "Rewriting detected via REQUEST_URI vs SCRIPT_NAME.\n";
    $hasRewriting = true;
} else if (isset($_SERVER['HTTP_X_REWRITE_URL']) || isset($_SERVER['IIS_UrlRewriteModule'])) {
    echo "Rewriting detected via IIS headers.\n";
    $hasRewriting = true;
} else if (isset($_ENV['SPP_CLEAN_URLS']) && $_ENV['SPP_CLEAN_URLS']) {
    echo "Rewriting forced via Env.\n";
    $hasRewriting = true;
}

echo "Has Rewriting: " . ($hasRewriting ? 'YES' : 'NO') . "\n";
