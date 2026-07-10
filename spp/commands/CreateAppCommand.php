<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class CreateAppCommand
 * Legacy proxy class kept for backward compatibility with make:app-legacy.
 * Directly forwards execution to the all-encompassing MakeAppCommand.
 */
class CreateAppCommand extends Command
{
    public function execute(array $args): void
    {
        // Add or ensure legacy mode is set for MakeAppCommand
        $hasType = isset($args[3]) && !str_starts_with($args[3], '--');
        if (!$hasType) {
            // Insert 'legacy' as the app type argument (arg index 3)
            $newArgs = [];
            foreach ($args as $idx => $val) {
                if ($idx === 3) {
                    $newArgs[] = 'legacy';
                }
                $newArgs[] = $val;
            }
            if (!isset($args[3])) {
                $newArgs[3] = 'legacy';
            }
            $args = $newArgs;
        }

        $makeApp = new MakeAppCommand();
        $makeApp->execute($args);
    }

    public function getName(): string
    {
        return 'make:app-legacy';
    }

    public function getDescription(): string
    {
        return 'Legacy scaffolder — forwards to make:app (kept for backward compatibility)';
    }
}
