<?php
namespace SPPMod\SPPView;

use ReflectionClass;
use ReflectionProperty;

/**
 * LiveComponent
 * 
 * Provides Livewire-like state hydration and reactive rendering for SPP.
 * Works natively with SPP Live (WebSockets) and falls back to SPP Ajax.
 */
abstract class LiveComponent
{
    public string $id;

    public function __construct(string $id = null)
    {
        $this->id = $id ?? uniqid('live_');
    }

    /**
     * Mounts the component initially with default parameters
     */
    public function mount(array $params = []): void
    {
        foreach ($params as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Hydrate public properties from incoming client state
     */
    public function hydrate(array $state): void
    {
        foreach ($state as $key => $value) {
            if (property_exists($this, $key)) {
                $refProp = new ReflectionProperty($this, $key);
                if ($refProp->isPublic()) {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Dehydrate public properties to send back to the client
     */
    public function dehydrate(): array
    {
        $state = [];
        $refClass = new ReflectionClass($this);
        foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $state[$prop->getName()] = $prop->getValue($this);
        }
        return $state;
    }

    /**
     * Dispatches an event over SPP Live (WebSocket) or SPP Ajax (Fallback)
     */
    protected function emit(string $event, ...$params): void
    {
        // Try WebSocket first
        if (class_exists('\SPPMod\SPPLive\LiveEmitter')) {
            \SPPMod\SPPLive\LiveEmitter::emit($this->id, $event, $params);
            return;
        }

        // Fallback to AJAX queue
        if (class_exists('\\SPPMod\\SPPLive\\LiveEmitter')) {
            \SPPMod\SPPLive\LiveEmitter::emit($this->id, $event, $params);
        }
    }

    /**
     * Render the component's view.
     * Inheriting classes must implement this.
     * Return a file path (e.g. 'views/counter.html') or raw HTML.
     * 
     * @return string
     */
    abstract public function render(): string;

    /**
     * Generate a tamper-proof signature for the dehydrated state
     */
    public static function signState(array $state): string
    {
        $secret = \SPP\SPPSession::getSessionVar('spplive_secret');
        if (!$secret) {
            $secret = bin2hex(random_bytes(32));
            \SPP\SPPSession::setSessionVar('spplive_secret', $secret);
        }
        
        $payload = json_encode($state);
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Handles an incoming component update request
     */
    public static function handleRequest(string $componentClass, array $state, string $checksum, ?string $action, array $params = []): array
    {
        if (!class_exists($componentClass)) {
            throw new \Exception("LiveComponent {$componentClass} not found.");
        }

        // Verify tampering
        $expectedChecksum = self::signState($state);
        if (!hash_equals($expectedChecksum, $checksum)) {
            throw new \Exception("LiveComponent state tampering detected.");
        }

        /** @var LiveComponent $component */
        $component = new $componentClass($state['id'] ?? null);
        $component->hydrate($state);

        if ($action && method_exists($component, $action)) {
            call_user_func_array([$component, $action], $params);
        }

        $html = $component->render();
        
        // If render returns a file path to an HTML/PHP view, render it
        if (str_ends_with($html, '.html') || str_ends_with($html, '.php') || str_ends_with($html, '.blade.php')) {
            $filePath = SPP_APP_DIR . '/' . ltrim($html, '/');
            if (file_exists($filePath)) {
                if (str_ends_with($html, '.html')) {
                    $compiled = ViewCompiler::compile($filePath);
                } else {
                    $compiled = $filePath;
                }
                
                ob_start();
                extract($component->dehydrate());
                include $compiled;
                $html = ob_get_clean();
            }
        }

        $newState = $component->dehydrate();

        return [
            'id' => $component->id,
            'state' => $newState,
            'checksum' => self::signState($newState),
            'html' => $html,
            'events' => class_exists('\\SPPMod\\SPPLive\\LiveEmitter') ? \SPPMod\SPPLive\LiveEmitter::flushEvents() : []
        ];
    }
}
