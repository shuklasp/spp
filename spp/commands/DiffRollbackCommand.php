<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DiffRollbackCommand extends Command
{
    protected string $name = 'diff:rollback';
    protected string $description = 'Rollback an entity to a previous state';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $entityType = null;
        $entityId = null;
        $revId = null;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--type=')) $entityType = substr($arg, 7);
            elseif (str_starts_with($arg, '--id=')) $entityId = substr($arg, 5);
            elseif (str_starts_with($arg, '--rev=')) $revId = (int) substr($arg, 6);
        }

        if (!$entityType || !$entityId || !$revId) {
            echo "Usage: php spp.php diff:rollback --type=<ModelClass> --id=<ID> --rev=<RevID>\n";
            return;
        }

        try {
            if (!class_exists($entityType)) {
                echo "Entity class $entityType not found.\n";
                return;
            }
            
            $entity = clone $entityType::find_one(['id' => $entityId]);
            if (!$entity || empty($entity->id)) {
                echo "Entity not found.\n";
                return;
            }
            
            if (class_exists('\\SPPMod\\SPPDiff\\RevisionManager')) {
                $pastEntity = \SPPMod\SPPDiff\RevisionManager::getRevision($entity, $revId);
                $pastEntity->save();
                echo "Rolled back successfully to revision {$revId}.\n";
            } else {
                echo "SPPDiff module not active.\n";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
