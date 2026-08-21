<?php
$p = 'c:/projects/apache/school1/spp/core/class.module.php';
$c = file_get_contents($p);

// Revert the previous bad edit if possible, or just fix the structure.
// I'll search for the bad block and remove it, then re-insert correctly.

$bad_start = "// --- Step 1.1: Fallback to 'default' isolated config";
$bad_pos = strpos($c, $bad_start);
if ($bad_pos !== false) {
    // Find the end of the bad block (next // --- Step 2)
    $bad_end = strpos($c, "// --- Step 2:", $bad_pos);
    if ($bad_end !== false) {
        $c = substr($c, 0, $bad_pos) . substr($c, $bad_end);
    }
}

$fallback = "\n        // --- Step 1.1: Fallback to 'default' isolated config if context is not 'default' ---\n" .
            "        if (\$appname && \$appname !== 'default') {\n" .
            "            \$defaultConf = APP_ETC_DIR . SPP_DS . 'default' . SPP_DS . 'modsconf' . SPP_DS . \$modname . SPP_DS . 'config.yml';\n" .
            "            if (file_exists(\$defaultConf)) {\n" .
            "                \$yamlData = Yaml::parseFile(\$defaultConf);\n" .
            "                \$val = \$yamlData['variables'][\$varname] ?? (\$yamlData[\$varname] ?? null);\n" .
            "                if (\$val !== null) {\n" .
            "                    \$result = (string) \$val;\n" .
            "                    self::\$configCache[\$cacheKey] = \$result;\n" .
            "                    return \$result;\n" .
            "                }\n" .
            "            }\n" .
            "        }\n";

$step1_pos = strpos($c, '// --- Step 1: Check isolated per-app YAML config');
// Search for the closing brace of 'if ($appname) {' which is at indentation of 8 spaces.
$closing_brace_pattern = "\n        }\n"; 
$closing_brace_pos = strpos($c, $closing_brace_pattern, $step1_pos);

if ($step1_pos !== false && $closing_brace_pos !== false) {
    $insert_pos = $closing_brace_pos + strlen($closing_brace_pattern);
    $new_content = substr($c, 0, $insert_pos) . $fallback . substr($c, $insert_pos);
    file_put_contents($p, $new_content);
    echo "Success";
} else {
    echo "Failed to find Step 1 or closing brace. step1=" . ($step1_pos !== false ? 'Y' : 'N') . " brace=" . ($closing_brace_pos !== false ? 'Y' : 'N');
}
