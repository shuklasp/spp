<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class UiWatchCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $appName = $argv[2] ?? $cliDefaultApp;
                if ($appName !== \SPP\Scheduler::getContext()) \SPP\Scheduler::setContext($appName);
                $compDir = SPP_APP_DIR . "/src/{$appName}/components";
                $genDir = SPP_APP_DIR . "/res/apps/{$appName}/generated";
        
                echo "Starting watcher for '{$appName}' (Ctrl+C to stop)...\n";
                $mtimes = [];
                
                while (true) {
                    $files = glob("{$compDir}/*.php");
                    foreach ($files as $file) {
                        $mtime = filemtime($file);
                        if (!isset($mtimes[$file]) || $mtimes[$file] != $mtime) {
                            $className = "App\\" . ucfirst($appName) . "\\Components\\" . basename($file, '.php');
                            echo "  [" . date('H:i:s') . "] Rebuilding {$className}...\n";
                            try {
                                if (!is_dir($genDir)) mkdir($genDir, 0777, true);
                                $js = \SPPMod\SPPView\JSGenerator::generate($className);
                                file_put_contents("{$genDir}/" . basename($file, '.php') . ".js", $js);
                                $mtimes[$file] = $mtime;
                            } catch (\Exception $e) {
                                echo "    [ERROR] " . $e->getMessage() . "\n";
                            }
                        }
                    }
                    usleep(500000); // 500ms
                }
    }

    public function getName(): string
    {
        return 'ui:watch';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ui:watch';
    }
}
