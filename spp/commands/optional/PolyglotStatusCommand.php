<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;

class PolyglotStatusCommand extends Command {
    protected string $name = 'polyglot:status';
    protected string $description = 'Checks runtime environment and activation status for polyglot language drivers';
    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void {
        $isJson = isset($args['json']) || in_array('--json', $args, true);
        
        $runtimes = class_exists('\\SPP\\Core\\Polyglot\\LanguageRegistry')
            ? \SPP\Core\Polyglot\LanguageRegistry::discoverRuntimes()
            : [];

        if ($isJson) {
            echo json_encode([
                'success' => true,
                'count' => count($runtimes),
                'runtimes' => $runtimes
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            return;
        }

        echo "Checking Polyglot runtime environment across active LanguageRegistry drivers...\n\n";

        echo "+-------------------+----------+---------+--------------------------------------------------+\n";
        echo "| Language          | State    | Runtime | Discovered Path / Version                        |\n";
        echo "+-------------------+----------+---------+--------------------------------------------------+\n";
        
        foreach ($runtimes as $id => $info) {
            $name = $info['name'] ?? ucfirst($id);
            $category = $info['category'] ?? 'interpreted';
            $enabled = $info['enabled'] ?? true;
            $state = $enabled ? "ENABLED" : "DISABLED";
            
            if ($category === 'dsl') {
                $status = "OK";
                $detail = "In-Process Sandboxed Engine";
            } else {
                $hasPath = !empty($info['path']);
                $status = $hasPath ? "OK" : "MISSING";
                $ver = $hasPath ? ($info['version'] ?? 'Discovered') : "Not found in PATH";
                $detail = substr($ver . ($hasPath ? " (" . basename($info['path']) . ")" : ""), 0, 48);
            }
            
            echo "| " . str_pad($name, 17) . " | " . str_pad($state, 8) . " | " . str_pad($status, 7) . " | " . str_pad($detail, 48) . " |\n";
        }
        echo "+-------------------+----------+---------+--------------------------------------------------+\n";
    }
}
