<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;

class PolyglotListCommand extends Command {
    protected string $name = 'polyglot:list';
    protected string $description = 'Discovers and tabulates all registered polyglot services';
    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void {
        echo "Discovering polyglot services...\n";
        
        $searchDirs = [
            SPP_BASE_DIR . '/services/',
            SPP_BASE_DIR . '/src/services/',
            SPP_APP_DIR . '/services/'
        ];

        $found = [];
        foreach ($searchDirs as $dir) {
            if (is_dir($dir)) {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
                foreach ($files as $file) {
                    if ($file->isFile()) {
                        $ext = strtolower($file->getExtension());
                        if (in_array($ext, ['py', 'go', 'js', 'rs', 'rb', 'sh', 'cpp', 'cs', 'java', 'pl'])) {
                            $normalizedPath = str_replace('\\', '/', $file->getPathname());
                            if (strpos($normalizedPath, '/obj/') === false && strpos($normalizedPath, '/bin/') === false) {
                                $found[] = [
                                    'Language' => strtoupper($ext),
                                    'Path' => str_replace(str_replace('\\', '/', SPP_BASE_DIR), '', $normalizedPath)
                                ];
                            }
                        }
                    }
                }
            }
        }

        if (empty($found)) {
            echo "No polyglot services found in standard directories.\n";
            return;
        }

        echo "+----------+--------------------------------------------------+\n";
        echo "| Language | Path                                             |\n";
        echo "+----------+--------------------------------------------------+\n";
        foreach ($found as $service) {
            echo "| " . str_pad($service['Language'], 8) . " | " . str_pad($service['Path'], 48) . " |\n";
        }
        echo "+----------+--------------------------------------------------+\n";
    }
}
