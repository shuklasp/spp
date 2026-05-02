<?php
namespace SPP\CLI\Commands;

/**
 * Class XdbListDbsCommand
 * Lists all available XDB databases.
 */
class XdbListDbsCommand extends \SPP\CLI\Command
{
    protected string $name = 'xdb:list-dbs';
    protected string $description = 'List all available XDB databases';

    public function execute(array $args): void
    {
        try {
            $xdbClass = dirname(__DIR__) . '/modules/spp/sppxdb/class.sppxdb.php';
            if (file_exists($xdbClass)) require_once($xdbClass);
            
            $xdb = new \SPPMod\SPPXDB\SPP_XDB();
            $results = $xdb->querySQL("SHOW DATABASES");
            
            if (!empty($results)) {
                echo "Available XDB Databases:\n";
                foreach ($results as $row) {
                    echo " - " . $row['Database'] . "\n";
                }
            } else {
                echo "No databases found.\n";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
