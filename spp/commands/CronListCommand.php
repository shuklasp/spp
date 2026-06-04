<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class CronListCommand extends Command
{
    protected string $name = 'cron:list';
    protected string $description = 'List all registered scheduled tasks';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() use ($appname) {
            echo "Cron tasks registered in app context: {$appname}\n";
            echo str_repeat('-', 50) . "\n";
            
            if (!class_exists('\\SPP\\Cron\\Scheduler')) {
                echo "Cron Scheduler not available.\n";
                return;
            }
            
            try {
                $refClass = new \ReflectionClass('\\SPP\\Cron\\Scheduler');
                $refProp = $refClass->getProperty('tasks');
                $refProp->setAccessible(true);
                $tasks = $refProp->getValue();
                
                if (empty($tasks)) {
                    echo "No cron tasks registered.\n";
                    return;
                }
                
                foreach ($tasks as $i => $task) {
                    echo "[" . ($i+1) . "] Expression: " . $task['expression'] . "\n";
                }
            } catch (\Exception $e) {
                echo "Could not fetch cron tasks: " . $e->getMessage() . "\n";
            }
        });
    }
}
