<?php

namespace SPPMod\SPPView\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Title
{
    public string $title;

    public function __construct(string $title)
    {
        $this->title = $title;
    }
}
