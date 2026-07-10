<?php
declare(strict_types=1);

namespace SPP\Core\Interfaces;

/**
 * Interface ReviewableEntityInterface
 * Extends WorkflowableInterface to support organizational hierarchies, multi-tier approvals, and dynamic assignment.
 */
interface ReviewableEntityInterface extends WorkflowableInterface
{
    /**
     * Get the ID or username of the currently assigned reviewer.
     *
     * @return string|null
     */
    public function getAssignedReviewer(): ?string;

    /**
     * Assign a specific reviewer to this entity.
     *
     * @param string|null $reviewerId
     * @return void
     */
    public function assignReviewer(?string $reviewerId): void;

    /**
     * Get the department responsible for the current approval tier.
     *
     * @return string|null
     */
    public function getDepartment(): ?string;

    /**
     * Get the rejection reason if the workflow transition was rejected or sent back.
     *
     * @return string|null
     */
    public function getRejectionReason(): ?string;

    /**
     * Set the rejection reason when denying or sending back an entity in the workflow.
     *
     * @param string $reason
     * @return void
     */
    public function setRejectionReason(string $reason): void;
}
