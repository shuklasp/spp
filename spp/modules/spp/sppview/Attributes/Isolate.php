<?php

namespace SPPMod\SPPView\Attributes;

use Attribute;

/**
 * Marks a LiveComponent to have isolated events.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Isolate
{
    public function __construct()
    {
    }
}
