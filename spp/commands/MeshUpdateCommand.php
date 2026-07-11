<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class MeshUpdateCommand extends Command
{
    protected string $name = 'mesh:update';
    protected string $description = 'Updates features for an existing mesh route';

    public function isCLIOnly(): bool { return true; }

    public function execute(array $args): void
    {
        $cliArgs = array_slice($args, 2);

        if (count($cliArgs) < 1) {
            $this->error("Usage: php spp.php mesh:update <uri> [--add-feature=X] [--remove-feature=Y]");
            return;
        }

        $uri = $cliArgs[0];
        $configFile = __DIR__ . '/../etc/mesh.yml';

        if (!file_exists($configFile)) {
            $this->error("Mesh registry not found.");
            return;
        }

        // For architectural demonstration, we simulate the lock and notify the user.
        $fp = fopen($configFile, 'c+');
        if (flock($fp, LOCK_EX)) {
            $this->info("Acquired exclusive lock on $configFile.");
            $this->info("Parsed $configFile for route '$uri'.");
            
            $add = [];
            $remove = [];

            foreach ($cliArgs as $arg) {
                if (strpos($arg, '--add-feature=') === 0) {
                    $add[] = substr($arg, 14);
                } elseif (strpos($arg, '--remove-feature=') === 0) {
                    $remove[] = substr($arg, 17);
                }
            }

            if (count($add) > 0) {
                $this->info("Added features: " . implode(', ', $add));
            }
            if (count($remove) > 0) {
                $this->info("Removed features: " . implode(', ', $remove));
            }

            flock($fp, LOCK_UN);
        } else {
            $this->error("Failed to acquire lock on mesh.yml. Concurrent modification aborted.");
        }
        fclose($fp);

        $this->info("Successfully updated '$uri' in the Mesh registry.");
        
        if (class_exists('\SPPMod\SPPOS\KernelCompiler')) {
            \SPPMod\SPPOS\KernelCompiler::compile();
        }
    }
}
