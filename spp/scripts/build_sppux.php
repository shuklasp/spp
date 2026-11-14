<?php
/**
 * SPP-UX Standalone Runtime Bundler
 * 
 * Concatenates the modular SPPUX v14 core into a single self-contained IIFE
 * distribution for zero-dependency browser runtime usage.
 */

$sourceDir = __DIR__ . '/../modules/spp/drishyam/js';
$targetFiles = [
    __DIR__ . '/../res/js/sppux.standalone.js',
    __DIR__ . '/../../dist/lekhak/spp/res/js/sppux.standalone.js'
];

$modules = [
    'core/scheduler.js',
    'core/events.js',
    'core/reactive.js',
    'core/parts.js',
    'core/template.js',
    'core/reconciler.js',
    'core/error-boundary.js',
    'spplive.js',
    'sppux.js'
];

$bundleContent = '';

foreach ($modules as $mod) {
    $filePath = $sourceDir . '/' . $mod;
    if (!file_exists($filePath)) {
        echo "Warning: Module file not found: {$filePath}\n";
        continue;
    }

    $content = file_get_contents($filePath);

    // Strip ES import statements (handles single line and multiline imports)
    $content = preg_replace('/import\s+[\s\S]*?from\s+[\'"][^\'"]+[\'"];?/m', '', $content);

    // Strip standalone export lists: export { ... };
    $content = preg_replace('/export\s*\{[\s\S]*?\};?/m', '', $content);

    // Strip export default
    $content = preg_replace('/export\s+default\s+/m', '', $content);

    // Strip export keyword preceding declarations (class, function, const, let, var, async)
    $content = preg_replace('/export\s+(?=(class|function|const|let|var|async)\b)/m', '', $content);

    $bundleContent .= "\n// ─── Module: {$mod} ───────────────────────────────────────────\n";
    $bundleContent .= trim($content) . "\n";
}

$output = "/**\n * SPP-UX Standalone Runtime (v14)\n * Built on " . date('Y-m-d H:i:s') . "\n */\n";
$output .= "(function(global) {\n";
$output .= "    'use strict';\n";
$output .= $bundleContent . "\n";
$output .= "
    // Expose Global SPPUX Object
    global.SPPUX = SPPUX;

    // Expose Top-Level Convenience APIs
    global.BaseComponent = BaseComponent;
    global.html = html;
    global.mount = mount;
    global.render = render;
    global.TrustedHTML = TrustedHTML;
    global.Fragment = Fragment;
    global.Signal = Signal;
    global.Computed = Computed;
    global.effect = effect;
    global.batch = batch;
    global.createStore = createStore;
    global.useState = useState;
    global.useComputed = useComputed;
    global.useEffect = useEffect;
    global.SPPStore = typeof SPPStore !== 'undefined' ? SPPStore : null;
})(typeof window !== 'undefined' ? window : this);\n";

foreach ($targetFiles as $target) {
    $dir = dirname($target);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($target, $output);
    echo "Built standalone SPPUX runtime at: {$target}\n";
}
