<?php
namespace SPPMod\SPPNetwork;

/**
 * Class WorkermanAdapter
 * 
 * Pure PHP Async Server Adapter.
 * Uses native stream_select and pcntl for multi-processing.
 */
class WorkermanAdapter implements NetworkAdapterInterface
{
    public function start(string $host, int $port)
    {
        if (!class_exists('\Workerman\Worker')) {
            throw new \Exception("WebOS Kernel Panic: Workerman is not installed. Cannot boot pure PHP async runtime.");
        }

        $worker = new \Workerman\Worker("http://$host:$port");
        
        $worker->onMessage = function($connection, $request) {
            $this->handleRequest($request, $connection);
        };
        
        echo "SPP WebOS (Workerman Pure-PHP Async Runtime) started on $host:$port\n";
        \Workerman\Worker::runAll();
    }

    public function handleRequest($request, $response)
    {
        // Translate Workerman request to SPP request, pass to SPP Router
        $response->send("Hello from SPP WebOS (Powered by Workerman)");
    }
}
