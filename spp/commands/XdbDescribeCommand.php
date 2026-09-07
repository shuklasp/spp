<?php
namespace SPP\CLI\Commands;

/**
 * Class XdbDescribeCommand
 * Describes the schema of an XDB table.
 */
class XdbDescribeCommand extends \SPP\CLI\Command
{
    protected string $name = 'xdb:describe';
    protected string $description = 'Describe the schema of an XDB table';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $db = 'default';
        $table = null;
        $isJson = $this->hasFlag($args, 'json');

        foreach ($args as $arg) {
            if (strpos($arg, '--db=') === 0) $db = substr($arg, 5);
            else if (strpos($arg, '--') !== 0 && !in_array($arg, ['spp.php', 'xdb:describe'])) {
                $table = $arg;
            }
        }

        if (!$table) {
            if ($isJson) {
                echo json_encode(['success' => false, 'error' => 'Table name required.']);
            } else {
                echo "Usage: php spp xdb:describe <table_name> [--db=dbname]\n";
            }
            return;
        }

        try {
            $xdbClass = file_exists(dirname(__DIR__) . '/modules/optional/sppxdb/class.sppxdb.php')
                ? dirname(__DIR__) . '/modules/optional/sppxdb/class.sppxdb.php'
                : dirname(__DIR__) . '/modules/spp/sppxdb/class.sppxdb.php';
            if (file_exists($xdbClass)) require_once($xdbClass);
            
            $xdb = new \SPPMod\SPPXDB\SPP_XDB($db);
            $results = $xdb->querySQL("DESCRIBE $table");

            if ($isJson) {
                echo json_encode(['success' => true, 'database' => $db, 'table' => $table, 'columns' => $results ?: []]);
                return;
            }
            
            if (!empty($results)) {
                echo "Schema for table '{$table}' in database '{$db}':\n";
                if (function_exists('printTable')) {
                    printTable(array_keys($results[0]), $results);
                } else {
                    print_r($results);
                }
            } else {
                echo "No schema found for table '{$table}'.\n";
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
