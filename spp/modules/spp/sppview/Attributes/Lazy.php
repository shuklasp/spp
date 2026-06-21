<?php

namespace SPPMod\SPPView\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Lazy
{
    public function __construct(
        public string $action = 'load'
    ) {}
}
