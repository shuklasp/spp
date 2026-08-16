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
        \SPP\App::bootApp($appname);
        
        $dirsToScan = [
            SPP_APP_DIR . '/controllers',
            SPP_APP_DIR . '/src/Controllers',
            SPP_APP_DIR . '/src/controllers',
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
    }
}
