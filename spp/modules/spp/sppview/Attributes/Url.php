<?php

namespace SPPMod\SPPView\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Url
{
    public function __construct(
        public ?string $as = null,
        public bool $keep = false
    ) {}
}
