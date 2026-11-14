<?php
namespace SPP\Core;

use SPP\Module;
use SPP\Core\ModuleCompiler;
use Symfony\Component\Yaml\Yaml;


class ModuleInstaller
{
    private static $db = null;

    public static function getDb()
    {
        if (self::$db === null) {
            try {
                if (class_exists('\\SPP\\DB')) {
                    self::$db = \SPP\DB::getInstance();
                }
            } catch (\Exception $e) {
                // DB not available
            }
        }
        return self::$db;
    }

    public static function setupSystemTables(): void
    {
        $db = self::getDb();
        if (!$db) return;

        $table = \SPP\DB::sppTable('spp_modules');
        if (!$db->tableExists($table)) {
            $db->execute_query("CREATE TABLE $table (
                name VARCHAR(100) PRIMARY KEY,
                version VARCHAR(50),
                installed_at DATETIME,
                last_updated_at DATETIME
            )");
        }
    }

    public static function getModuleState(string $moduleName): ?array
    {
        $db = self::getDb();
        if (!$db) return null;
        self::setupSystemTables();
        $table = \SPP\DB::sppTable('spp_modules');
        $res = $db->execute_query("SELECT * FROM $table WHERE name = ?", [$moduleName]);
        
        $state = count($res) > 0 ? $res[0] : null;
        file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[ModuleInstaller] getModuleState('$moduleName') returned: " . json_encode($state) . "\n", FILE_APPEND);
        return $state;
    }

