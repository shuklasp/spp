<?php

namespace SPP\Attributes;

use Attribute;

/**
 * Binds a property to the session.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Session
{
    /**
     * @param string|null $key The session key.
     */
    public function __construct(
        public ?string $key = null
    ) {
    }
}
