<?php
namespace SPP;

/**
 * Class SPPEventObject
 * Base class for all modern events in SPP.
 * Supports propagation control and automatic name resolution.
 */
abstract class SPPEventObject
{
    /** @var bool Flag to stop event propagation */
    protected bool $propagationStopped = false;

    /**
     * Halts the event chain.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Checks if propagation has been stopped.
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Returns the event name, resolved from the class name.
     * e.g., \App\Events\UserLoggedInEvent -> user_logged_in
     */
    public function getName(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        // Remove 'Event' suffix if present
        if (str_ends_with($className, 'Event')) {
            $className = substr($className, 0, -5);
        }
        
        // Convert PascalCase to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }
}
