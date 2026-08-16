<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class AppSetBaseCommand
 * Sets the base application in the SPP configuration.
 */
class AppSetBaseCommand extends Command
{
    protected string $name = 'app:set-base';
    protected string $description = 'Set an application as the primary/base application';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appName = $this->getArgument($args, 0) ?? null;

        if (!$appName) {
            echo "Usage: php spp.php app:set-base <app_name>\n";
            return;
        }

        $gsPath = SPP_BASE_DIR . '/etc/global-settings.yml';
        if (!file_exists($gsPath)) {
            echo "Error: global-settings.yml not found.\n";
            return;
        }

        $settings = Yaml::parseFile($gsPath);
        
        if (!isset($settings['apps'][$appName])) {
            echo "Error: Application '{$appName}' is not registered.\n";
            return;
        }

        $settings['base_app'] = $appName;
        file_put_contents($gsPath, Yaml::dump($settings, 10, 2));

        echo "Success: '{$appName}' is now the primary/base application.\n";
    }
}
