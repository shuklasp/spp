<?php
// Initialize framework
$_SERVER['REQUEST_URI'] = '/school1/samvaad';
$_SERVER['REQUEST_METHOD'] = 'GET';
require 'c:/projects/apache/school1/index.php';

$path = 'c:/projects/apache/school1/src/Samvaad/comp/partials/live_demo.html';
$compiled = \SPPMod\SPPView\ViewCompiler::compile($path);

echo "Compiled Path: " . $compiled . "\n";
echo "Content:\n";
echo file_get_contents($compiled);
