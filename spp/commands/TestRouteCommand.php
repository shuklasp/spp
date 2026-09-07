<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;

class TestRouteCommand extends Command
{
    protected string $name = 'test:routes';
    protected string $description = 'Test route scanner';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'Samvaad';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() use ($appname) {
            $dirsToScan = [
                SPP_APP_DIR . '/controllers',
                SPP_APP_DIR . '/src/' . $appname . '/controllers',
                SPP_APP_DIR . '/src/' . $appname . '/Controllers',
                SPP_APP_DIR . '/serv'
            ];
            
            $routes = [];
            foreach ($dirsToScan as $dir) {
                if (is_dir($dir)) {
                    echo "Scanning $dir...\n";
                    $scanned = \SPPMod\SPPView\RouteScanner::scan($dir);
                    $routes = array_merge($routes, $scanned);
                }
            }
            
            print_r($routes);
        });
    }
}
