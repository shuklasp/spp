<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;

class PolyglotRunCommand extends Command {
    protected string $name = 'polyglot:run';
    protected string $description = 'Executes a specific polyglot service directly';
    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void {
        $servicePath = null;
        $serviceArgs = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--path=')) {
                $servicePath = substr($arg, 7);
            } elseif (!str_starts_with($arg, '--app=') && !in_array($arg, ['spp.php', 'polyglot:run'])) {
                $serviceArgs[] = escapeshellarg($arg);
            }
        }

        if (!$servicePath) {
            echo "Usage: php spp.php polyglot:run --path=<relative_path_to_service> [args...]\n";
            return;
        }

        $fullPath = SPP_BASE_DIR . '/' . ltrim($servicePath, '/');
        if (!file_exists($fullPath)) {
            echo "Error: Service not found at {$fullPath}\n";
            return;
        }

        $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
        $interpreters = [
            'py' => 'python',
            'js' => 'node',
            'rb' => 'ruby',
            'sh' => 'bash',
            'go' => 'go run'
        ];

        $interpreter = $interpreters[$ext] ?? null;
        if (!$interpreter) {
            echo "Error: Unknown interpreter for extension '.{$ext}'\n";
            return;
        }

        $cmd = escapeshellcmd("{$interpreter} " . escapeshellarg($fullPath)) . " " . implode(" ", $serviceArgs);
        echo "Executing: {$cmd}\n";
        echo "----------------------------------------\n";
        passthru($cmd, $returnVar);
        echo "----------------------------------------\n";
        echo "Service exited with code: {$returnVar}\n";
    }
}
