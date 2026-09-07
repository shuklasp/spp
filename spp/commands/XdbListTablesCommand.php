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

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $db = 'default';
        $isJson = $this->hasFlag($args, 'json');
        foreach ($args as $arg) {
            if (strpos($arg, '--db=') === 0) $db = substr($arg, 5);
        }

        try {
            $xdbClass = file_exists(dirname(__DIR__) . '/modules/optional/sppxdb/class.sppxdb.php')
                ? dirname(__DIR__) . '/modules/optional/sppxdb/class.sppxdb.php'
                : dirname(__DIR__) . '/modules/spp/sppxdb/class.sppxdb.php';
            if (file_exists($xdbClass)) require_once($xdbClass);
            
            $xdb = new \SPPMod\SPPXDB\SPP_XDB($db);
            $results = $xdb->querySQL("SHOW TABLES");
            $tables = [];
            if (!empty($results)) {
                foreach ($results as $row) {
                    $tables[] = current($row);
                }
            }

            if ($isJson) {
                echo json_encode(['success' => true, 'database' => $db, 'tables' => $tables]);
                return;
            }
            
            if (!empty($tables)) {
                echo "Tables in database '{$db}':\n";
                foreach ($tables as $table) {
                    echo " - " . $table . "\n";
                }
            } else {
                echo "No tables found in database '{$db}'.\n";
            }
        } catch (\Exception $e) {
            if ($isJson) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            } else {
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
    }
}
