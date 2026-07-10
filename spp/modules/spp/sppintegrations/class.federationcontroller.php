<?php
namespace SPPMod\SPPIntegrations;

use SPP\MVC\Controllers\ViewController;

/**
 * Class IntegrationFederationController
 * 
 * Exposes a central SPP endpoint to federate raw data from integrated apps
 * into rendered HTML blocks for cross-app embedding (e.g., Moodle block in Drupal).
 */
class IntegrationFederationController extends ViewController
{
    /**
     * Endpoint: /api/integration/federated-block
     * 
     * Renders data from a remote driver into a standardized SPP partial.
     * Returning HTML allows for true Zero-Touch injection into external apps.
     */
    public function renderFederatedBlock(): void
    {
        $appAlias = $_GET['app'] ?? '';
        $endpoint = $_GET['endpoint'] ?? '';
        $template = $_GET['template'] ?? 'default_card'; // partial name

        if (empty($appAlias) || empty($endpoint)) {
            $this->renderStaticPartial('partials/error_block.html');
            return;
        }

        try {
            $driver = IntegrationFactory::getDriver($appAlias);
            $raw_data = $driver->fetchData($endpoint);
            
            // Following SPP Architect rules: Use external partials, no inline HTML literals
            $this->renderPartial('partials/integration_' . $template . '.html', ['data' => $raw_data]);
            
        } catch (\Exception $e) {
            $this->renderPartial('partials/error_block.html', ['message' => $e->getMessage()]);
        }
    }
}
