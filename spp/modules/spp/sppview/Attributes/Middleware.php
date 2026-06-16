<?php

namespace SPPMod\SPPView\Attributes;

use Attribute;

/**
 * Middleware Attribute
 * 
 * Allows attaching middleware logic to controllers or specific methods.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    public string $class;

    /**
     * @param string $class The fully qualified class name of the middleware
     */
    public function __construct(string $class)
    {
        $this->class = $class;
    }
}
