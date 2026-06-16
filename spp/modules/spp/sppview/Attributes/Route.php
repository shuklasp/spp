<?php

namespace SPPMod\SPPView\Attributes;

use Attribute;

/**
 * Route Attribute
 * 
 * Allows controllers to define their routes directly above methods,
 * improving DX by keeping routing logic close to the implementation.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Route
{
    public string $path;
    public string $method;
    public string $name;
    public array $middleware;

    /**
     * @param string $path The route path (e.g., '/users/{id}')
     * @param string $method HTTP method(s), default 'GET|POST'
     * @param string $name Optional route name for reverse routing
     * @param array $middleware Array of middleware classes to apply
     */
    public function __construct(
        string $path,
        string $method = 'GET|POST',
        string $name = '',
        array $middleware = []
    ) {
        $this->path = $path;
        $this->method = strtoupper($method);
        $this->name = $name;
        $this->middleware = $middleware;
    }
}
