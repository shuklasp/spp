<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

class InterdbMappingRemoveCommand extends Command
{
    protected string $name = 'interdb:mapping:remove';
    protected string $description = 'Remove an InterDB mapping';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $alias = null;
        foreach ($args as $arg) {
            if ($arg === 'spp.php' || $arg === $this->name || str_starts_with($arg, '--')) continue;
            $alias = $arg;
            break;
        }

        if (!$alias) {
            echo "Usage: php spp.php interdb:mapping:remove <alias>\n";
            return;
        }

        $path = SPP_MODULES_DIR . '/spp/sppinterdb/etc/config.yml';
        if (!file_exists($path)) {
            echo "InterDB is not configured.\n";
            return;
        }

        $config = Yaml::parseFile($path) ?: [];
        if (!isset($config['mappings'][$alias])) {
            echo "Mapping '{$alias}' not found.\n";
            return;
        }

        unset($config['mappings'][$alias]);
        file_put_contents($path, Yaml::dump($config, 4, 4));

        echo "Successfully removed mapping '{$alias}'.\n";
    }
}
