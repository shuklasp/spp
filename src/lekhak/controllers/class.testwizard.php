<?php

namespace App\Lekhak\Controllers;

use SPP\Core\Workflow\WizardController;

/**
 * Class TestWizard
 * Handles the multi-step test wizard process.
 */
class TestWizard extends WizardController
{
    protected string $workflowName = 'test';
    protected string $entityType = 'test';
    protected string $bundle = 'default';

    /**
     * Retrieve the active wizard entity (e.g. from session or database).
     * @return object
     */
    protected function getWizardEntity()
    {
        // Example: load from session
        if (!isset($_SESSION['wizard_test'])) {
            $entity = new \stdClass();
            \SPPMod\SPPWorkflow\SPPWorkflowManager::applyTransition($entity, 'draft', null, 'Initial test setup');
            $_SESSION['wizard_test'] = $entity;
        }
        return $_SESSION['wizard_test'];
    }

    /**
     * Save the active wizard entity state.
     * @param object $entity
     */
    protected function saveWizardEntity($entity): void
    {
        // Example: save to session
        $_SESSION['wizard_test'] = $entity;
    }
}
