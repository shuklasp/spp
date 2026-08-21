<?php
$p = 'c:/projects/apache/school1/spp/core/class.module.php';
$c = file_get_contents($p);
$old = '/return \$result;\s*\}\s*\/\/ --- Step 2: Check canonical per-app YAML config/';
$replacement = "return \$result;\n                }\n            }\n        }\n\n        // --- Step 1.1: Fallback to 'default' isolated config if context is not 'default' ---\n" .
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
            "        }\n\n        // --- Step 2: Check canonical per-app YAML config";

$new = preg_replace($old, $replacement, $c);
if ($new !== null && $new !== $c) {
    file_put_contents($p, $new);
    echo "Success";
} else {
    echo "Failed to match or no change. new=" . ($new === null ? 'NULL' : 'Same');
}
