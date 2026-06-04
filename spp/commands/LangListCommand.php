<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class LangListCommand extends Command
{
    protected string $name = 'lang:list';
    protected string $description = 'List all translations';

    public function execute(array $args): void
    {
        $appname = 'default';
        $locale = null;
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
                
                $filters = [];
                if ($locale) $filters['locale'] = $locale;
                
                $translations = \SPPMod\SPPLang\SPPLang::getTranslations($filters);
                
                if (empty($translations)) {
                    echo "No translations found.\n";
                    return;
                }
                
                echo str_pad("Key Code", 30) . str_pad("Locale", 10) . "Translation\n";
                echo str_repeat("-", 80) . "\n";
                
                foreach ($translations as $t) {
                    $key = $t['key_code'] ?? 'N/A';
                    $loc = $t['locale'] ?? 'N/A';
                    $trans = $t['translation'] ?? '';
                    
                    if (strlen($trans) > 35) $trans = substr($trans, 0, 35) . '...';
                    
                    echo str_pad($key, 30) . str_pad($loc, 10) . "{$trans}\n";
                }
            } catch (\Exception $e) {
                echo "Error listing translations: " . $e->getMessage() . "\n";
            }
        });
    }
}
