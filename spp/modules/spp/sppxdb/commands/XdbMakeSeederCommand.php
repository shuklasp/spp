<?php

namespace SPPMod\SPPXDB\Commands;

use SPP\CLI\Command;
use SPPMod\SPPXDB\SPP_XDB;
use SPPMod\SPPXDB\SeederManager;

/**
 * Class XdbMakeSeederCommand
 * Generates a new SPP_XDB Seeder stub.
 */
class XdbMakeSeederCommand extends Command
{
    public function getName(): string
    {
        return 'xdb:make:seeder';
    }

    public function getDescription(): string
    {
        return 'Create a new SPP_XDB seeder file';
    }

    public function execute(array $args): void
    {
        $name = null;
        
        foreach ($args as $arg) {
            if (strpos($arg, '--') === 0) continue;
            if (basename($arg) === 'spp.php' || $arg === 'spp/spp.php' || $arg === 'xdb:make:seeder') continue;
            $name = $arg;
            break;
        }

        if (!$name) {
            echo "Usage: php spp.php xdb:make:seeder <name_of_seeder>\n";
            return;
        }

        $db = new SPP_XDB();
        $mgr = new SeederManager($db);

        $path = $mgr->create($name);
        echo "Created seeder: $path\n";
    }
}
