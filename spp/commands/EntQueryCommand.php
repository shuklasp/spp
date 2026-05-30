<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EntQueryCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $entityName = $argv[2] ?? null;
                $limit = $argv[3] ?? 10;
                if (!$entityName) die("Error: Entity name required.\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                
                try {
                    $db = new \SPPMod\SPPDB\SPPDB();
                    // Resolve table from YAML
                    $config = @\Symfony\Component\Yaml\Yaml::parseFile(\SPPMod\SPPEntity\SPPEntity::getEntityConfigFile($entityName));
                    $table = $config['table'] ?? strtolower($entityName).'s';
                    
                    $results = $db->exec_squery("SELECT * FROM %tab% LIMIT ?", $table, [(int)$limit]);
                    if (!empty($results)) {
                        printTable(array_keys($results[0]), $results);
                    } else {
                        echo "No records found in table '{$table}'.\n";
                    }
                } catch (\Exception $e) {
                    echo "Query Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'ent:query';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ent:query';
    }
}
