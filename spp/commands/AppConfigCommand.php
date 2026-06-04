<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class AppConfigCommand
 * Configures application settings like base_url or table_prefix.
 */
class AppConfigCommand extends Command
{
    protected string $name = 'app:config';
    protected string $description = 'Configure application settings (e.g., base_url, table_prefix)';

    public function execute(array $args): void
    {
        $appName = $args[2] ?? null;

        if (!$appName) {
            echo "Usage: php spp.php app:config <app_name> [--base_url=...] [--table_prefix=...]\n";
            return;
        }

        $gsPath = SPP_BASE_DIR . '/etc/global-settings.yml';
        if (!file_exists($gsPath)) {
            echo "Error: global-settings.yml not found.\n";
            return;
        }

        $settings = Yaml::parseFile($gsPath);
        
        if (!isset($settings['apps'][$appName])) {
            echo "Error: Application '{$appName}' is not registered in global-settings.\n";
            return;
        }

        $appConfig = &$settings['apps'][$appName];
        $updated = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--base_url=')) {
                $appConfig['base_url'] = substr($arg, 11);
                $updated = true;
                echo "Set base_url to '{$appConfig['base_url']}'\n";
            } elseif (str_starts_with($arg, '--table_prefix=')) {
                $appConfig['table_prefix'] = substr($arg, 15);
                $updated = true;
                echo "Set table_prefix to '{$appConfig['table_prefix']}'\n";
            }
        }

        if ($updated) {
            file_put_contents($gsPath, Yaml::dump($settings, 10, 2));
            echo "Success: Application '{$appName}' configuration updated.\n";
        } else {
            echo "No valid configuration flags provided. Current config for '{$appName}':\n";
            print_r($appConfig);
        }
    }
}
