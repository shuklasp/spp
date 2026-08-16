<?php

namespace SPP\Attributes;

use Attribute;

/**
 * Adds validation rules to a property.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Validate
{
    /**
     * @param mixed $rules The validation rules.
     * @param string|null $message Custom error message.
     * @param string|null $as Custom attribute name for messages.
     */
    public function __construct(
        public mixed $rules,
        public ?string $message = null,
        public ?string $as = null
    ) {
    }
}
