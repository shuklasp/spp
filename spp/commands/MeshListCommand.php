<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class MeshListCommand extends Command
{
    protected string $name = 'mesh:list';
    protected string $description = 'Lists all active Mesh passthrough routes';

    public function isCLIOnly(): bool { return true; }

    public function execute(array $args): void
    {
        $configFile = __DIR__ . '/../etc/mesh.yml';

        $this->info("SPP WebOS Mesh Routes");
        $this->info("=====================");

        if (!file_exists($configFile)) {
            $this->info("No legacy applications mounted. Mesh registry is empty.");
            return;
        }

        $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $currentRoute = null;

        foreach ($lines as $line) {
            // Remove comments
            $line = preg_replace('/#.*$/', '', $line);
            if (trim($line) === '') continue;

            if (preg_match('/^\s{2}([^\s:]+):/', $line, $matches)) {
                $currentRoute = $matches[1];
                $this->info("\nRoute: $currentRoute");
            } elseif ($currentRoute && preg_match('/^\s{4}target:\s*(.+)$/', $line, $matches)) {
                $this->info("  -> Target: " . trim($matches[1]));
            } elseif ($currentRoute && preg_match('/^\s{4}integration:\s*(.+)$/', $line, $matches)) {
                $this->info("  -> Integration: " . trim($matches[1]));
            } elseif ($currentRoute && preg_match('/^\s{6}-\s*(.+)$/', $line, $matches)) {
                $this->info("     - Feature: " . trim($matches[1]));
            }
        }
        $this->info("");
    }
}
