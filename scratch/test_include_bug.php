<?php
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['REQUEST_METHOD'] = 'GET';
require 'C:/projects/apache/school1/index.php';

$class = '\\App\\Samvaad\\comp\\live_demo';
$comp = new $class();
$rawHtml = $comp->render();
$filePath = $rawHtml;
$compiled = \SPPMod\SPPView\ViewCompiler::compile($filePath);

echo "Compiled file: $compiled\n";
echo "Content length: " . filesize($compiled) . "\n";
echo "Content:\n" . file_get_contents($compiled) . "\n";
echo "--- NOW INCLUDING ---\n";
ob_start();
extract($comp->dehydrate());
extract($comp->getComputedProperties());
include $compiled;
$html = ob_get_clean();
echo "Included Length: " . strlen($html) . "\n";
if ($html === '') {
    echo "ERROR: HTML IS EMPTY AFTER INCLUDE!\n";
} else {
    echo "Included output:\n" . $html . "\n";
}
