<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;
class ProfileReportGenerateCommand extends Command {
    protected string $name = 'profile:report:generate';
    protected string $description = 'Dump a performance profile trace for debugging';
    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void {
        echo "Generating performance profile trace...\n";
        $dir = SPP_BASE_DIR . '/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . '/profile_' . time() . '.json';
        file_put_contents($file, json_encode(['status' => 'ok', 'trace' => []]));
        echo "Report generated at: {$file}\n";
    }
}
