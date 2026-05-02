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

    public function execute(array $args): void
    {
        $db = 'default';
        $table = null;

        foreach ($args as $arg) {
            if (strpos($arg, '--db=') === 0) $db = substr($arg, 5);
            else if (strpos($arg, '--') !== 0 && !in_array($arg, ['spp.php', 'xdb:describe'])) {
                $table = $arg;
            }
        }

        if (!$table) {
            echo "Usage: php spp xdb:describe <table_name> [--db=dbname]\n";
            return;
        }

        try {
            $xdbClass = dirname(__DIR__) . '/modules/spp/sppxdb/class.sppxdb.php';
            if (file_exists($xdbClass)) require_once($xdbClass);
            
            $xdb = new \SPPMod\SPPXDB\SPP_XDB($db);
            $results = $xdb->querySQL("DESCRIBE $table");
            
            if (!empty($results)) {
                echo "Schema for table '{$table}' in database '{$db}':\n";
                // We use the global printTable function if available in spp.php context
                if (function_exists('printTable')) {
                    printTable(array_keys($results[0]), $results);
                } else {
                    print_r($results);
                }
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
