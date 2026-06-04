<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

class AppDefaultCommand extends Command {
    protected string $name = 'app:default';
    protected string $description = 'Set or view the default global CLI application context';
    public function execute(array $args): void {
        $setApp = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--set=')) $setApp = substr($arg, 6);
        }
        $cliSettingsPath = SPP_APP_DIR . '/spp/etc/cli-settings.yml';
        if ($setApp) {
            $settings = file_exists($cliSettingsPath) ? Yaml::parseFile($cliSettingsPath) : [];
            $settings['default_app'] = $setApp;
            file_put_contents($cliSettingsPath, Yaml::dump($settings));
            echo "Default CLI app context set to '{$setApp}'.\n";
        } else {
            $settings = file_exists($cliSettingsPath) ? Yaml::parseFile($cliSettingsPath) : [];
            $current = $settings['default_app'] ?? 'default';
            echo "Current default CLI app context is '{$current}'.\n";
            echo "Use --set=<app_name> to change it.\n";
        }
    }
}