    public static function install(string $moduleName): bool
    {
        Module::loadAllModules();
        $module = Module::getModule($moduleName);
        if (!$module) throw new \Exception("Module not found or not active: $moduleName");

        $state = self::getModuleState($moduleName);
        if ($state) {
            // Already installed. Attempt upgrade.
            return self::upgrade($moduleName);
        }

        $provider = self::resolveServiceProvider($module);
        
        if ($provider && method_exists($provider, 'preInstall')) {
            if ($provider->preInstall() === false) {
                return false; // Aborted by module
            }
        }

        self::executeDatabaseSync($module);
        
        if ($provider && method_exists($provider, 'install')) {
            $provider->install();
        }

        $installScript = $module->ModPath . SPP_DS . 'install.php';
        if (file_exists($installScript)) {
            require $installScript;
        }

        if ($provider && method_exists($provider, 'postInstall')) {
            $provider->postInstall();
        }

        $db = self::getDb();
        if ($db) {
            $table = \SPP\DB::sppTable('spp_modules');
            $db->execute_query("INSERT INTO $table (name, version, installed_at, last_updated_at) VALUES (?, ?, ?, ?)", [
                $moduleName,
                $module->Version ?? '1.0',
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);
        }

        return true;
    }

    /**
     * Executes both standalone db.yml definitions and manifest installation deltas.
     */
    public static function executeDatabaseSync(Module $module): void
    {
        // 1. Standalone db.yml schema
        self::executeDbYml($module);

        // 2. Backward-compatible manifest installation block
        if (!empty($module->Installation) && method_exists($module, 'runInstallation')) {
            $module->runInstallation();
        }
    }

    public static function executeDbYml(Module $module): void
    {
        $dbFile = $module->ModPath . SPP_DS . 'db.yml';
        if (!file_exists($dbFile)) return;

        $db = self::getDb();
        if (!$db) return;

        $data = Yaml::parseFile($dbFile);
        if (isset($data['tables'])) {
            foreach ($data['tables'] as $tableName => $cols) {
                $actualTable = \SPP\DB::sppTable($tableName);
                if (method_exists($db, 'createTableIncremental')) {
                    $db->createTableIncremental($actualTable, $cols);
                } else {
                    $query = "CREATE TABLE IF NOT EXISTS $actualTable (";
                    $defs = [];
                    $constraints = [];
                    foreach ($cols as $colName => $colDef) {
                        if (strtoupper($colName) === 'PRIMARY KEY') {
                            $constraints[] = "PRIMARY KEY $colDef";
                        } elseif (strtoupper($colName) === 'UNIQUE') {
                            $constraints[] = "UNIQUE $colDef";
                        } else {
                            $defs[] = "$colName $colDef";
                        }
                    }
                    $allDefs = array_merge($defs, $constraints);
                    $query .= implode(', ', $allDefs) . ")";
                    $db->execute_query($query);
                }
            }
        }

        if (isset($data['seeds'])) {
            foreach ($data['seeds'] as $tableName => $rows) {
                // Support Dynamic Seeder Classes
                if (is_string($rows) && class_exists($rows)) {
                    $seeder = new $rows();
                    if (method_exists($seeder, 'run')) {
                        $seeder->run();
                    }
                    continue;
                }

                if (!is_array($rows)) continue;

                $actualTable = \SPP\DB::sppTable($tableName);
                foreach ($rows as $row) {
                    // Check if exists
                    $where = [];
                    $params = [];
                    if (isset($row['id'])) {
                        $where[] = "id = ?";
                        $params[] = $row['id'];
                    } else {
                        $k = array_keys($row)[0];
                        $where[] = "$k = ?";
                        $params[] = $row[$k];
                    }
                    
                    $check = $db->execute_query("SELECT 1 FROM $actualTable WHERE " . implode(' AND ', $where), $params);
                    if (count($check) == 0) {
                        $cols = array_keys($row);
                        $vals = array_values($row);
                        $placeholders = implode(', ', array_fill(0, count($vals), '?'));
                        $insertQ = "INSERT INTO $actualTable (" . implode(', ', $cols) . ") VALUES ($placeholders)";
                        try {
                            $db->execute_query($insertQ, $vals);
                        } catch (\Exception $e) {
                            $schema = $db->getAdapter()->getSchema($actualTable);
                            echo "Schema for $actualTable: " . print_r($schema, true) . "\n";
                            throw $e;
                        }
                    }
                }
            }
        }
    }

    public static function upgrade(string $moduleName): bool
    {
        Module::loadAllModules();
        $module = Module::getModule($moduleName);
        if (!$module) throw new \Exception("Module not found: $moduleName");

        $state = self::getModuleState($moduleName);
        if (!$state) {
            return self::install($moduleName);
        }

        $currentVersion = $state['version'];
        $newVersion = $module->Version ?? '1.0';

        if ($currentVersion === $newVersion) {
            // Re-run executeDatabaseSync silently to ensure schema sync
            self::executeDatabaseSync($module);
            return true;
        }

        $provider = self::resolveServiceProvider($module);

        if ($provider && method_exists($provider, 'preUpgrade')) {
            if ($provider->preUpgrade($currentVersion, $newVersion) === false) {
                return false;
            }
        }

        self::executeDatabaseSync($module);

        if ($provider && method_exists($provider, 'update')) {
            $provider->update($currentVersion, $newVersion);
        }

        if ($provider && method_exists($provider, 'postUpgrade')) {
            $provider->postUpgrade($currentVersion, $newVersion);
        }

        $db = self::getDb();
        if ($db) {
            $table = \SPP\DB::sppTable('spp_modules');
            $db->execute_query("UPDATE $table SET version = ?, last_updated_at = ? WHERE name = ?", [
                $newVersion, 
                date('Y-m-d H:i:s'), 
                $moduleName
            ]);
        }
        
        return true;
    }

    /**
     * Resolves and instantiates the ServiceProvider for a module if declared.
     */
    public static function resolveServiceProvider(Module $module): ?object
    {
        $providerClass = $module->ServiceProvider ?? null;
        if (is_object($providerClass)) {
            return $providerClass;
        }
        if (is_string($providerClass) && class_exists($providerClass)) {
            return new $providerClass();
        }
        return null;
    }

    public static function uninstall(string $moduleName): bool
    {
        Module::loadAllModules();
        $module = Module::getModule($moduleName);
        if (!$module) return false;

        $provider = self::resolveServiceProvider($module);
        
        if ($provider && method_exists($provider, 'preUninstall')) {
            if ($provider->preUninstall() === false) {
                return false;
            }
        }

        if ($provider && method_exists($provider, 'uninstall')) {
            $provider->uninstall();
        }

        $uninstallScript = $module->ModPath . SPP_DS . 'uninstall.php';
        if (file_exists($uninstallScript)) {
            require $uninstallScript;
        }

        // We do NOT drop tables to prevent data loss.
        // We just drop the tracking.

        $db = self::getDb();
        if ($db) {
            $table = \SPP\DB::sppTable('spp_modules');
            $db->execute_query("DELETE FROM $table WHERE name = ?", [$moduleName]);
        }

        if ($provider && method_exists($provider, 'postUninstall')) {
            $provider->postUninstall();
        }

        return true;
    }

    public static function installAllActive(): array
    {
        Module::loadAllModules();
        $results = [];
        $modules = \SPP\Registry::get('__mods') ?? [];

        // Build DAG for dependency resolution
        $graph = [];
        $inDegree = [];
        foreach (array_keys($modules) as $modName) {
            $graph[$modName] = [];
            $inDegree[$modName] = 0;
        }

        foreach ($modules as $modName => $modPath) {
            $module = Module::getModule($modName);
            if ($module) {
                $depNames = method_exists($module, 'getDependencyNames') 
                    ? $module->getDependencyNames() 
                    : (array)($module->Dependencies ?? []);
                foreach ($depNames as $dep) {
                    $depClean = trim(explode('(', (string)$dep)[0]);
                    $depClean = trim(explode(' ', $depClean)[0]);
                    if (isset($modules[$depClean])) {
                        $graph[$depClean][] = $modName;
                        $inDegree[$modName]++;
                    }
                }
            }
        }

        // Topological Sort
        $queue = [];
        foreach ($inDegree as $node => $deg) {
            if ($deg === 0) {
                $queue[] = $node;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($graph[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sorted) !== count($modules)) {
            throw new \Exception("Circular dependency detected during module installation sequence.");
        }

        foreach ($sorted as $modName) {
            try {
                $res = self::install($modName);
                $results[$modName] = ['success' => $res, 'message' => 'OK'];
            } catch (\Exception $e) {
                $results[$modName] = ['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
            }
        }

        return $results;
    }

    public static function setModuleStatus(string $moduleName, string $status, ?string $context = null): bool
    {
        $moduleName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $moduleName);
        $status = in_array($status, ['active', 'inactive']) ? $status : 'inactive';

        if ($status === 'inactive' && class_exists('\\SPP\\Module') && \SPP\Module::isCompulsory($moduleName)) {
            throw new \SPP\SPPException("Module '{$moduleName}' is compulsory and cannot be disabled.");
        }

        $context = $context ?: (\SPP\Scheduler::getContext() ?: 'default');
        $manifests = [
            APP_ETC_DIR . SPP_DS . $context . SPP_DS . 'modsconf' . SPP_DS . 'modules.yml',
            SPP_ETC_DIR . SPP_DS . 'apps' . SPP_DS . $context . SPP_DS . 'modules.yml',
            SPP_ETC_DIR . SPP_DS . 'modules.yml'
        ];

        $found = false;
        foreach ($manifests as $file) {
            if (file_exists($file)) {
                $data = Yaml::parseFile($file);
                if (isset($data['modules'])) {
                    foreach ($data['modules'] as &$mod) {
                        $name = $mod['name'] ?? $mod['modname'] ?? null;
                        if ($name === $moduleName) {
                            $mod['status'] = $status;
                            $found = true;
                            file_put_contents($file, Yaml::dump($data, 6, 4));
                            break 2;
                        }
                    }
                }
            }
        }

        if (!$found) {
            // Append to user modconf
            $userConf = SPP_ETC_DIR . SPP_DS . 'apps' . SPP_DS . $context . SPP_DS . 'modules.yml';
            if (!file_exists(dirname($userConf))) {
                mkdir(dirname($userConf), 0777, true);
            }
            $data = file_exists($userConf) ? Yaml::parseFile($userConf) : ['modules' => []];
            $data['modules'][] = ['name' => $moduleName, 'status' => $status];
            file_put_contents($userConf, Yaml::dump($data, 6, 4));
        }

        if ($status === 'active' && class_exists('\\SPP\\Core\\ModuleConfigManager')) {
            try {
                \SPP\Core\ModuleConfigManager::ensureConfigForApp($moduleName, $context);
            } catch (\Throwable $e) {
            }
        }

        $compiler = new ModuleCompiler($context);
        try {
            $compiler->compile();
            return true;
        } catch (\SPP\Exceptions\MissingDependencyException $e) {
            // Rollback status
            self::setModuleStatus($moduleName, 'inactive');
            throw $e;
        }
    }
}
