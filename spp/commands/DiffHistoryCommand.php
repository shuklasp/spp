<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DiffHistoryCommand extends Command
{
    protected string $name = 'diff:history';
    protected string $description = 'View revision history of an entity';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $entityType = null;
        $entityId = null;
        $isJson = false;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--type=')) $entityType = substr($arg, 7);
            elseif (str_starts_with($arg, '--id=')) $entityId = substr($arg, 5);
            elseif ($arg === '--json') $isJson = true;
        }

        if (!$entityType || !$entityId) {
            if ($isJson) {
                echo json_encode(['error' => 'Missing --type or --id']);
            } else {
                echo "Usage: php spp.php diff:history --type=<ModelClass> --id=<ID> [--json]\n";
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
                $history = \SPPMod\SPPDiff\RevisionManager::getHistory($entity);
                if (empty($history)) {
                    if ($isJson) echo json_encode([]);
                    else echo "No revision history found.\n";
                    return;
                }
                
                if ($isJson) {
                    echo json_encode($history);
                } else {
                    echo "Revision history for $entityType ($entityId):\n";
                    foreach ($history as $h) {
                        echo "[Rev {$h['id']}] Date: {$h['created_at']} | User: {$h['user_id']}\n";
                    }
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
