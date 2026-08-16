<?php

namespace SPP\Attributes;

use Attribute;

/**
 * Marks a method or property as a computed property.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class Computed
{
    /**
     * @param bool $cache Whether to cache the computed value for the request.
     * @param bool $persist Whether to persist the computed value.
     */
    public function __construct(
        public bool $cache = true,
        public bool $persist = false
    ) {
    }
}
