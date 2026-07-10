<?php
declare(strict_types=1);

namespace SPP\Core\Interfaces;

/**
 * Interface WorkflowableInterface
 * Defines a formal contract for entities that support state-machine workflow management.
 */
interface WorkflowableInterface
{
    /**
     * Get the entity type name (e.g. 'node', 'order').
     *
     * @return string
     */
    public function getWorkflowEntityType(): string;

    /**
     * Get the bundle or subtype name (e.g. 'article', 'page', 'default').
     *
     * @return string
     */
    public function getWorkflowBundle(): string;

    /**
     * Get the current workflow status/state of the entity.
     *
     * @return string
     */
    public function getWorkflowStatus(): string;

    /**
     * Set the new workflow status/state of the entity.
     *
     * @param string $newStatus
     * @return void
     */
    public function setWorkflowStatus(string $newStatus): void;
}
