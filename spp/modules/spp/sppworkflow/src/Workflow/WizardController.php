<?php
declare(strict_types=1);

namespace SPP\Core\Workflow;

use SPP\Core\ResourceController;
use SPPMod\SPPWorkflow\SPPWorkflowManager;
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

        $contextData = ['comment' => 'Wizard started'];
        if ($entity instanceof \SPPMod\SPPDB\SPPEntity || (class_exists('\\SPPMod\\SPPDB\\SPPEntity') && is_subclass_of($entity, '\\SPPMod\\SPPDB\\SPPEntity'))) {
            $entity->applyTransition($initialState, null, 'Wizard started', $contextData);
        } else {
            \SPPMod\SPPWorkflow\SPPWorkflowManager::applyTransition($entity, $initialState, null, 'Wizard started', $contextData);
        }
        $this->saveWizardEntity($entity);

        if ($this->isHtmx() || (isset($_SERVER['HTTP_TURBO_FRAME']) && $_SERVER['HTTP_TURBO_FRAME'])) {
            return $this->renderPartial("partials/wizard_{$initialState}.html", [
                'item' => $entity,
                'current_step' => $initialState,
                'next_steps' => SPPWorkflowManager::getNextStates($this->entityType, $initialState, $this->bundle)
            ]);
        }

        return [
            'view' => "wizard_{$initialState}",
            'data' => [
                'item' => $entity,
                'current_step' => $initialState,
                'next_steps' => SPPWorkflowManager::getNextStates($this->entityType, $initialState, $this->bundle)
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
                'next_steps' => SPPWorkflowManager::getNextStates($this->entityType, $stepName, $this->bundle)
            ]);
        }

        return [
            'view' => "wizard_{$stepName}",
            'data' => [
                'item' => $entity,
                'current_step' => $stepName,
                'next_steps' => SPPWorkflowManager::getNextStates($this->entityType, $stepName, $this->bundle)
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
            $validNexts = SPPWorkflowManager::getNextStates($this->entityType, $stepName, $this->bundle);
            $nextState = $validNexts[0] ?? null;
        }

        if (!$nextState) {
            throw new SPPException("WizardController Error: No valid next state determined from step '{$stepName}'.");
        }

        $contextData = [
            'item' => $entity,
            'current_step' => $nextState,
            'next_steps' => SPPWorkflowManager::getNextStates($this->entityType, $nextState, $this->bundle),
            'comment' => "Completed wizard step {$stepName}. Moving to {$nextState}."
        ];

        $response = $this->transitionEntity($entity, $nextState, $contextData, "partials/wizard_{$nextState}.html", "partials/wizard_{$stepName}.html");
        $this->saveWizardEntity($entity);

        return $response;
    }

    /**
     * Complete the wizard.
     */
    public function finish($args = [])
    {
        $entity = $this->getWizardEntity();
        $finalState = $this->getFinalState();

        $contextData = [
            'item' => $entity,
            'comment' => "Finished wizard."
        ];

        $response = $this->transitionEntity($entity, $finalState, $contextData, "partials/wizard_complete.html", "partials/wizard_error.html");
        $this->saveWizardEntity($entity);

        return $response;
    }

    protected function getInitialState(): string
    {
        $workflow = SPPWorkflowManager::getWorkflow($this->entityType, $this->bundle);
        return $workflow['states'][0] ?? 'step_1';
    }

    protected function getFinalState(): string
    {
        $workflow = SPPWorkflowManager::getWorkflow($this->entityType, $this->bundle);
        $states = $workflow['states'] ?? ['completed'];
        return end($states);
    }
}
