<?php
namespace SPPMod\SPPIntegrations;

/**
 * Class SPPIntegrations
 * 
 * SPP Module Bootstrap for the Integrations Core and Integration Container.
 */
class SPPIntegrations
{
    public static function init(): void
    {
        // Load the core interfaces and factory
        require_once __DIR__ . '/int.driver.php';
        require_once __DIR__ . '/class.abstractdriver.php';
        if (file_exists(__DIR__ . '/class.integrationfactory.php')) {
            require_once __DIR__ . '/class.integrationfactory.php';
        }

        // Load IPC components
        require_once __DIR__ . '/src/IPC/IpcTransportInterface.php';
        require_once __DIR__ . '/src/IPC/FileChannelTransport.php';
        require_once __DIR__ . '/src/IPC/SocketTransport.php';
        require_once __DIR__ . '/src/IPC/RemoteServiceProxy.php';
        require_once __DIR__ . '/src/IPC/IpcServer.php';

        // Load Container and Adapters
        require_once __DIR__ . '/src/Container/IntegrationContainer.php';
        require_once __DIR__ . '/src/Adapters/ExternalAppAdapter.php';
        require_once __DIR__ . '/sdk/SppIntegrationClient.php';

        // Load root facade
        $facade = dirname(__DIR__, 2) . '/core/class.integrationcontainer.php';
        if (file_exists($facade)) {
            require_once $facade;
        }

        // Auto-bootstrap container with available drivers
        \SPPMod\SPPIntegrations\Container\IntegrationContainer::getInstance();
    }
}

// Auto-init for SPP module lifecycle
SPPIntegrations::init();
