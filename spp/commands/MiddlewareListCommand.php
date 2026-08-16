<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\MiddlewareKernel;

class MiddlewareListCommand extends Command
{
    protected string $name = 'middleware:list';
    protected string $description = 'List the middleware pipeline for an app';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        echo "Middleware Pipeline for app: {$appname}\n\n";

        \SPP\Scheduler::withContext($appname, function() {
            // Boot the kernel to initialize the middleware array
            MiddlewareKernel::boot();

            // Extract the protected static property using Reflection
            $reflector = new \ReflectionClass(MiddlewareKernel::class);
            $property = $reflector->getProperty('middleware');
            $property->setAccessible(true);
            $middleware = $property->getValue();

            if (empty($middleware)) {
                echo "No middleware registered.\n";
                return;
            }

            echo str_pad("Order", 10) . "Middleware Class\n";
            echo str_repeat("-", 60) . "\n";

            $order = 1;
            foreach ($middleware as $mw) {
                echo str_pad($order++, 10) . $mw . "\n";
            }
            echo "\n";
        });
    }
}
