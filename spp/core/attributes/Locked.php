<?php

namespace SPP\Attributes;

use Attribute;

/**
 * Marks a property as locked, preventing updates from the client.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Locked
{
    public function __construct()
    {
    }
}
