<?php
declare(strict_types=1);

namespace SPP\Core\Workflow;

use SPP\Core\ResourceController;
use SPP\Core\WorkflowManager;
use SPP\Core\Interfaces\WorkflowableInterface;
use SPP\Exceptions\SPPException;

/**
 * Class WizardController
 * Abstract base controller designed specifically for multi-step interactive wizard workflows.
 * Strictly enforces Zero Inline HTML Literals by utilizing external partials and streams.
 */
abstract class WizardController extends ResourceController
{
    protected string $workflowName;
    protected string $entityType;
    protected string $bundle = 'default';

    /**
     * Get the current active wizard entity from session or storage.
     *
     * @return mixed
     */
    abstract protected function getWizardEntity();

    /**
     * Save or persist the active wizard entity state.
     *
     * @param mixed $entity
     * @return void
     */
    abstract protected function saveWizardEntity($entity): void;

    /**
     * Start or restart the wizard workflow.
     */
    public function start($args)
    {
        $entity = $this->getWizardEntity();
        $initialState = $this->getInitialState();

        if ($entity instanceof WorkflowableInterface) {
            $entity->setWorkflowStatus($initialState);
        } elseif (method_exists($entity, 'set')) {
            $entity->set('status', $initialState);
        } elseif (property_exists($entity, 'status') || isset($entity->status)) {
            $entity->status = $initialState;
        }
        $this->saveWizardEntity($entity);

        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            return $this->renderPartial("partials/wizard_{$initialState}.html", [
                'item' => $entity,
                'current_step' => $initialState,
                'next_steps' => WorkflowManager::getNextStates($this->entityType, $initialState, $this->bundle)
            ]);
        }

        return [
            'view' => "wizard_{$initialState}",
            'data' => [
                'item' => $entity,
                'current_step' => $initialState,
                'next_steps' => WorkflowManager::getNextStates($this->entityType, $initialState, $this->bundle)
            ]
        ];
    }

    /**
     * Display a specific step of the wizard.
     */
    public function step($stepName)
    {
        $entity = $this->getWizardEntity();
        $currentStatus = ($entity instanceof WorkflowableInterface) ? $entity->getWorkflowStatus() : ($entity->status ?? 'draft');

        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            return $this->renderPartial("partials/wizard_{$stepName}.html", [
                'item' => $entity,
                'current_step' => $stepName,
                'next_steps' => WorkflowManager::getNextStates($this->entityType, $stepName, $this->bundle)
            ]);
        }

        return [
            'view' => "wizard_{$stepName}",
            'data' => [
                'item' => $entity,
                'current_step' => $stepName,
                'next_steps' => WorkflowManager::getNextStates($this->entityType, $stepName, $this->bundle)
            ]
        ];
    }

    /**
     * Process a form submission for a specific step and transition to the next state.
     */
    public function processStep($stepName, $args = [])
    {
        $entity = $this->getWizardEntity();
        $data = $_POST;

        // Apply submitted data to entity
        if (method_exists($entity, 'setValues')) {
            $entity->setValues($data);
        } else {
            foreach ($data as $key => $val) {
                if (method_exists($entity, 'set')) {
                    $entity->set($key, $val);
                } else {
                    $entity->$key = $val;
                }
            }
        }

        $nextState = $data['next_state'] ?? null;
        if (!$nextState) {
            $validNexts = WorkflowManager::getNextStates($this->entityType, $stepName, $this->bundle);
            $nextState = $validNexts[0] ?? null;
        }

        if (!$nextState) {
            throw new SPPException("WizardController Error: No valid next state determined from step '{$stepName}'.");
        }

        // Apply transition (triggers guards, events, audit history)
        WorkflowManager::applyTransition($entity, $nextState, null, "Completed wizard step {$stepName}. Moving to {$nextState}.");
        $this->saveWizardEntity($entity);

        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            return $this->renderPartial("partials/wizard_{$nextState}.html", [
                'item' => $entity,
                'current_step' => $nextState,
                'next_steps' => WorkflowManager::getNextStates($this->entityType, $nextState, $this->bundle)
            ]);
        }

        return [
            'success' => true,
            'current_step' => $nextState,
            'message' => "Step {$stepName} completed successfully."
        ];
    }

    /**
     * Complete the wizard.
     */
    public function finish($args = [])
    {
        $entity = $this->getWizardEntity();
        $finalState = $this->getFinalState();

        WorkflowManager::applyTransition($entity, $finalState, null, "Finished wizard.");
        $this->saveWizardEntity($entity);

        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            return $this->renderPartial("partials/wizard_complete.html", ['item' => $entity]);
        }

        return [
            'view' => 'wizard_complete',
            'data' => ['item' => $entity]
        ];
    }

    protected function getInitialState(): string
    {
        $workflow = WorkflowManager::getWorkflow($this->entityType, $this->bundle);
        return $workflow['states'][0] ?? 'step_1';
    }

    protected function getFinalState(): string
    {
        $workflow = WorkflowManager::getWorkflow($this->entityType, $this->bundle);
        $states = $workflow['states'] ?? ['completed'];
        return end($states);
    }
}
