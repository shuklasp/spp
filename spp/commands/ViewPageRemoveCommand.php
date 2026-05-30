<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewPageRemoveCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $name = $argv[2] ?? null;
                if (!$name) die("Usage: php spp.php view:page:remove <name>\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                if (\SPPMod\SPPView\Pages::removePage($name, 'yaml')) {
                    echo "Success: Removed from YAML.\n";
                } else if (\SPPMod\SPPView\Pages::removePage($name, 'db')) {
                    echo "Success: Removed from DB.\n";
                } else {
                    echo "Error: Page route not found.\n";
                }
    }

    public function getName(): string
    {
        return 'view:page:remove';
    }

    public function getDescription(): string
    {
        return 'Legacy port of view:page:remove';
    }
}
