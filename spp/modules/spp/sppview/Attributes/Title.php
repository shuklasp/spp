<?php

namespace SPPMod\SPPView\Attributes;

use Attribute;

/**
 * Sets the document title for a LiveComponent.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Title
{
    /**
     * @param string $title The title of the page.
     */
    public function __construct(
        public string $title
    ) {
    }
}
