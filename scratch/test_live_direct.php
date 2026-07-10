<?php
require_once __DIR__ . '/../spp/sppinit.php';
require_once __DIR__ . '/../global.php';

use SPPMod\SPPView\LiveComponent;

try {
    echo "Rendering Live Component...\n";
    $html = LiveComponent::renderComponent('\\App\\Samvaad\\comp\\live_demo');
    echo "Success!\n";
    echo substr($html, 0, 500) . "\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
