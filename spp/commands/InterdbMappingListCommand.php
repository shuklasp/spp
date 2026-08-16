<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

class InterdbMappingListCommand extends Command
{
    protected string $name = 'interdb:mapping:list';
    protected string $description = 'List all InterDB mappings';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $path = SPP_MODULES_DIR . '/spp/sppinterdb/etc/config.yml';
        if (!file_exists($path)) {
            echo "InterDB is not configured.\n";
            return;
        }

        $config = Yaml::parseFile($path) ?: [];
        $mappings = $config['mappings'] ?? [];

        if (empty($mappings)) {
            echo "No InterDB mappings found.\n";
            return;
        }

        echo "InterDB Mappings:\n";
        echo str_repeat("-", 60) . "\n";
        echo str_pad("Alias", 20) . str_pad("Engine", 15) . "Table\n";
        echo str_repeat("-", 60) . "\n";

        foreach ($mappings as $alias => $data) {
            $engine = $data['engine'] ?? 'default';
            $table = $data['table'] ?? '';
            echo str_pad($alias, 20) . str_pad($engine, 15) . "{$table}\n";
        }
    }
}
