<?php
namespace App\Samvaad\Serv;

/**
 * ============================================================================
 * DashboardController — Data Display with Auth
 * ============================================================================
 *
 * Demonstrates:
 *   - Rendering Blade views with dynamic data
 *   - Authentication checking before rendering
 *   - Database entity queries (when SPPDB is available)
 *   - Passing data to Blade templates
 * ============================================================================
 */
class DashboardController
{
    public function index()
    {
        // Boot SPP-UX for @sppux directive
        if (class_exists('\SPPMod\Drishyam\SPPUX')) {
            \SPPMod\Drishyam\SPPUX::boot();
        }

        // Check authentication (optional — uncomment to require login)
        // if (class_exists('\SPPMod\SPPAuth\SPPAuth') && !\SPPMod\SPPAuth\SPPAuth::authSessionExists()) {
        //     header('Location: ' . \SPP\App::getBaseUrl('Samvaad') . '/login');
        //     exit;
        // }

        // Example: Query database entities
        $items = [];
        // Uncomment when SPPDB is configured:
        // $db = new \SPPMod\SPPDB\SPPDB();
        // $items = $db->execute_query('SELECT * FROM Samvaad_items ORDER BY id DESC LIMIT 10');

        $blade = \SPPMod\Drishyam\SPPBlade::getInstance();
        return $blade->run('dashboard', [
            'app_name' => 'Samvaad',
            'base_url' => \SPP\App::getBaseUrl('Samvaad'),
            'items' => $items,
            'stats' => [
                'total_items' => count($items),
                'active' => 0,
                'completed' => 0,
            ]
        ]);
    }
}