<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DiListCommand extends Command
{
    protected string $name = 'di:list';
    protected string $description = 'List the Dependency Injection container bindings';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        echo "DI Container Bindings for app: {$appname}\n\n";

        \SPP\Scheduler::withContext($appname, function() {
            // Get the container
            $app = \SPP\App::getApp();
            if (!$app) {
                echo "App not initialized.\n";
                return;
            }
            $container = $app->getContainer();

            // Use reflection to access bindings and instances
            $reflector = new \ReflectionClass($container);
            
            $bindingsProperty = $reflector->getProperty('bindings');
            $bindingsProperty->setAccessible(true);
            $bindings = $bindingsProperty->getValue($container);

            $instancesProperty = $reflector->getProperty('instances');
            $instancesProperty->setAccessible(true);
            $instances = $instancesProperty->getValue($container);

            if (empty($bindings) && empty($instances)) {
                echo "No services registered in DI container.\n";
                return;
            }

            echo str_pad("Abstract / ID", 40) . str_pad("Type", 15) . "Concrete / Status\n";
            echo str_repeat("-", 85) . "\n";

            // List Bindings
            foreach ($bindings as $abstract => $binding) {
                $type = $binding['shared'] ? 'Singleton' : 'Factory';
                
                $concreteStr = 'Closure';
                if (is_string($binding['concrete'])) {
                    $concreteStr = $binding['concrete'];
                } elseif (is_object($binding['concrete']) && !($binding['concrete'] instanceof \Closure)) {
                    $concreteStr = get_class($binding['concrete']);
                }

                echo str_pad($abstract, 40) . str_pad($type, 15) . $concreteStr . "\n";
            }

            // List Instances (that might not be in bindings if registered directly, though Container code stores in instances when get() is called on singletons)
            foreach ($instances as $id => $instance) {
                if (!isset($bindings[$id])) {
                    $type = 'Instance';
                    $concreteStr = is_object($instance) ? get_class($instance) : gettype($instance);
                    echo str_pad($id, 40) . str_pad($type, 15) . $concreteStr . " (Resolved)\n";
                }
            }
            
            echo "\n";
        });
    }
}
