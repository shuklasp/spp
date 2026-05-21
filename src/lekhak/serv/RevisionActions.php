<?php
namespace App\Lekhak\Serv;

use App\Lekhak\Services\NodeRevisionStorage;
use App\Lekhak\UI\RevisionDiffViewer;
use App\Lekhak\Entities\Node;

/**
 * LiveAction functions for Revision management in the admin panel.
 */

function live_Revision_List($la, $params) {
    $entityId = $params['entity_id'] ?? null;
    $entityType = $params['entity_type'] ?? 'node';

    if (!$entityId) return $la->setStatus('error')->notify("Entity ID required.");

    $storage = new NodeRevisionStorage();
    $revisions = $storage->listRevisions($entityType, $entityId);

    $la->setData(['revisions' => $revisions]);
}

function live_Revision_Compare($la, $params) {
    $entityId = $params['entity_id'] ?? null;
    $entityType = $params['entity_type'] ?? 'node';
    $oldId = $params['old_id'] ?? null;
    $newId = $params['new_id'] ?? null;

    if (!$entityId || !$oldId || !$newId) {
        return $la->setStatus('error')->notify("Missing parameters for comparison.");
    }

    $storage = new NodeRevisionStorage();
    $oldData = $storage->loadRevision($entityType, $entityId, $oldId) ?? [];
    $newData = $storage->loadRevision($entityType, $entityId, $newId) ?? [];

    $html = RevisionDiffViewer::renderDiff($oldData, $newData);
    $la->setData(['html' => $html]);
}

function live_Revision_Revert($la, $params) {
    $entityId = $params['entity_id'] ?? null;
    $entityType = $params['entity_type'] ?? 'node';
    $revisionId = $params['revision_id'] ?? null;

    if (!$entityId || !$revisionId) {
        return $la->setStatus('error')->notify("Entity ID and Revision ID required.");
    }

    $storage = new NodeRevisionStorage();
    $data = $storage->loadRevision($entityType, $entityId, $revisionId);

    if (!$data) {
        return $la->setStatus('error')->notify("Revision not found.");
    }

    // Attempt to save the old data as the current entity
    if ($entityType === 'node') {
        $node = new Node($entityId);
        if ($node->id) {
            foreach ($data as $key => $val) {
                if ($key !== 'id') {
                    $node->set($key, $val);
                }
            }
            $_POST['revision_log'] = "Reverted to revision {$revisionId}";
            $node->save();
            $la->notify("Successfully reverted to revision {$revisionId}.", "success");
        } else {
            $la->setStatus('error')->notify("Current entity not found.");
        }
    } else {
         $la->setStatus('error')->notify("Revert for entity type '{$entityType}' is not yet supported.");
    }
}
