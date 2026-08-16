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

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appName = $this->getArgument($args, 0);

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

        $baseUrl = $this->getOption($args, 'base_url');
        if ($baseUrl !== null) {
            $appConfig['base_url'] = $baseUrl;
            $updated = true;
            echo "Set base_url to '{$appConfig['base_url']}'\n";
        }

        $tablePrefix = $this->getOption($args, 'table_prefix');
        if ($tablePrefix !== null) {
            $appConfig['table_prefix'] = $tablePrefix;
            $updated = true;
            echo "Set table_prefix to '{$appConfig['table_prefix']}'\n";
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
