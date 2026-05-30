<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthGroupCreateCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        require_once SPP_APP_DIR . '/spp/sppinit.php';
                $name = prompt("Group Name");
                if (!$name) die("Name required.\n");
                $desc = prompt("Description");
                echo "\nChoose Storage Source:\n";
                echo "  [1] database (Shared across instances)\n";
                echo "  [2] global   (YAML in framework etc)\n";
                echo "  [3] app      (YAML in app etc)\n";
                $srcIdx = (int)prompt("Selection", "1");
                $source = ($srcIdx === 2) ? 'global' : (($srcIdx === 3) ? 'app' : 'database');
        
                try {
                    $id = \SPPMod\SPPGroup\SPPGroup::saveGroupInfo([
                        'name' => $name,
                        'description' => $desc,
                        'source' => $source
                    ]);
                    echo "\nSuccess: Group created with ID/Slug: {$id}\n";
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:group:create';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:group:create';
    }
}
