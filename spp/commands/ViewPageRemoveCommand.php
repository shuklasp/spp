<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewPageRemoveCommand extends Command
{
    protected string $name = 'view:page:remove';
    protected string $description = 'Remove a page route from an app';

    public function execute(array $args): void
    {
        $appname = 'default';
        $name = null;
        $source = 'yaml';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) $appname = substr($arg, 6);
            elseif (str_starts_with($arg, '--name=')) $name = substr($arg, 7);
            elseif (str_starts_with($arg, '--source=')) $source = substr($arg, 9);
        }

        if (!$name) {
            echo "Usage: php spp.php view:page:remove --name=<route> [--app=default] [--source=yaml|db]\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($name, $source) {
            \SPPMod\SPPView\Pages::removePage($name, $source);
        });

        echo "Success: Page route '{$name}' removed for app '{$appname}' (Source: {$source}).\n";
    }
}
