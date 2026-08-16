<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ExtEnableCommand extends Command
{
    protected string $name = 'ext:enable';
    protected string $description = 'Enable a specific extension';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        if (empty($this->getArgument($args, 0))) {
            echo "Usage: php spp.php ext:enable <extension_name>\n";
            return;
        }
        
        $ext = $this->getArgument($args, 0);
        echo "Enabling extension: $ext...\n";
        echo "ExtEnableCommand is a stub. Implementation pending.\n";
    }
}
