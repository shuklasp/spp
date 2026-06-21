<?php

namespace SPP\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class On
{
    public function __construct(
        public string $event,
        public int $priority = 500
    ) {}
}
