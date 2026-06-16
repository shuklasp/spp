<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ViewServiceRemoveCommand extends Command
{
    protected string $name = 'view:service:remove';
    protected string $description = 'Remove an AJAX service endpoint from an app';

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
            echo "Usage: php spp.php view:service:remove --name=<service> [--app=default] [--source=yaml|db]\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($name, $source) {
            \SPPMod\SppApi\SPPAjax::unregisterService($name, $source);
        });

        echo "Success: AJAX Service '{$name}' removed for app '{$appname}' (Source: {$source}).\n";
    }
}
