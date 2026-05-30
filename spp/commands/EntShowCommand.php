<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EntShowCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $entityName = $argv[2] ?? null;
                if (!$entityName) die("Error: Entity name required.\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                
                $cfgFile = \SPPMod\SPPEntity\SPPEntity::getEntityConfigFile($entityName);
                if (!$cfgFile) die("Error: Entity '{$entityName}' not found.\n");
                
                echo "Entity Definition: {$entityName}\n";
                echo "Path: " . realpath($cfgFile) . "\n";
                echo "------------------------------------------\n";
                echo file_get_contents($cfgFile);
                echo "------------------------------------------\n";
    }

    public function getName(): string
    {
        return 'ent:show';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ent:show';
    }
}
