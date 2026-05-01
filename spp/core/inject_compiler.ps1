$path = 'c:\projects\apache\school1\spp\core\class.module.php'
$content = [System.IO.File]::ReadAllText($path)

$cacheCode = @"

        // --- Phase 1: Try Compiled Cache (High Performance) ---
        if (!defined('SPP_DEBUG') || !SPP_DEBUG) {
            $cacheFile = \SPP\Core\ModuleCompiler::getCachePath($appname);
            if (file_exists($cacheFile)) {
                $compiled = require $cacheFile;
                if (is_array($compiled)) {
                    foreach ($compiled as $name => $data) {
                        self::initFromCache($name, $data);
                    }
                    self::$allModulesLoaded = true;
                    return;
                }
            }
        }
"@

$initFromCache = @"
    /**
     * Rapid initialization of a module from compiled cache data.
     */
    private static function initFromCache(string $name, array $data): void
    {
        \SPP\Registry::register('__mods=>' . `$name, `$data['path']);
        
        `$manifestPath = `$data['path'] . SPP_DS . 'module.yml';
        if (!file_exists(`$manifestPath)) `$manifestPath = `$data['path'] . SPP_DS . 'module.xml';
        
        `$module = new self(`$manifestPath);
        `$module->ModuleType = `$data['type'];
        
        \SPP\Registry::register('__modobj=>' . `$name, `$module);

        if (!empty(`$data['services'])) {
            `$module->registerServices(`$data['services']);
        }

        foreach (`$data['includes'] as `$file) {
            `$path = `$data['path'] . SPP_DS . `$file;
            if (file_exists(`$path)) require_once `$path;
        }

        `$initFile = `$data['path'] . SPP_DS . 'modinit.php';
        if (file_exists(`$initFile)) require_once `$initFile;
        
        `$eventsDir = `$data['path'] . SPP_DS . 'events';
        if (is_dir(`$eventsDir)) {
            \SPP\SPPEvent::scanAndRegisterDirs(`$eventsDir);
        }
    }

"@

# Inject cache check
if ($content -match '(\$loadedContexts\[\$appname\] = true;)') {
    $content = $content -replace '(\$loadedContexts\[\$appname\] = true;)', "`$1`r`n$cacheCode"
}

# Inject initFromCache method after loadAllModules
if ($content -match '(self::\$allModulesLoaded = true;\s+\})') {
    $content = $content -replace '(self::\$allModulesLoaded = true;\s+\})', "`$1`r`n`r`n$initFromCache"
}

[System.IO.File]::WriteAllText($path, $content)
