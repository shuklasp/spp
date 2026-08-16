<?php

namespace SPPMod\SPPWorkflow;
require_once __DIR__ . '/src/WorkflowManager.php';

/**
 * SPPWorkflow Module Initialization
 * Registers decoupled event listeners for automatic workflow checks on entity changes.
 */

if (class_exists('\\SPP\\SPPEvent')) {
    \SPP\SPPEvent::listen('entity:after_save', function($entity) {
        if ($entity instanceof \SPP\EventParams) {
            $entity = $entity->get('entity');
        }
        if (is_object($entity) && class_exists('\\SPPMod\\SPPWorkflow\\SPPWorkflowManager')) {
            $statusField = method_exists($entity, 'getWorkflowStatusField') ? $entity->getWorkflowStatusField() : 'status';
            $status = method_exists($entity, 'get') ? ($entity->get($statusField) ?: 'draft') : (isset($entity->$statusField) ? $entity->$statusField : 'draft');
            
            $entityClass = get_class($entity);
            $parts = explode('\\', $entityClass);
            $entityType = strtolower(array_pop($parts));

            $bundle = 'default';
            if (method_exists($entity, 'attributeExists') && $entity->attributeExists('bundle')) {
                $bundle = $entity->get('bundle') ?: 'default';
            } elseif (method_exists($entity, 'get') && isset($entity->bundle)) {
                $bundle = $entity->get('bundle') ?: 'default';
            } elseif (isset($entity->bundle)) {
                $bundle = $entity->bundle ?: 'default';
            }

            // Dispatch workflow specific event if workflow exists
            if (\SPPMod\SPPWorkflow\SPPWorkflowManager::getWorkflow($entityType, $bundle)) {
                \SPP\SPPEvent::fireEvent('workflow.entity.updated', new \SPP\EventParams([
                    'entity' => $entity,
                    'status' => $status,
                    'type' => $entityType,
                    'bundle' => $bundle
                ]));
            }
        }
    });
}

if (class_exists('\SPP\CLI\CommandManager')) {
    if (!class_exists('\SPPMod\SPPWorkflow\Commands\GenerateSnapshotsCommand')) {
        require_once __DIR__ . '/Commands/GenerateSnapshotsCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPWorkflow\Commands\GenerateSnapshotsCommand());

    if (!class_exists('\SPPMod\SPPWorkflow\Commands\DispatchWebhooksCommand')) {
        require_once __DIR__ . '/Commands/DispatchWebhooksCommand.php';
    }
    \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPWorkflow\Commands\DispatchWebhooksCommand());
}
