<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AuthGroupEditCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        $slug = $argv[2] ?? null;
                if (!$slug) die("Usage: php spp.php auth:group:edit <slug>\n");
                require_once SPP_APP_DIR . '/spp/sppinit.php';
                try {
                    $group = new \SPPMod\SPPGroup\SPPGroup();
                    $group->load($slug);
                    if (!$group->id) throw new \Exception("Group not found.");
                    
                    echo "Editing Group: {$group->id}\n";
                    $name = prompt("Name", $group->get('name'));
                    $desc = prompt("Description", $group->get('description'));
        
                    \SPPMod\SPPGroup\SPPGroup::saveGroupInfo([
                        'slug' => $group->id,
                        'name' => $name,
                        'description' => $desc
                    ]);
                    echo "\nSuccess: Group updated.\n";
                } catch (\Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'auth:group:edit';
    }

    public function getDescription(): string
    {
        return 'Legacy port of auth:group:edit';
    }
}
