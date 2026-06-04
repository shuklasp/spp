<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class LangScanCommand extends Command
{
    protected string $name = 'lang:scan';
    protected string $description = 'Scan directories for new translation keys';

    public function execute(array $args): void
    {
        $appname = 'default';
        $locale = 'en';
        foreach ($args as $arg) {
            if ($arg === 'spp.php' || $arg === $this->name || str_starts_with($arg, '--')) continue;
            $locale = $arg;
            break;
        }
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() use ($locale) {
            try {
                \SPP\Module::loadModule('spplang');
                if (!class_exists('\\SPPMod\\SPPLang\\SPPLang')) {
                    echo "SPPLang module is not installed or available.\n";
                    return;
                }
                
                $dir = dirname(SPP_BASE_DIR) . '/src';
                echo "Scanning directory: {$dir} for locale '{$locale}'...\n";
                
                $newlyAdded = \SPPMod\SPPLang\SPPLang::scanDirectory($dir, $locale);
                
                $count = count($newlyAdded);
                echo "Scan complete! Discovered {$count} new translation keys.\n";
                
                if ($count > 0) {
                    echo "New keys:\n";
                    foreach ($newlyAdded as $key) {
                        echo "  - {$key}\n";
                    }
                }
            } catch (\Exception $e) {
                echo "Error scanning translations: " . $e->getMessage() . "\n";
            }
        });
    }
}
