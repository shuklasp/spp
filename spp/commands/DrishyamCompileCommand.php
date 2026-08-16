<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DrishyamCompileCommand extends Command
{
    protected string $name = 'drishyam:compile';
    protected string $description = 'Pre-compile Drishyam templates for production';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "Compiling Drishyam templates...\n";
        
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() {
            if (class_exists('\\SPPMod\\Drishyam\\Drishyam')) {
                $d = \SPPMod\Drishyam\Drishyam::getInstance();
                $d->boot();
                $d->preWarm();
                echo "Drishyam templates successfully pre-compiled and warmed in memory.\n";
            } else {
                echo "Drishyam module is not installed or enabled.\n";
            }
        });
    }
}
