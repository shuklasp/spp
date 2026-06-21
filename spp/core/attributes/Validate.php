<?php

namespace SPP\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Validate
{
    public string $rules;

    public function __construct(string $rules)
    {
        $this->rules = $rules;
    }
}
