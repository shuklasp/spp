<?php
namespace SPPMod\SPPNetwork;

/**
 * Class NetworkServer
 * 
 * Auto-detects the hardware environment and boots the optimal WebOS server adapter.
 */
class NetworkServer
{
    public static function boot(string $host = '0.0.0.0', int $port = 80)
    {
        $adapter = self::detectOptimalAdapter();
        $adapter->start($host, $port);
    }

    private static function detectOptimalAdapter(): NetworkAdapterInterface
    {
        // 1. If not running in CLI, we MUST fallback to FastCGI (Shared Hosting Safe)
        if (php_sapi_name() !== 'cli') {
            return new FastCgiAdapter();
        }

        // 2. If CLI and Swoole is installed, use maximum C-Extension performance
        if (extension_loaded('swoole')) {
            return new SwooleAdapter();
        }

        // 3. Fallback to pure PHP async server
        return new WorkermanAdapter();
    }
}
