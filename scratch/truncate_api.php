<?php
$file = 'c:/projects/apache/school1/spp/admin/api.php';
$lines = file($file);

$newLines = [];
$inLegacyBlock = false;

// We want to keep lines 0 to 391 (up to 'if ($action === \'list_revisions\') {')
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], "if (\$action === 'list_revisions') {") !== false) {
        $inLegacyBlock = true;
        // Insert our hook
        $newLines[] = "    require_once __DIR__ . '/services/General.php';\n";
        $newLines[] = "    \\SPPMod\\SPPAjax\\SPPAjax::resolveAndExecute(\$action, \$_REQUEST);\n";
    }
    
    if (!$inLegacyBlock) {
        $newLines[] = $lines[$i];
    }
    
    // Resume copying after get_di_bindings block
    if ($inLegacyBlock && strpos($lines[$i], "sendResponse(false, [], \"DI Error: \" . \$e->getMessage());") !== false) {
        // Skip the next 3 lines which are } catch and }
        $i += 3;
        $inLegacyBlock = false;
    }
}

file_put_contents($file, implode("", $newLines));
echo "Done";
