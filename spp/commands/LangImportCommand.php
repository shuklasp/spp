<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class LangImportCommand extends Command
{
    protected string $name = 'lang:import';
    protected string $description = 'Import JSON language file into active database translation overrides';

    public function isCLIOnly(): bool
    {
        return true;
    }

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

        \SPP\Scheduler::withContext($appname, function() use ($appname, $locale) {
            try {
                \SPP\Module::loadModule('spplang');
                if (!class_exists('\\SPPMod\\SPPLang\\SPPLang')) {
                    echo "SPPLang module is not installed or available.\n";
                    return;
                }

                echo "Importing translations for locale '{$locale}' (App: {$appname})...\n";

                $candidates = [
                    SPP_APP_DIR . "/src/{$appname}/translations/{$locale}.json",
                    SPP_APP_DIR . "/src/{$appname}/resources/translations/{$locale}.json",
                ];

                $targetFile = null;
                foreach ($candidates as $cand) {
                    if (file_exists($cand)) {
                        $targetFile = $cand;
                        break;
                    }
                }

                if (!$targetFile) {
                    echo "Translation file not found for locale '{$locale}' in App '{$appname}'.\n";
                    return;
                }

                $content = file_get_contents($targetFile);
                if ($content === false) {
                    echo "Failed to read translation file at {$targetFile}\n";
                    return;
                }

                $dict = json_decode($content, true);
                if (!is_array($dict)) {
                    echo "Invalid JSON format in translation file {$targetFile}\n";
                    return;
                }

                $count = 0;
                foreach ($dict as $key => $val) {
                    if (is_string($key) && is_string($val)) {
                        \SPPMod\SPPLang\SPPLang::saveTranslation($key, $locale, $val, 'active');
                        $count++;
                    }
                }

                echo "Successfully imported {$count} translation keys from {$targetFile}\n";
            } catch (\Exception $e) {
                echo "Error importing translations: " . $e->getMessage() . "\n";
            }
        });
    }
}
