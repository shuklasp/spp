<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GroupDeleteCommand
 * Deletes a shared resource group.
 */
class GroupDeleteCommand extends Command
{
    protected string $name = 'group:delete';
    protected string $description = 'Delete a shared resource group';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $groupName = $this->getArgument($args, 0) ?? null;

        if (!$groupName) {
            echo "Usage: php spp.php group:delete <group_name>\n";
            return;
        }

        $gsPath = SPP_BASE_DIR . '/etc/global-settings.yml';
        if (!file_exists($gsPath)) {
            echo "Error: global-settings.yml not found.\n";
            return;
        }

        $settings = Yaml::parseFile($gsPath);
        
        if (!isset($settings['shared_groups'][$groupName])) {
            echo "Error: Shared group '{$groupName}' does not exist.\n";
            return;
        }

        // Check if any app is using this group
        $inUseBy = [];
        foreach ($settings['apps'] as $app => $conf) {
            if (isset($conf['shared_group']) && $conf['shared_group'] === $groupName) {
                $inUseBy[] = $app;
            }
        }

        if (!empty($inUseBy)) {
            echo "Error: Cannot delete group '{$groupName}' because it is in use by: " . implode(', ', $inUseBy) . "\n";
            return;
        }

        unset($settings['shared_groups'][$groupName]);
        file_put_contents($gsPath, Yaml::dump($settings, 10, 2));
        
        echo "Success: Shared group '{$groupName}' deleted successfully.\n";
    }
}
