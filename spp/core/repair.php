<?php
$path = 'c:\projects\apache\school1\spp\core\class.module.php';
$content = file_get_contents($path);

// Mangled block 1
$mangled1 = "        if (!defined('SPP_DEBUG') || !SPP_DEBUG) {
             = \\SPP\\Core\\ModuleCompiler::getCachePath();
            if (file_exists()) {
                 = require ;
                if (is_array()) {
                    foreach ( as  => ) {
                        self::initFromCache(, );
                    }
                    self:: = true;
                    return;
                }
            }
        }";

$fixed1 = "        if (!defined('SPP_DEBUG') || !SPP_DEBUG) {
            \$cacheFile = \\SPP\\Core\\ModuleCompiler::getCachePath(\$appname);
            if (file_exists(\$cacheFile)) {
                \$compiled = require \$cacheFile;
                if (is_array(\$compiled)) {
                    foreach (\$compiled as \$name => \$data) {
                        self::initFromCache(\$name, \$data);
                    }
                    self::\$allModulesLoaded = true;
                    return;
                }
            }
        }";

// Mangled block 2
$mangled2 = "    private static function initFromCache(string , array ): void";
$fixed2 = "    private static function initFromCache(string \$name, array \$data): void";

$content = str_replace($mangled1, $fixed1, $content);
$content = str_replace($mangled2, $fixed2, $content);

file_put_contents($path, $content);
echo "Repaired successfully\n";
