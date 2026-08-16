<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GroupCreateCommand
 * Creates a new shared resource group.
 */
class GroupCreateCommand extends Command
{
    protected string $name = 'group:create';
    protected string $description = 'Create a new shared resource group';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $groupName = $this->getArgument($args, 0) ?? null;

        if (!$groupName) {
            echo "Usage: php spp.php group:create <group_name> [--extends=core] [--prefix=...]\n";
            return;
        }

        $gsPath = SPP_BASE_DIR . '/etc/global-settings.yml';
        if (!file_exists($gsPath)) {
            echo "Error: global-settings.yml not found.\n";
            return;
        }

        $settings = Yaml::parseFile($gsPath);
        
        if (!isset($settings['shared_groups'])) {
            $settings['shared_groups'] = [];
        }

        if (isset($settings['shared_groups'][$groupName])) {
            echo "Error: Shared group '{$groupName}' already exists.\n";
            return;
        }

        $extends = 'core';
        $prefix = '';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--extends=')) {
                $extends = substr($arg, 10);
            } elseif (str_starts_with($arg, '--prefix=')) {
                $prefix = substr($arg, 9);
            }
        }

        $settings['shared_groups'][$groupName] = [
            'extends' => $extends,
            'table_prefix' => $prefix,
            'entities' => []
        ];

        file_put_contents($gsPath, Yaml::dump($settings, 10, 2));
        echo "Success: Shared group '{$groupName}' created successfully.\n";
    }
}
