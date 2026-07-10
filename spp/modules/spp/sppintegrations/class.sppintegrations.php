<?php
namespace SPPMod\SPPIntegrations;

/**
 * Class SPPIntegrations
 * 
 * SPP Module Bootstrap for the Integrations Core.
 */
class SPPIntegrations
{
    public static function init(): void
    {
        // Load the core interfaces and factory
        require_once __DIR__ . '/int.driver.php';
        require_once __DIR__ . '/class.abstractdriver.php';
        require_once __DIR__ . '/class.factory.php';
    }
}

// Auto-init for SPP module lifecycle
SPPIntegrations::init();
