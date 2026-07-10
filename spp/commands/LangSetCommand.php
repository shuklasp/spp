<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class LangSetCommand extends Command
{
    protected string $name = 'lang:set';
    protected string $description = 'Set a translation for a key';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'default';
        $key = null;
        $locale = null;
        $translation = null;
        
        foreach ($args as $arg) {
            if ($arg === 'spp.php' || $arg === $this->name || str_starts_with($arg, '--')) continue;
            if (!$key) $key = $arg;
            elseif (!$locale) $locale = $arg;
            elseif (!$translation) $translation = $arg;
        }
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        if (!$key || !$locale || !$translation) {
            echo "Usage: php spp.php lang:set <key> <locale> <translation>\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($key, $locale, $translation) {
            try {
                \SPP\Module::loadModule('spplang');
                if (!class_exists('\\SPPMod\\SPPLang\\SPPLang')) {
                    echo "SPPLang module is not installed or available.\n";
                    return;
                }
                
                \SPPMod\SPPLang\SPPLang::saveTranslation($key, $locale, $translation, 'active');
                echo "Translation saved successfully.\n";
            } catch (\Exception $e) {
                echo "Error saving translation: " . $e->getMessage() . "\n";
            }
        });
    }
}
