<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class ScheduleRunCommand
 * Gathers and executes scheduled tasks from all active modules.
 */
class ScheduleRunCommand extends Command
{
    public function getName(): string
    {
        return 'schedule:run';
    }

    public function getDescription(): string
    {
        return 'Run all scheduled cron tasks declared by active modules';
    }

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "Starting SPP Scheduler...\n";

        \SPP\Module::loadAllModules();
        $modules = \SPP\Registry::get('__modobj');

        if (!is_array($modules) || empty($modules)) {
            echo "No modules registered.\n";
            return;
        }

        $scheduler = new \SPP\Cron\Scheduler();
        $registeredCount = 0;

        foreach ($modules as $modName => $modObj) {
            if (!$modObj instanceof \SPP\Module) {
                continue;
            }

            $provider = $modObj->ServiceProvider ?? null;
            if ($provider && method_exists($provider, 'schedule')) {
                // Modules use this hook to call $scheduler->call(...)
                $provider->schedule($scheduler);
                $registeredCount++;
            }
        }

        echo "Gathered scheduled tasks from {$registeredCount} modules.\n";
        
        // Let the scheduler run the stack
        \SPP\Cron\Scheduler::run();
        
        echo "Scheduler execution completed.\n";
    }
}
