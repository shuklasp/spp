<?php

namespace SPP\Attributes;

use Attribute;

/**
 * Listens for an event on the LiveComponent.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class On
{
    /**
     * @param string $event The event name to listen for.
     */
    public function __construct(
        public string $event
    ) {
    }
}
