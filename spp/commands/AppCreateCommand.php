<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AppCreateCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $appName = $argv[2] ?? prompt("Application Name (slug)");
                $appType = prompt("Application Type (javascript/php)", "php");
                $baseUrl = prompt("Base URL", "/" . $appName);
                
                $appDir = SPP_APP_DIR . "/etc/apps/{$appName}";
                if (is_dir($appDir)) die("Error: Application '{$appName}' already exists.\n");
        
                echo "Initializing directory structure for '{$appName}'...\n";
                $app = new \SPP\App($appName, true, 1); // Level 1 init to create dirs
                
                // Define additional directories
                $dirs = ['entities', 'forms', 'modsconf', 'pages'];
                foreach ($dirs as $d) {
                    $path = "{$appDir}/{$d}";
                    if (!is_dir($path)) mkdir($path, 0777, true);
                }
        
                $srcDir = SPP_APP_DIR . "/src/{$appName}";
                $subDirs = ($appType === 'php') ? ['pages', 'serv', 'components'] : ['comp', 'serv', 'store'];
                foreach ($subDirs as $sd) {
                    $path = "{$srcDir}/{$sd}";
                    if (!is_dir($path)) mkdir($path, 0777, true);
                }
        
                // Create manifest.yml
                $manifest = [
                    'app' => [
                        'name' => $appName,
                        'type' => $appType,
                        'version' => '1.0.0',
                        'description' => "Auto-generated {$appType}-SPA application."
                    ]
                ];
                file_put_contents("{$appDir}/manifest.yml", \Symfony\Component\Yaml\Yaml::dump($manifest));
        
                // Create services.yml
                $servicesYml = "################################################################################\n";
                $servicesYml .= "# SPP Service Registry (Manual)\n";
                $servicesYml .= "# Register your services here to bypass dynamic discovery overhead.\n";
                $servicesYml .= "# Syntax:\n";
                $servicesYml .= "# services:\n";
                $servicesYml .= "#   - name: MyService\n";
                $servicesYml .= "#     script: src/{$appName}/serv/MyService.php\n";
                $servicesYml .= "#     method: POST\n";
                $servicesYml .= "################################################################################\n\n";
                $servicesYml .= "services: []\n";
                file_put_contents("{$appDir}/services.yml", $servicesYml);
        
                // Create detected-services.yml
                $detectedYml = "################################################################################\n";
                $detectedYml .= "# SPP Detected Services Registry (Auto-discovered)\n";
                $detectedYml .= "# This file is automatically populated by the SPPAjax discovery engine.\n";
                $detectedYml .= "################################################################################\n\n";
                $detectedYml .= "services: []\n";
                file_put_contents("{$appDir}/detected-services.yml", $detectedYml);
        
                // Create initial modules.yml
                $modules = [
                    'modules' => [
                        ['name' => 'sppview', 'path' => 'spp/sppview'],
                        ['name' => 'sppajax', 'path' => 'spp/sppajax']
                    ]
                ];
                file_put_contents("{$appDir}/modules.yml", \Symfony\Component\Yaml\Yaml::dump($modules));
        
                // Registry update
                echo "Registering application in global-settings.yml...\n";
                $globalSettingsPath = __DIR__ . '/etc/global-settings.yml';
                $settings = \Symfony\Component\Yaml\Yaml::parseFile($globalSettingsPath);
                $settings['apps'][$appName] = [
                    'base_url' => $baseUrl,
                    'table_prefix' => $appName . '_',
                    'shared_group' => 'core',
                    'etc_path' => "etc/apps/{$appName}",
                    'src_path' => "src/{$appName}"
                ];
                file_put_contents($globalSettingsPath, \Symfony\Component\Yaml\Yaml::dump($settings, 4, 2));
        
                echo "Success: Application '{$appName}' created.\n";
    }

    public function getName(): string
    {
        return 'app:create';
    }

    public function getDescription(): string
    {
        return 'Legacy port of app:create';
    }
}
