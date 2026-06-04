<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AiProvidersCommand extends Command
{
    protected string $name = 'ai:providers';
    protected string $description = 'List all registered AI providers';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() {
            try {
                \SPP\Module::loadModule('sppai');
                if (!class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
                    echo "SPPAI module is not installed or available.\n";
                    return;
                }
                
                $registry = \SPPMod\SPPAI\SPPAI::getRegistry();
                
                if (empty($registry)) {
                    echo "No AI providers registered.\n";
                    return;
                }
                
                echo "Registered AI Providers:\n";
                echo str_repeat("-", 60) . "\n";
                echo str_pad("Provider", 20) . str_pad("Model", 20) . "Status\n";
                echo str_repeat("-", 60) . "\n";
                
                foreach ($registry as $provider => $details) {
                    $model = $details['model'] ?? 'default';
                    $status = $details['active'] ?? false ? 'Active' : 'Inactive';
                    echo str_pad($provider, 20) . str_pad($model, 20) . "{$status}\n";
                }
            } catch (\Exception $e) {
                echo "Error listing AI providers: " . $e->getMessage() . "\n";
            }
        });
    }
}
