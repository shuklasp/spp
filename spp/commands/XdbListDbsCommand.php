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

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $isJson = $this->hasFlag($args, 'json');
        try {
            $xdbClass = file_exists(dirname(__DIR__) . '/modules/optional/sppxdb/class.sppxdb.php')
                ? dirname(__DIR__) . '/modules/optional/sppxdb/class.sppxdb.php'
                : dirname(__DIR__) . '/modules/spp/sppxdb/class.sppxdb.php';
            if (file_exists($xdbClass)) require_once($xdbClass);
            
            $xdb = new \SPPMod\SPPXDB\SPP_XDB();
            $results = $xdb->querySQL("SHOW DATABASES");
            $databases = [];
            if (!empty($results)) {
                foreach ($results as $row) {
                    $databases[] = $row['Database'] ?? current($row);
                }
            }

            if ($isJson) {
                echo json_encode(['success' => true, 'databases' => $databases]);
                return;
            }
            
            if (!empty($databases)) {
                echo "Available XDB Databases:\n";
                foreach ($databases as $db) {
                    echo " - " . $db . "\n";
                }
            } else {
                echo "No databases found.\n";
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
