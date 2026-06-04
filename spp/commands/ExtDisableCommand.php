<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ExtDisableCommand extends Command
{
    protected string $name = 'ext:disable';
    protected string $description = 'Disable a specific extension';

    public function execute(array $args): void
    {
        if (empty($args[0])) {
            echo "Usage: php spp.php ext:disable <extension_name>\n";
            return;
        }
        
        $ext = $args[0];
        echo "Disabling extension: $ext...\n";
        echo "ExtDisableCommand is a stub. Implementation pending.\n";
    }
}
