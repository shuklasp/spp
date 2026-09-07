<?php
namespace SPPMod\SPPNetwork;

/**
 * Class SwooleAdapter
 * 
 * Enterprise C-Extension Adapter.
 * Leverages Swoole coroutines for maximum async performance.
 */
class SwooleAdapter implements NetworkAdapterInterface
{
    public function start(string $host, int $port)
    {
        if (!extension_loaded('swoole')) {
            throw new \Exception("WebOS Kernel Panic: Swoole extension is not loaded. Cannot boot in Enterprise Mode.");
        }

        $server = new \Swoole\Http\Server($host, $port);
        
        $server->on('request', function ($request, $response) {
            // Guarantee fresh context (Prevent Cross-Tenant State Bleeding)
            if (class_exists('\SPPMod\SPPNetwork\ContextManager')) {
                \SPPMod\SPPNetwork\ContextManager::resetContext();
            }
            
            $this->handleRequest($request, $response);
        });
        
        echo "SPP WebOS (Swoole C-Extension Runtime) started on $host:$port\n";
        $server->start();
    }

    public function handleRequest($request, $response)
    {
        // Translate Swoole request to SPP request, pass to SPP Router
        $response->end("Hello from SPP WebOS (Powered by Swoole)");
    }
}
