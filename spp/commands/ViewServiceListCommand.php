<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewServiceListCommand extends Command
{
    protected string $name = 'view:service:list';
    protected string $description = 'List all registered AJAX services for an app';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        echo "Listing services for app: {$appname}\n\n";

        $services = \SPP\Scheduler::withContext($appname, function() {
            return \SPPMod\SppApi\SPPAjax::listServices();
        });

        if (empty($services)) {
            echo "No services found for '{$appname}'.\n";
            return;
        }

        echo str_pad("Service Name", 30) . str_pad("Method", 10) . str_pad("Script", 30) . "Source\n";
        echo str_repeat("-", 85) . "\n";

        foreach ($services as $s) {
            $source = $s['source'] === 'db' ? ($s['db_summary'] ?? 'Database') : ($s['source_path'] ?? 'services.yml');
            echo str_pad($s['name'], 30) . str_pad($s['method'] ?? 'POST', 10) . str_pad($s['script'], 30) . $source . "\n";
        }
        echo "\n";
    }
}
