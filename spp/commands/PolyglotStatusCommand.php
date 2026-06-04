<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;

class PolyglotStatusCommand extends Command {
    protected string $name = 'polyglot:status';
    protected string $description = 'Checks the runtime environment for polyglot language binaries';
    public function execute(array $args): void {
        echo "Checking Polyglot runtime environment...\n\n";
        
        $binaries = [
            'Python 3' => 'python --version',
            'Node.js' => 'node --version',
            'Go' => 'go version',
            'Ruby' => 'ruby --version',
            'Rust' => 'rustc --version'
        ];

        echo "+--------------+---------+------------------------------------------+\n";
        echo "| Language     | Status  | Version Info                             |\n";
        echo "+--------------+---------+------------------------------------------+\n";
        
        foreach ($binaries as $lang => $cmd) {
            $output = [];
            $returnVar = 0;
            exec($cmd . ' 2>&1', $output, $returnVar);
            
            if ($returnVar === 0 && !empty($output)) {
                $status = "OK";
                $version = substr(implode(" ", $output), 0, 40);
            } else {
                $status = "MISSING";
                $version = "Not found in PATH";
            }
            
            echo "| " . str_pad($lang, 12) . " | " . str_pad($status, 7) . " | " . str_pad($version, 40) . " |\n";
        }
        echo "+--------------+---------+------------------------------------------+\n";
    }
}
