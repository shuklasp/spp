<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ExtDisableCommand extends Command
{
    protected string $name = 'ext:disable';
    protected string $description = 'Disable a specific extension';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        if (empty($this->getArgument($args, 0))) {
            echo "Usage: php spp.php ext:disable <extension_name>\n";
            return;
        }
        
        $ext = $this->getArgument($args, 0);
        echo "Disabling extension: $ext...\n";
        echo "ExtDisableCommand is a stub. Implementation pending.\n";
    }
}
