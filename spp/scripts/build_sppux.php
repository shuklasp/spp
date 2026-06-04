<?php
$sourceFile = __DIR__ . '/../modules/spp/sppux/js/sppux.js';
$targetFile = __DIR__ . '/../res/js/sppux.standalone.js';

$content = file_get_contents($sourceFile);

// Strip 'export ' from declarations
$content = preg_replace('/^export\s+/m', '', $content);

// Wrap in IIFE
$output = "/**\n * SPP-UX Standalone Runtime\n * Built on " . date('Y-m-d H:i:s') . "\n */\n";
$output .= "(function(global) {\n";
$output .= $content . "\n";
$output .= "
    // Expose Global SPPUX Object
    global.SPPUX = {
        BaseComponent: BaseComponent,
        html: html,
        mount: mount,
        TrustedHTML: TrustedHTML,
        Fragment: Fragment,
        SPPStore: typeof SPPStore !== 'undefined' ? SPPStore : null
    };

    // Also expose top-level for absolute novice convenience
    global.BaseComponent = BaseComponent;
    global.html = html;
    global.mount = mount;
})(typeof window !== 'undefined' ? window : this);\n";

file_put_contents($targetFile, $output);
echo "Built standalone SPPUX runtime at: {$targetFile}\n";
