<?php
namespace SPPMod\SPPIntegrations;

use SPP\ViewController;

/**
 * Class MeshApiController
 * 
 * The Unified REST Gateway. Allows any app to request normalized data
 * from the entire mesh (e.g. GET /api/mesh/content?type=product).
 */
class MeshApiController extends ViewController
{
    public function index()
    {
        $type = $_GET['type'] ?? 'post';
        
        $results = [];
        $drivers = IntegrationFactory::getDrivers();

        foreach ($drivers as $alias => $driver) {
            // Introspect capabilities
            if ($type === 'product' && $driver instanceof CommerceProviderInterface) {
                $rawProducts = $driver->fetchProducts();
                foreach ($rawProducts as $raw) {
                    $results[] = OntologyMapper::normalize($alias, 'product', $raw);
                }
            } elseif ($type === 'post' && $driver instanceof ContentProviderInterface) {
                $rawPosts = $driver->fetchPosts();
                foreach ($rawPosts as $raw) {
                    $results[] = OntologyMapper::normalize($alias, 'post', $raw);
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'data' => $results]);
        exit;
    }
}
