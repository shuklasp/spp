<?php
namespace SPPMod\SPPDoc;

use SPP\Core\Router\RouterFacade;

class SPPRouteDocCollector {
    public function collect() {
        // Collect defined routes using the new RouterFacade
        if (class_exists('\\SPP\\Core\\Router\\RouterFacade') && method_exists('\\SPP\\Core\\Router\\RouterFacade', 'getRoutes')) {
            $routes = RouterFacade::getRoutes();
            $formatted = [];
            foreach ($routes as $r) {
                $actionStr = is_string($r['action']) ? $r['action'] : (is_array($r['action']) ? implode('@', $r['action']) : 'Closure');
                $formatted[] = [
                    'method' => $r['method'] ?? 'GET',
                    'path' => $r['uri'] ?? '/',
                    'action' => $actionStr,
                    'middleware' => $r['middleware'] ?? []
                ];
            }
            if (!empty($formatted)) {
                return $formatted;
            }
        }

        // Fallback if no routes defined in Router yet
        return [
            [
                'method' => 'GET',
                'path' => '/api/users',
                'description' => 'List all users (Fallback sample)'
            ],
            [
                'method' => 'POST',
                'path' => '/api/users',
                'description' => 'Create a new user (Fallback sample)'
            ]
        ];
    }
}
