<?php
require_once 'sppinit.php';

use SPPMod\SPPView\ViewFormBuilder;

// Set context to 'default'
\SPP\Scheduler::setContext('default');

// Load all modules (using cache if compiled)
\SPP\Module::loadAllModules();

echo "<!DOCTYPE html><html><head><title>SPP Universe Test</title>";
// Mock some styles for the container
echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px; }
    .container { max-width: 900px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
    h1 { color: #38bdf8; margin-top: 0; }
    .spp-element { margin-bottom: 20px; }
    label { display: block; margin-bottom: 8px; font-weight: 600; color: #94a3b8; }
    .btn-glow { background: #0284c7; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; transition: 0.3s; box-shadow: 0 0 15px rgba(2, 132, 199, 0.4); }
    .btn-glow:hover { background: #0369a1; box-shadow: 0 0 20px rgba(2, 132, 199, 0.6); }
</style>";

// Include external libraries
echo "<link rel='stylesheet' href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' />";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css' />";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css' />";
echo "<link rel='stylesheet' href='modules/spp/sppext/css/sppext-premium.css' />";

echo "<script src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'></script>";
echo "<script src='https://cdn.jsdelivr.net/npm/flatpickr'></script>";
echo "<script src='https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js'></script>";
echo "<script src='https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js'></script>";

echo "<script src='modules/spp/sppux/js/sppux.js'></script>";
echo "<script src='modules/spp/sppux/js/sppux-bridge.js'></script>";
echo "<script src='modules/spp/sppux/js/sppux-ui.js'></script>";
echo "<script src='modules/spp/sppux/js/spp-loader.js'></script>";
echo "<script src='modules/spp/sppext/js/sppext.js'></script>";

echo "<script>
    window.addEventListener('DOMContentLoaded', () => {
        if (window.SPPUX && window.SPPUX.Loader) {
            SPPUX.Loader.hydrate(document.body);
        }
    });
</script>";

echo "</head><body><div class='container'>";

try {
    $form = ViewFormBuilder::fromYaml('modules/spp/sppext/test_universe.yml');
    echo "<h1>" . $form->getMatter() . "</h1>";
    echo $form->render();
} catch (\Exception $e) {
    echo "<div style='color: #ef4444; border: 1px solid #ef4444; padding: 15px; border-radius: 8px;'>";
    echo "<b>Error:</b> " . $e->getMessage();
    echo "</div>";
}

echo "</div></body></html>";
