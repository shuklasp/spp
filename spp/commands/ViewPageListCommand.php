<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewPageListCommand extends Command
{
    protected string $name = 'view:page:list';
    protected string $description = 'List all registered pages/routes for an app';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        echo "Listing pages for app: {$appname}\n\n";

        $pages = \SPP\Scheduler::withContext($appname, function() {
            return \SPPMod\SPPView\Pages::listPages();
        });

        if (empty($pages)) {
            echo "No pages found for '{$appname}'.\n";
            return;
        }

        echo str_pad("Route Name", 25) . str_pad("URL Target", 40) . "Source\n";
        echo str_repeat("-", 85) . "\n";

        foreach ($pages as $url => $p) {
            $source = $p['source'] === 'db' ? ($p['db_summary'] ?? 'Database') : ($p['source_path'] ?? 'pages.yml');
            $target = $p['controller'] ?? $p['url'] ?? $p['script'] ?? 'Unknown';
            echo str_pad($url, 25) . str_pad($target, 40) . $source . "\n";
        }
        echo "\n";
    }
}
