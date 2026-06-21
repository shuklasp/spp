<?php

namespace SPP\Core\Interfaces;

/**
 * Interface FrontendComponentInterface
 * 
 * A marker interface for any class that represents a frontend UI component.
 * This signals to the core engine (like SPPEvent) that this class should 
 * not be instantiated dynamically for backend background tasks or listeners.
 */
interface FrontendComponentInterface
{
    // Marker interface
}
