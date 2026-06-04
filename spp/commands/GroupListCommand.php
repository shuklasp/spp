<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GroupListCommand
 * Lists shared resource groups.
 */
class GroupListCommand extends Command
{
    protected string $name = 'group:list';
    protected string $description = 'List all shared resource groups';

    public function execute(array $args): void
    {
        $gsPath = SPP_BASE_DIR . '/etc/global-settings.yml';
        if (!file_exists($gsPath)) {
            echo "Error: global-settings.yml not found.\n";
            return;
        }

        $settings = Yaml::parseFile($gsPath);
        $groups = $settings['shared_groups'] ?? [];

        if (empty($groups)) {
            echo "No shared groups found.\n";
            return;
        }

        echo "\nShared Resource Groups:\n";
        echo str_pad("Group Name", 20) . str_pad("Extends", 15) . str_pad("Table Prefix", 15) . "Entities\n";
        echo str_repeat("-", 80) . "\n";

        foreach ($groups as $name => $conf) {
            $extends = $conf['extends'] ?? 'none';
            $prefix = $conf['table_prefix'] ?? '';
            $entitiesCount = isset($conf['entities']) ? count((array)$conf['entities']) : 0;
            
            echo str_pad($name, 20) . str_pad($extends, 15) . str_pad($prefix, 15) . "{$entitiesCount} entities\n";
        }
        echo "\n";
    }
}
