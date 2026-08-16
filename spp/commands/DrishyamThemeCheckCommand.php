<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DrishyamThemeCheckCommand extends Command
{
    protected string $name = 'drishyam:theme:check';
    protected string $description = 'Validate Drishyam theme assets and structure';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() {
            if (!class_exists('\\SPPMod\\Drishyam\\Drishyam')) {
                echo "Drishyam module is not available.\n";
                return;
            }
            
            $d = \SPPMod\Drishyam\Drishyam::getInstance();
            $d->boot();
            $themes = $d->getThemes();
            
            echo "Found " . count($themes) . " theme(s):\n";
            echo str_repeat('-', 40) . "\n";
            
            foreach ($themes as $name => $theme) {
                echo "Theme: {$name}\n";
                echo "  Path: " . $theme->getPath() . "\n";
                $hasConfig = file_exists($theme->getPath() . '/theme.yml') || file_exists($theme->getPath() . '/style.css') || glob($theme->getPath() . '/*.info.yml');
                echo "  Config valid: " . ($hasConfig ? "Yes" : "No") . "\n";
                $viewsDir = rtrim($theme->getPath(), '/\\') . '/views';
                echo "  Views directory: " . (is_dir($viewsDir) ? "Found" : "Missing") . "\n";
                echo "\n";
            }
            
            echo "Theme validation complete.\n";
        });
    }
}
