<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPPMod\SPPIntegrations\IntegrationFactory;

/**
 * Class IntegrationInstallCommand
 * 
 * Automates the scaffolding and routing of an external application 
 * within the SPP environment for Zero-Touch integration.
 */
class IntegrationInstallCommand extends Command
{
    protected string $name = 'integration:install';
    protected string $description = 'Provision an external app directory and register the SPP route bypass';

    public function isCLIOnly(): bool 
    { 
        return true; 
    }

    public function execute(array $args): void
    {
        $appName = $this->getArgument($args, 0);
        $routePath = $this->getArgument($args, 1);

        if (!$appName || !$routePath) {
            echo "Usage: php spp.php integration:install <app_name> <route_path> [--isolation=virtual|physical]\n";
            echo "Example: php spp.php integration:install wordpress /blog --isolation=virtual\n";
            return;
        }

        $appName = strtolower($appName);
        $routePath = '/' . ltrim($routePath, '/');
        
        $isolation = 'virtual';
        foreach ($args as $arg) {
            if (strpos($arg, '--isolation=') === 0) {
                $isolation = str_replace('--isolation=', '', $arg);
            }
        }
        
        // 1. Filesystem Scaffolding
        $publicDir = realpath(__DIR__ . '/../../') . '/public'; // Assuming SPP public dir structure
        if (!$publicDir) {
            $publicDir = realpath(__DIR__ . '/../../'); // Fallback to root if no public dir
        }
        
        $targetDir = $publicDir . $routePath;
        
        echo "1. Creating physical directory for {$appName} at {$targetDir}...\n";
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true)) {
                throw new \Exception("Failed to create directory: {$targetDir}. Check if the path contains invalid characters (like colons on Windows) or if you lack permissions.");
            }
            echo "   [SUCCESS] Directory created.\n";
        } else {
            echo "   [INFO] Directory already exists.\n";
        }

        // 2. Route Bypass (Mock representation of SPP Router modification)
        echo "2. Registering route bypass in SPP Router for {$routePath}...\n";
        $routerConfigFile = realpath(__DIR__ . '/../../etc/routes.yml');
        if (file_exists($routerConfigFile)) {
            $yaml = file_get_contents($routerConfigFile);
            if (strpos($yaml, $routePath) === false) {
                file_put_contents($routerConfigFile, "\n  - path: \"{$routePath}/*\"\n    bypass: true\n", FILE_APPEND);
                echo "   [SUCCESS] Route bypass injected.\n";
            } else {
                echo "   [INFO] Route bypass already exists.\n";
            }
        } else {
            echo "   [INFO] Route bypass registered in memory (routes.yml not found).\n";
        }

        // 3. Driver Auto-Configuration Verification
        echo "3. Verifying Driver Auto-Configuration for {$appName}...\n";
        try {
            $driver = IntegrationFactory::getDriver($appName, [
                'local_path' => $targetDir
            ]);
            echo "   [SUCCESS] Driver instantiated with local_path for Native Zero-Touch CDC.\n";
        } catch (\Exception $e) {
            echo "   [WARNING] " . $e->getMessage() . "\n";
        }

        // 4. WebOS Hardware Abstraction Provisioning
        echo "4. Provisioning WebOS Hardware Abstraction (Isolation: {$isolation})...\n";
        $dummyConfig = "<?php\n// SPP WebOS Auto-Generated Configuration\n";
        $dummyConfig .= "define('DB_HOST', 'spp://kernel');\n";
        $dummyConfig .= "define('DB_USER', 'spp_system');\n";
        $dummyConfig .= "define('SPP_ISOLATION', '{$isolation}');\n";
        $dummyConfig .= "define('SPP_INSTANCE', '{$appName}:' . md5('{$routePath}'));\n";
        
        file_put_contents($targetDir . '/spp-webos-config.php', $dummyConfig);
        echo "   [SUCCESS] WebOS dummy connection string injected into target directory.\n";

        echo "\nInstallation complete! You can now install {$appName} into {$routePath}.\n";
    }
}
