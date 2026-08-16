<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DiffCompareCommand extends Command
{
    protected string $name = 'diff:compare';
    protected string $description = 'Compare two JSON arrays or states';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $entityType = null;
        $entityId = null;
        $revId = null;
        $isJson = false;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--type=')) $entityType = substr($arg, 7);
            elseif (str_starts_with($arg, '--id=')) $entityId = substr($arg, 5);
            elseif (str_starts_with($arg, '--rev=')) $revId = substr($arg, 6);
            elseif ($arg === '--json') $isJson = true;
        }

        if (!$entityType || !$entityId || !$revId) {
            if ($isJson) {
                echo json_encode(['error' => 'Missing --type, --id or --rev']);
            } else {
                echo "Usage: php spp.php diff:compare --type=<ModelClass> --id=<ID> --rev=<RevID> [--json]\n";
            }
            return;
        }

        try {
            if (!class_exists($entityType)) {
                if ($isJson) echo json_encode(['error' => "Entity class $entityType not found."]);
                else echo "Entity class $entityType not found.\n";
                return;
            }
            
            $entity = clone (new $entityType());
            $entity = clone $entityType::find_one(['id' => $entityId]);
            if (!$entity || empty($entity->id)) {
                if ($isJson) echo json_encode(['error' => "Entity not found."]);
                else echo "Entity not found.\n";
                return;
            }
            
            if (class_exists('\\SPPMod\\SPPDiff\\RevisionManager')) {
                $pastEntity = \SPPMod\SPPDiff\RevisionManager::getRevision($entity, (int)$revId);
                
                if ($isJson) {
                    echo json_encode($pastEntity->getValues());
                } else {
                    echo "Snapshot for $entityType ($entityId) at Rev $revId:\n";
                    print_r($pastEntity->getValues());
                }
            } else {
                if ($isJson) echo json_encode(['error' => 'SPPDiff module not active.']);
                else echo "SPPDiff module not active.\n";
            }
        } catch (\Exception $e) {
            if ($isJson) echo json_encode(['error' => $e->getMessage()]);
            else echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
