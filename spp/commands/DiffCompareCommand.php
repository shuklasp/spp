<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DiffCompareCommand extends Command
{
    protected string $name = 'diff:compare';
    protected string $description = 'Compare two JSON arrays or states';

    public function execute(array $args): void
    {
        echo "Comparing states using DeltaEngine...\n";
        if (class_exists('\\SPPMod\\SPPDiff\\DeltaEngine')) {
            echo "DeltaEngine is available.\n";
            echo "Usage: This command currently requires custom integration to compare specific JSON files.\n";
        } else {
            echo "SPPDiff module not active.\n";
        }
    }
}
