<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DiffHistoryCommand extends Command
{
    protected string $name = 'diff:history';
    protected string $description = 'View revision history of an entity';

    public function execute(array $args): void
    {
        $entityType = null;
        $entityId = null;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--type=')) $entityType = substr($arg, 7);
            elseif (str_starts_with($arg, '--id=')) $entityId = substr($arg, 5);
        }

        if (!$entityType || !$entityId) {
            echo "Usage: php spp.php diff:history --type=<ModelClass> --id=<ID>\n";
            return;
        }

        try {
            if (!class_exists($entityType)) {
                echo "Entity class $entityType not found.\n";
                return;
            }
            
            $entity = clone (new $entityType());
            $entity = clone $entityType::find_one(['id' => $entityId]);
            if (!$entity || empty($entity->id)) {
                echo "Entity not found.\n";
                return;
            }
            
            if (class_exists('\\SPPMod\\SPPDiff\\RevisionManager')) {
                $history = \SPPMod\SPPDiff\RevisionManager::getHistory($entity);
                if (empty($history)) {
                    echo "No revision history found.\n";
                    return;
                }
                echo "Revision history for $entityType ($entityId):\n";
                foreach ($history as $h) {
                    echo "[Rev {$h['id']}] Date: {$h['created_at']} | User: {$h['user_id']}\n";
                }
            } else {
                echo "SPPDiff module not active.\n";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
