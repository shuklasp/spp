<?php
$content = file_get_contents('spp/core/class.module.php');
$inject = <<<PHP
    public static function loadAllModules(): void
    {
        \$start = microtime(true);
        error_log("loadAllModules start");
PHP;
$content = preg_replace('/public static function loadAllModules\(\): void\s*\{/', $inject, $content);
$content = str_replace('// --- Phase 1: Try Compiled Cache (High Performance) ---', 'error_log("Phase 1 start: " . (microtime(true) - $start)); // --- Phase 1: Try Compiled Cache (High Performance) ---', $content);
$content = str_replace('// --- Phase 2: Runtime Topological Boot (Debug Mode) ---', 'error_log("Phase 2 start: " . (microtime(true) - $start)); // --- Phase 2: Runtime Topological Boot (Debug Mode) ---', $content);
$content = str_replace('self::$allModulesLoaded = true;', 'error_log("loadAllModules end: " . (microtime(true) - $start)); self::$allModulesLoaded = true;', $content);
file_put_contents('spp/core/class.module.php', $content);
echo "Injected.\n";
