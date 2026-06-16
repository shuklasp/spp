<?php
namespace SPP\Attributes;

/**
 * Route Attribute
 * 
 * Used for defining application routes directly on controller methods
 * instead of relying on pages.yml or routes.yml.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Route {
    public function __construct(
        public string $path,
        public string $method = 'GET|POST',
        public bool $auth = false
    ) {}
}
