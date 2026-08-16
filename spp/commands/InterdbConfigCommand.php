<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

class InterdbConfigCommand extends Command
{
    protected string $name = 'interdb:config';
    protected string $description = 'Get or set the interdb operating mode';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $mode = null;
        foreach ($args as $arg) {
            if ($arg === 'spp.php' || $arg === $this->name || str_starts_with($arg, '--')) continue;
            $mode = $arg;
            break;
        }

        $path = SPP_MODULES_DIR . '/spp/sppinterdb/etc/config.yml';
        
        $config = [];
        if (file_exists($path)) {
            $config = Yaml::parseFile($path) ?: [];
        }

        if ($mode) {
            $config['mode'] = $mode;
            if (!isset($config['mappings'])) $config['mappings'] = [];
            
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
            file_put_contents($path, Yaml::dump($config, 4, 4));
            
            echo "InterDB mode set to: {$mode}\n";
        } else {
            $currentMode = $config['mode'] ?? 'interdb';
            echo "InterDB Mode: {$currentMode}\n";
        }
    }
}
