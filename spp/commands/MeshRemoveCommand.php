<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class MeshRemoveCommand extends Command
{
    protected string $name = 'mesh:remove';
    protected string $description = 'Unmounts a legacy application from the WebOS Mesh';

    public function isCLIOnly(): bool { return true; }

    public function execute(array $args): void
    {
        $cliArgs = array_slice($args, 2);
        
        if (count($cliArgs) < 1) {
            $this->error("Usage: php spp.php mesh:remove <uri>");
            return;
        }

        $uri = $cliArgs[0];
        $configFile = __DIR__ . '/../etc/mesh.yml';

        if (!file_exists($configFile)) {
            $this->error("Mesh registry not found.");
            return;
        }

        $fp = fopen($configFile, 'c+');
        if (flock($fp, LOCK_EX)) {
            $size = filesize($configFile);
            $content = $size > 0 ? fread($fp, $size) : '';
            $lines = explode("\n", $content);
            $out = [];
            $skipping = false;

            foreach ($lines as $line) {
                if (preg_match('/^\s{2}' . preg_quote(trim($uri, '/'), '/') . ':/', $line) || 
                    preg_match('/^\s{2}' . preg_quote($uri, '/') . ':/', $line)) {
                    $skipping = true;
                    continue;
                }
                if ($skipping && preg_match('/^\s{2}[^\s]+:/', $line)) {
                    $skipping = false;
                }
                if (!$skipping) {
                    $out[] = $line;
                }
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, implode("\n", $out));
            flock($fp, LOCK_UN);
        } else {
            $this->error("Failed to acquire lock on mesh.yml. Concurrent modification aborted.");
        }
        fclose($fp);
        $this->info("Successfully unmounted '$uri' from the Mesh registry.");
        
        if (class_exists('\SPPMod\SPPOS\KernelCompiler')) {
            \SPPMod\SPPOS\KernelCompiler::compile();
        }
    }
}
