<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class LangExportCommand extends Command
{
    protected string $name = 'lang:export';
    protected string $description = 'Export active database translation overrides into JSON language file';

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

                echo "Exporting translations for locale '{$locale}' (App: {$appname})...\n";

                $translations = \SPPMod\SPPLang\SPPLang::getTranslations(['locale' => $locale, 'status' => 'active']);
                $dict = [];
                foreach ($translations as $t) {
                    $key = $t['key_code'] ?? '';
                    $trans = $t['translation'] ?? '';
                    if ($key !== '') {
                        $dict[$key] = $trans;
                    }
                }

                $targetDir = SPP_APP_DIR . "/src/{$appname}/translations";
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0777, true);
                }

                $targetFile = $targetDir . "/{$locale}.json";
                $jsonContent = json_encode($dict, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                if (file_put_contents($targetFile, $jsonContent) !== false) {
                    $count = count($dict);
                    echo "Successfully exported {$count} translation keys to {$targetFile}\n";
                } else {
                    echo "Failed to write translation file at {$targetFile}\n";
                }
            } catch (\Exception $e) {
                echo "Error exporting translations: " . $e->getMessage() . "\n";
            }
        });
    }
}
