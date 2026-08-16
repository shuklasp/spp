<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewPageAddCommand extends Command
{
    protected string $name = 'view:page:add';
    protected string $description = 'Add a new page route to an app';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'default';
        $name = null;
        $url = null;
        $source = 'yaml';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) $appname = substr($arg, 6);
            elseif (str_starts_with($arg, '--name=')) $name = substr($arg, 7);
            elseif (str_starts_with($arg, '--url=')) $url = substr($arg, 6);
            elseif (str_starts_with($arg, '--source=')) $source = substr($arg, 9);
        }

        if (!$name || !$url) {
            echo "Usage: php spp.php view:page:add --name=<route> --url=<target> [--app=default] [--source=yaml|db]\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($name, $url, $source) {
            \SPPMod\SPPView\Pages::savePage($name, $url, $source);
        });

        echo "Success: Page route '{$name}' -> '{$url}' saved for app '{$appname}' (Source: {$source}).\n";
    }
}
