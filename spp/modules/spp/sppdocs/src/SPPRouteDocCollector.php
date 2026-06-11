<?php
namespace SPPMod\SppDocs;

use SPP\Core\RouterFacade;

class SPPRouteDocCollector {
    public function collect() {
        // Collect defined routes using the new RouterFacade
        // Currently returning a stub array for demonstration
        return [
            [
                'method' => 'GET',
                'path' => '/api/users',
                'description' => 'List all users'
            ],
            [
                'method' => 'POST',
                'path' => '/api/users',
                'description' => 'Create a new user'
            ]
        ];
    }
}
