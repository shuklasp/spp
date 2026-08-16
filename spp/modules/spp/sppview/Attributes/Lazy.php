<?php

namespace SPPMod\SPPView\Attributes;

use Attribute;

/**
 * Marks a LiveComponent as lazy-loaded.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Lazy
{
    /**
     * @param string $action The action to call for loading.
     * @param bool $isolate Whether to isolate the component.
     */
    public function __construct(
        public string $action = '$refresh',
        public bool $isolate = true
    ) {
    }
}
