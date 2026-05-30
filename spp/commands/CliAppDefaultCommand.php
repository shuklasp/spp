<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class CliAppDefaultCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $appName = $argv[2] ?? prompt("Default Application Name", $cliDefaultApp);
                
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                $globalSettingsPath = __DIR__ . '/etc/global-settings.yml';
                $settings = \Symfony\Component\Yaml\Yaml::parseFile($globalSettingsPath);
                
                if (!isset($settings['apps'][$appName])) {
                    die("Error: Application '{$appName}' is not registered in global-settings.yml.\n");
                }
        
                $cliSettings['default_app'] = $appName;
                file_put_contents($cliSettingsPath, \Symfony\Component\Yaml\Yaml::dump($cliSettings));
                echo "Success: '{$appName}' set as the default CLI application context.\n";
    }

    public function getName(): string
    {
        return 'cli:app:default';
    }

    public function getDescription(): string
    {
        return 'Legacy port of cli:app:default';
    }
}
