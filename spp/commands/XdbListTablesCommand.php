<?php
namespace SPP\CLI\Commands;

/**
 * Class XdbListTablesCommand
 * Lists all tables in a specific XDB database.
 */
class XdbListTablesCommand extends \SPP\CLI\Command
{
    protected string $name = 'xdb:list-tables';
    protected string $description = 'List all tables in an XDB database';

    public function execute(array $args): void
    {
        $db = 'default';
        foreach ($args as $arg) {
            if (strpos($arg, '--db=') === 0) $db = substr($arg, 5);
        }

        try {
            $xdbClass = dirname(__DIR__) . '/modules/spp/sppxdb/class.sppxdb.php';
            if (file_exists($xdbClass)) require_once($xdbClass);
            
            $xdb = new \SPPMod\SPPXDB\SPP_XDB($db);
            $results = $xdb->querySQL("SHOW TABLES");
            
            if (!empty($results)) {
                echo "Tables in database '{$db}':\n";
                foreach ($results as $row) {
                    echo " - " . current($row) . "\n";
                }
            } else {
                echo "No tables found in database '{$db}'.\n";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
