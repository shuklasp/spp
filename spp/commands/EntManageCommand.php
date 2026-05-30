<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EntManageCommand extends Command
{
    public function execute(array $args): void
    {
        echo "Error: Command 'ent:manage' is partially ported and needs manual intervention.\n";
    }

    public function getName(): string
    {
        return 'ent:manage';
    }

    public function getDescription(): string
    {
        return 'Legacy port of ent:manage';
    }
}
