<?php
declare(strict_types=1);

namespace SPP\Core\Workflow;

use SPP\Core\Interfaces\ReviewableEntityInterface;
use SPP\Core\WorkflowManager;
use SPP\Exceptions\SPPException;

/**
 * Class ApprovalChain
 * Orchestrates organizational hierarchical workflows, multi-tier approvals, and threshold-based auto-approvals.
 */
class ApprovalChain
{
    protected string $name;
    protected array $tiers = [];

    /**
     * ApprovalChain constructor.
     *
     * @param string $name
     * @param array $tiers
     */
    public function __construct(string $name, array $tiers = [])
    {
        $this->name = $name;
        $this->tiers = $tiers;
    }

    /**
     * Add an approval tier to the chain.
     *
     * @param string $tierName e.g., 'manager', 'director', 'finance'
     * @param array $approvers User IDs or roles authorized for this tier
     * @param float $threshold Financial/quantity threshold below which this tier can be bypassed
     * @param string|null $department Optional department filter
     * @return self
     */
    public function addTier(string $tierName, array $approvers, float $threshold = 0.0, ?string $department = null): self
    {
        $this->tiers[$tierName] = [
            'approvers' => $approvers,
            'threshold' => $threshold,
            'department' => $department
        ];
        return $this;
    }

    /**
     * Get all configured tiers.
     *
     * @return array
     */
    public function getTiers(): array
    {
        return $this->tiers;
    }

    /**
     * Evaluate if the entity qualifies for automatic threshold bypass to a higher tier or auto-approval.
     *
     * @param mixed $entity
     * @param string $amountField
     * @return string|null Returns the name of the tier that requires approval, or null if fully auto-approved.
     */
    public function evaluateRequiredTier($entity, string $amountField = 'amount'): ?string
    {
        $amount = 0.0;
        if (method_exists($entity, 'get')) {
            $amount = (float)($entity->get($amountField) ?: 0.0);
        } elseif (isset($entity->$amountField)) {
            $amount = (float)$entity->$amountField;
        }

        $entityDepartment = ($entity instanceof ReviewableEntityInterface) ? $entity->getDepartment() : null;

        foreach ($this->tiers as $tierName => $meta) {
            if ($meta['department'] !== null && $entityDepartment !== null && $meta['department'] !== $entityDepartment) {
                continue;
            }
            if ($amount >= $meta['threshold']) {
                return $tierName;
            }
        }

        return null; // Value is below all thresholds; requires no higher approval
    }

    /**
     * Check if a user is an authorized approver for the current tier.
     *
     * @param mixed $entity
     * @param mixed $user
     * @param string $currentTier
     * @return bool
     */
    public function isAuthorizedApprover($entity, $user, string $currentTier): bool
    {
        if (!isset($this->tiers[$currentTier])) {
            return false;
        }

        $userId = is_object($user) ? (method_exists($user, 'getId') ? $user->getId() : ($user->id ?? null)) : $user;
        if ($userId === null && class_exists('\SPPMod\SPPAuth\SPPAuth')) {
            $currentUser = \SPPMod\SPPAuth\SPPAuth::user();
            $userId = $currentUser->id ?? (method_exists($currentUser, 'getId') ? $currentUser->getId() : null);
        }

        // Check if explicitly assigned
        if ($entity instanceof ReviewableEntityInterface) {
            $assigned = $entity->getAssignedReviewer();
            if ($assigned !== null && (string)$assigned === (string)$userId) {
                return true;
            }
        }

        $approvers = $this->tiers[$currentTier]['approvers'];
        return in_array((string)$userId, array_map('strval', $approvers), true);
    }

    /**
     * Progress the approval chain by applying the transition to the next status.
     *
     * @param mixed $entity
     * @param mixed $user
     * @param string $currentTier
     * @param string $nextStatus
     * @param string $comment
     * @return bool
     * @throws SPPException
     */
    public function approve($entity, $user, string $currentTier, string $nextStatus, string $comment = ''): bool
    {
        if (!$this->isAuthorizedApprover($entity, $user, $currentTier)) {
            throw new SPPException("ApprovalChain Error: User is not authorized to approve tier '{$currentTier}'.");
        }

        return WorkflowManager::applyTransition($entity, $nextStatus, $user, "Approved tier {$currentTier}. " . $comment);
    }

    /**
     * Reject the workflow and send it back to a rejection status, recording the reason.
     *
     * @param mixed $entity
     * @param mixed $user
     * @param string $currentTier
     * @param string $rejectionStatus
     * @param string $reason
     * @return bool
     * @throws SPPException
     */
    public function reject($entity, $user, string $currentTier, string $rejectionStatus, string $reason): bool
    {
        if (!$this->isAuthorizedApprover($entity, $user, $currentTier)) {
            throw new SPPException("ApprovalChain Error: User is not authorized to reject tier '{$currentTier}'.");
        }

        if ($entity instanceof ReviewableEntityInterface) {
            $entity->setRejectionReason($reason);
        } elseif (method_exists($entity, 'set')) {
            $entity->set('rejection_reason', $reason);
        } elseif (property_exists($entity, 'rejection_reason') || isset($entity->rejection_reason)) {
            $entity->rejection_reason = $reason;
        }

        return WorkflowManager::applyTransition($entity, $rejectionStatus, $user, "Rejected tier {$currentTier}. Reason: " . $reason);
    }

    /**
     * Create an ApprovalChain instance directly from a registered YAML workflow configuration.
     *
     * @param string $entityType
     * @param string $bundle
     * @return self
     * @throws SPPException
     */
    public static function createFromWorkflow(string $entityType, string $bundle = 'default'): self
    {
        $workflow = WorkflowManager::getWorkflow($entityType, $bundle);
        if (!$workflow) {
            throw new SPPException("ApprovalChain Error: No workflow configuration found for {$entityType} ({$bundle}).");
        }

        $chainName = ($bundle !== 'default') ? "{$entityType}.{$bundle}" : $entityType;
        $chain = new self($chainName);

        $tiers = $workflow['tiers'] ?? [];
        foreach ($tiers as $tierName => $meta) {
            $approvers = $meta['approvers'] ?? [];
            $threshold = (float)($meta['threshold'] ?? 0.0);
            $department = $meta['department'] ?? null;
            $chain->addTier($tierName, $approvers, $threshold, $department);
        }

        return $chain;
    }
}
