<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ExtInstallCommand extends Command
{
    protected string $name = 'ext:install';
    protected string $description = 'Install an extension from a zip or directory';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $source = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--source=')) {
                $source = substr($arg, 9);
            }
        }
        
        if (!$source) {
            echo "Usage: php spp.php ext:install --source=<path_or_url>\n";
            return;
        }
        
        echo "Installing extension from $source...\n";
        echo "ExtInstallCommand is a stub. Implementation pending.\n";
    }
}
