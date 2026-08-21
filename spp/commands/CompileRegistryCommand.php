<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class CompileRegistryCommand extends Command
{
    protected string $name = 'cache:compile-registry';
    protected string $description = 'Rebuilds the Orion Cache and System Registry';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $isJson = $this->hasFlag($args, 'json');
        try {
            if (class_exists('\\SPP\\Registry')) {
                \SPP\Registry::forceSyncShared();
            }
            if (class_exists('\\SPP\\SPPEvent') && class_exists('\\SPP\\EventParams')) {
                \SPP\SPPEvent::fireEvent('spp_registry_compiled', new \SPP\EventParams([]));
            }
            if ($isJson) {
                echo json_encode(['success' => true, 'message' => 'System Registry Compiled successfully.']);
            } else {
                echo "System Registry Compiled successfully.\n";
            }
        } catch (\Exception $e) {
            if ($isJson) {
                echo json_encode(['success' => false, 'message' => "Compile failed: " . $e->getMessage()]);
            } else {
                echo "Compile failed: " . $e->getMessage() . "\n";
            }
        }
    }
}
