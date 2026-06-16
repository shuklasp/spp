<?php

namespace SPP;

abstract class EventHandler
{
    protected string $event_name;
    protected string $handler_name;
    
    // Legacy arrays kept for backward compatibility if directly accessed
    protected $before_handlers = [];
    protected $after_handlers = [];
    protected $override_handlers = [];
    
    /** @var int Execution priority (0-1000, higher runs first) */
    protected int $priority = 500;

    /** @var bool Flag to stop event propagation */
    private bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function __construct($event_name = null, $is_default = false)
    {
        $this->event_name = '';
        $this->event_name = $this->getEventName();
        $this->handler_name = $this->event_name;
        $this->event_name = ($event_name == null) ? $this->handler_name : $event_name;
        
        if ($is_default) {
            $this->overrideHandler();
        } else {
            $this->initHandler();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [];
    }

    /**
     * Initializes the handler. To be overridden in child classes.
     */
    protected function initHandler()
    {
    }

    /**
     * Modern explicit hook registration.
     * Routes the callable directly into SPPEvent's fast router.
     */
    protected function addHook(string $stage, string $methodName, int $priority = 500)
    {
        $hookName = ($stage === 'override' || $stage === 'instead') 
            ? 'instead_' . $this->event_name 
            : (($stage === 'main') ? $this->event_name : $stage . '_' . $this->event_name);
            
        $isOverride = ($stage === 'override' || $stage === 'instead');

        \SPP\SPPEvent::listen($hookName, [$this, $methodName], $isOverride, $priority);
    }

    /**
     * Legacy mappings to the new addHook router.
     */
    protected function addBeforeHandler($handler)
    {
        $this->addHook('before', $handler, $this->priority);
    }

    protected function addAfterHandler($handler)
    {
        $this->addHook('after', $handler, $this->priority);
    }

    protected function addOverrideHandler($handler)
    {
        $this->addHook('override', $handler, $this->priority);
    }

    /**
     * Legacy stage triggers. We keep these for instances that were manually called, 
     * though the router won't use them anymore.
     */
    public function beforeHandler(mixed &$params = [])
    {
        // No-op in modern router
    }

    public function overrideHandler(mixed &$params = [])
    {
        // No-op in modern router
    }

    public function afterHandler(mixed &$params = [])
    {
        // No-op in modern router
    }

    public function __call($name, $arguments)
    {
        throw new \BadMethodCallException("Call to undefined method " . static::class . "::{$name}()");
    }

    public function getEventName()
    {
        if ($this->event_name == '') {
            $this->event_name = get_called_class();
            $this->event_name = basename(str_replace('\\', '/', $this->event_name));
        }
        return $this->event_name;
    }

    public function getHandlerName()
    {
        return $this->getEventName();
    }
}
