<?php
namespace SPP\CLI\Commands;

/**
 * Class XdbCommand
 * Executes SQL, XPath, XQuery, or XQUF queries on the XML database via CLI.
 */
class XdbCommand extends \SPP\CLI\Command
{
    protected string $name = 'xdb:query';
    protected string $description = 'Execute a SQL, XPath, XQuery, or XQUF query on the XML database';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        // Identify the query string (the first argument that isn't the script or command name)
        $query = null;
        foreach ($args as $arg) {
            if (strpos($arg, '--') === 0) continue;
            if (basename($arg) === 'spp.php' || $arg === 'spp/spp.php' || $arg === 'xdb:query') continue;
            $query = $arg;
            break;
        }

        $isJson = $this->hasFlag($args, 'json');
        if (!$query) {
            if ($isJson) {
                echo json_encode(['success' => false, 'error' => 'Query string required.']);
            } else {
                echo "Usage: php spp xdb:query \"SELECT * FROM db.table\" [--type=sql|xpath|xquery|xquf]\n";
            }
            return;
        }

        $type = 'sql';
        
        foreach ($args as $arg) {
            if (strpos($arg, '--type=') === 0) {
                $type = substr($arg, 7);
            }
        }

        try {
            // Ensure module class is loaded
            $xdbClass = file_exists(dirname(__DIR__) . '/modules/optional/sppxdb/class.sppxdb.php')
                ? dirname(__DIR__) . '/modules/optional/sppxdb/class.sppxdb.php'
                : dirname(__DIR__) . '/modules/spp/sppxdb/class.sppxdb.php';
            if (file_exists($xdbClass)) {
                require_once($xdbClass);
            }
            
            $xdb = new \SPPMod\SPPXDB\SPP_XDB();

            if ($type === 'xpath') {
                if (!$isJson) echo "Executing XPath: $query\n";
                $results = $xdb->queryX($query);
            } elseif ($type === 'xquery') {
                if (!$isJson) echo "Executing XQuery: $query\n";
                $results = $xdb->queryXQuery($query);
            } elseif ($type === 'xquf') {
                if (!$isJson) echo "Executing XQUF Mutation: $query\n";
                $results = $xdb->updateXQUF($query);
            } else {
                if (!$isJson) echo "Executing SQL: $query\n";
                $results = $xdb->querySQL($query);
            }

            if ($isJson) {
                echo json_encode(['success' => true, 'results' => $results ?: []]);
                return;
            }

            if (is_array($results)) {
                echo "Found " . count($results) . " records:\n";
                print_r($results);
            } else {
                echo "Operation successful: " . var_export($results, true) . "\n";
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
