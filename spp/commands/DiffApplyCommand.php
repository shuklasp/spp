<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DiffApplyCommand extends Command
{
    protected string $name = 'diff:apply';
    protected string $description = 'Apply a patch or delta file';

    public function execute(array $args): void
    {
        echo "Applying delta patch...\n";
        if (class_exists('\\SPPMod\\SPPDiff\\DeltaEngine')) {
            echo "DeltaEngine is available.\n";
            echo "Usage: diff:apply --file=patch.json\n";
        } else {
            echo "SPPDiff module not active.\n";
        }
    }
}
