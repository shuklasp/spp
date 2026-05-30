<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class UiBuildCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $appName = $argv[2] ?? $cliDefaultApp;
                if ($appName !== \SPP\Scheduler::getContext()) \SPP\Scheduler::setContext($appName);
                $compDir = SPP_APP_DIR . "/src/{$appName}/components";
                $genDir = SPP_APP_DIR . "/res/apps/{$appName}/generated";
                
                if (!is_dir($compDir)) die("Error: Component directory not found for '{$appName}'.\n");
                if (!is_dir($genDir)) mkdir($genDir, 0777, true);
                
                echo "Building components for '{$appName}'...\n";
                $files = glob("{$compDir}/*.php");
                foreach ($files as $file) {
                    $className = "App\\" . ucfirst($appName) . "\\Components\\" . basename($file, '.php');
                    echo "  Generating JS for {$className}...\n";
                    try {
                        $js = \SPPMod\SPPView\JSGenerator::generate($className);
                        file_put_contents("{$genDir}/" . basename($file, '.php') . ".js", $js);
                    } catch (\Exception $e) {
                        echo "  [ERROR] " . $e->getMessage() . "\n";
                    }
                }
                echo "Build completed.\n";
    }

    public function getName(): string
    {
        return 'ui:build';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ui:build';
    }
}
