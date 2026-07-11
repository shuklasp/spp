<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class MeshAddCommand extends Command
{
    protected string $name = 'mesh:add';
    protected string $description = 'Mounts a legacy application as a passthrough route in the WebOS Mesh';

    public function isCLIOnly(): bool { return true; }

    public function execute(array $args): void
    {
        $cliArgs = array_slice($args, 2);
        
        if (count($cliArgs) < 2) {
            $this->error("Usage: php spp.php mesh:add <uri> <target> [--integration=partial] [--features=sso_auth,ui_mesh]");
            return;
        }

        $uri = $cliArgs[0];
        $target = $cliArgs[1];

        $integration = 'none';
        $features = [];

        foreach ($args as $arg) {
            if (strpos($arg, '--integration=') === 0) {
                $integration = substr($arg, 14);
            } elseif (strpos($arg, '--features=') === 0) {
                $features = explode(',', substr($arg, 11));
            }
        }

        $configFile = __DIR__ . '/../etc/mesh.yml';
        if (!file_exists(dirname($configFile))) {
            @mkdir(dirname($configFile), 0755, true);
        }

        $yaml = "  {$uri}:\n    target: {$target}\n    integration: {$integration}\n";
        if (!empty($features)) {
            $yaml .= "    features:\n";
            foreach ($features as $f) {
                $yaml .= "      - " . trim($f) . "\n";
            }
        }

        $fp = fopen($configFile, 'a+');
        if (flock($fp, LOCK_EX)) {
            // Read to see if file is empty
            fseek($fp, 0, SEEK_END);
            if (ftell($fp) === 0) {
                fwrite($fp, "mesh_routes:\n");
            }
            fwrite($fp, $yaml);
            flock($fp, LOCK_UN);
        } else {
            $this->error("Failed to acquire lock on mesh.yml. Concurrent modification aborted.");
        }
        fclose($fp);
        
        $this->info("Successfully mounted '$uri' to '$target' in the Mesh registry.");
        $this->info("Triggering FastCGI cache compilation...");
        
        if (class_exists('\SPPMod\SPPOS\KernelCompiler')) {
            \SPPMod\SPPOS\KernelCompiler::compile();
        }
    }
}
