<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class AppServeCommand
 *
 * Universal Sovereign Web Server: Starts a full-featured HTTP web server for SPP
 * applications, APIs, and the SPPAdmin workbench with ZERO Apache or Nginx dependency.
 */
class AppServeCommand extends Command
{
    protected string $name = 'serve';
    protected string $description = 'Start the sovereign SPP HTTP web server (zero Apache required)';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
            echo "Usage: php spp.php serve [--host=0.0.0.0] [--port=8000] [--app=name]\n";
            echo "Start the sovereign SPP HTTP web server with full web access without Apache.\n\n";
            echo "Options:\n";
            echo "  --host=<ip>    Interface to bind (default: 0.0.0.0 for full local and network access)\n";
            echo "  --port=<port>  Port to listen on (default: 8000, or 80 for standard HTTP)\n";
            echo "  --app=<name>   Default application context to boot\n";
            return;
        }

        $host = '0.0.0.0';
        $port = 8000;
        $appContext = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--host=')) {
                $host = trim(substr($arg, 7));
            } elseif (str_starts_with($arg, '--port=')) {
                $port = (int)substr($arg, 7);
            } elseif (str_starts_with($arg, '--app=')) {
                $appContext = trim(substr($arg, 6));
            }
        }

        if ($appContext !== null && class_exists('\\SPP\\Scheduler')) {
            \SPP\Scheduler::setContext($appContext);
        }

        $activeContext = \SPP\Scheduler::getContext() ?: ($appContext ?: 'default');
        $root = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2);
        $router = $root . '/spp/router.php';

        // Detect LAN IP address for network devices
        $lanIp = gethostbyname(gethostname());
        if ($lanIp === gethostname() || $lanIp === '127.0.0.1') {
            $lanIp = '127.0.0.1';
        }

        $displayLocalHost = ($host === '0.0.0.0' || $host === '127.0.0.1') ? 'localhost' : $host;
        $localUrl = "http://{$displayLocalHost}:{$port}";
        $networkUrl = ($host === '0.0.0.0' && $lanIp !== '127.0.0.1') ? "http://{$lanIp}:{$port}" : null;
        $adminLocalUrl = "http://{$displayLocalHost}:{$port}/sppadmin/";
        $adminNetworkUrl = $networkUrl ? "{$networkUrl}/sppadmin/" : null;

        echo "\n\033[32m=================================================================\033[0m\n";
        echo "\033[32m       SPP Sovereign Web Server Active (Zero Apache Required)     \033[0m\n";
        echo "\033[32m=================================================================\033[0m\n";
        echo " Application Context:  \033[36m{$activeContext}\033[0m\n";
        echo " Binding Address:      \033[33m{$host}:{$port}\033[0m\n";
        echo " Local Web URL:        \033[34m{$localUrl}\033[0m\n";
        if ($networkUrl) {
            echo " Network Web URL:      \033[34m{$networkUrl}\033[0m (Accessible from LAN / Mobile)\n";
        }
        echo " Admin Workbench:      \033[35m{$adminLocalUrl}\033[0m\n";
        if ($adminNetworkUrl) {
            echo " Network Admin URL:    \033[35m{$adminNetworkUrl}\033[0m\n";
        }
        echo " Document Root:        \033[37m{$root}\033[0m\n";
        echo " Sovereign Router:     \033[37mspp/router.php\033[0m\n";
        echo "-----------------------------------------------------------------\n";
        echo " Press \033[31mCtrl+C\033[0m to terminate the server.\n\n";

        // Launch PHP built-in web server with sovereign router
        $cmd = sprintf(
            'php -S %s:%d -t %s %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($root),
            escapeshellarg($router)
        );

        passthru($cmd);
    }
}
