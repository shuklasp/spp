<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

class InterdbMappingAddCommand extends Command
{
    protected string $name = 'interdb:mapping:add';
    protected string $description = 'Add a new InterDB mapping';

    public function execute(array $args): void
    {
        $alias = null;
        $engine = null;
        $table = null;
        
        foreach ($args as $arg) {
            if ($arg === 'spp.php' || $arg === $this->name || str_starts_with($arg, '--')) continue;
            if (!$alias) $alias = $arg;
            elseif (!$engine) $engine = $arg;
            elseif (!$table) $table = $arg;
        }

        if (!$alias || !$engine || !$table) {
            echo "Usage: php spp.php interdb:mapping:add <alias> <engine> <table>\n";
            return;
        }

        $path = SPP_MODULES_DIR . '/spp/sppinterdb/etc/config.yml';
        $config = file_exists($path) ? (Yaml::parseFile($path) ?: []) : ['mode' => 'interdb'];
        if (!isset($config['mappings'])) $config['mappings'] = [];

        $config['mappings'][$alias] = [
            'engine' => $engine,
            'table' => $table
        ];

        if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
        file_put_contents($path, Yaml::dump($config, 4, 4));

        echo "Successfully added mapping for '{$alias}' -> {$engine}.{$table}\n";
    }
}
