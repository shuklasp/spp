<?php
namespace SPPMod\SPPNetwork;

/**
 * Class FastCgiAdapter
 * 
 * Shared Hosting Fallback Adapter.
 * Hands control over to Apache/Nginx via standard PHP-FPM.
 */
class FastCgiAdapter implements NetworkAdapterInterface
{
    public function start(string $host, int $port)
    {
        // On FastCGI, there is no "start" loop because Apache/Nginx handles the loop.
        // We simply process the current global request immediately.
        $this->handleRequest($_REQUEST, null);
    }

    public function handleRequest($request, $response)
    {
        // ELIMINATE OVERHEAD: Load the pre-compiled OS kernel registry instantly
        $kernelConfig = \SPPMod\SPPOS\KernelCompiler::loadFast();
        
        // Standard SPP Router bootstrap execution using fast config
        echo "Hello from SPP WebOS (Powered by FastCGI / Apache with Pre-Compiled Kernel)";
    }
}
