<?php

namespace SPPMod\SPPView;

use SPP\SPPException;

/**
 * Class ViewNotFoundException
 * Thrown when a requested view template cannot be located by ViewLocator or ViewController.
 */
class ViewNotFoundException extends SPPException
{
    public function __construct($message, $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
