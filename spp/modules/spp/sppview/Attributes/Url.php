<?php

namespace SPPMod\SPPView\Attributes;

use Attribute;

/**
 * Binds a LiveComponent property to a query string parameter.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Url
{
    /**
     * @param string|null $as The query string parameter name.
     * @param bool $history Whether to use pushState.
     * @param bool $keep Whether to keep the parameter on unmount.
     * @param mixed $except The value to omit from the query string.
     */
    public function __construct(
        public ?string $as = null,
        public bool $history = false,
        public bool $keep = false,
        public mixed $except = null
    ) {
    }
}
