<?php
// Initialize framework
$_SERVER['REQUEST_URI'] = '/school1/samvaad';
$_SERVER['REQUEST_METHOD'] = 'GET';
require 'c:/projects/apache/school1/index.php';

$html = \SPPMod\SPPView\LiveComponent::renderComponent('\\App\\Samvaad\\comp\\live_demo');
echo "HTML Result:\n" . $html . "\n";
