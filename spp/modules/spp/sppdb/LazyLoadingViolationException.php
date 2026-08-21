<?php

namespace SPPMod\SPPDB;

/**
 * Thrown when an application attempts to lazy load a database relationship 
 * while strict lazy loading is enabled.
 */
class LazyLoadingViolationException extends \RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param  object  $model
     * @param  string  $relation
     * @return void
     */
    public function __construct($model, $relation)
    {
        $class = get_class($model);
        parent::__construct(
            "Attempted to lazy load [{$relation}] on model [{$class}] but lazy loading is disabled. " .
            "Use SppEntityQuery::with() to eagerly load the relationship."
        );
    }
}
