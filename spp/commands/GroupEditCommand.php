<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GroupEditCommand
 * Edits a shared resource group.
 */
class GroupEditCommand extends Command
{
    protected string $name = 'group:edit';
    protected string $description = 'Edit an existing shared resource group';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $groupName = $this->getArgument($args, 0) ?? null;

        if (!$groupName) {
            echo "Usage: php spp.php group:edit <group_name> [--extends=...] [--prefix=...]\n";
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

        $updated = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--extends=')) {
                $settings['shared_groups'][$groupName]['extends'] = substr($arg, 10);
                $updated = true;
            } elseif (str_starts_with($arg, '--prefix=')) {
                $settings['shared_groups'][$groupName]['table_prefix'] = substr($arg, 9);
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($gsPath, Yaml::dump($settings, 10, 2));
            echo "Success: Shared group '{$groupName}' updated successfully.\n";
        } else {
            echo "No changes made. Current config for '{$groupName}':\n";
            print_r($settings['shared_groups'][$groupName]);
        }
    }
}
