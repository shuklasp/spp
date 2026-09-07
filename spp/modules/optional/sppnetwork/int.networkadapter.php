<?php
namespace SPPMod\SPPNetwork;

/**
 * Interface NetworkAdapterInterface
 * 
 * Defines the contract for all Network Adapters (Swoole, Workerman, FastCGI)
 * to abstract the underlying OS web server from the SPP Kernel.
 */
interface NetworkAdapterInterface
{
    /**
     * Boot the server and start listening on the given host/port.
     */
    public function start(string $host, int $port);

    /**
     * Process an incoming HTTP request via the SPP Router.
     */
    public function handleRequest($request, $response);
}
