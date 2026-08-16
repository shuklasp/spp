<?php
namespace SPPMod\SPPView;

use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;

class ValidationException extends \Exception {}

use SPP\Core\Interfaces\FrontendComponentInterface;
use SPPMod\SPPView\Traits\LiveValidatorTrait;

/**
 * LiveComponent - Base class for reactive SPPLive components
 *
 * Provides a server-side reactive component system inspired by Laravel Livewire v3.
 * Public properties are automatically serialized (dehydrated) to the client and
 * restored (hydrated) on each subsequent request. State integrity is guaranteed
 * via HMAC SHA-256 checksums.
 *
 * Lifecycle order on subsequent requests:
 *   boot() → booted() → hydrate() → [updating()/updated() per prop] →
 *   [action method] → rendering() → render() → rendered() → dehydrate()
 *
 * @see \SPP\Core\Interfaces\FrontendComponentInterface
 */
abstract class LiveComponent implements FrontendComponentInterface
{
    use LiveValidatorTrait;

    /** @var string Unique component instance identifier */
    public string $id;

    /** @var array<string, string> Validation error messages keyed by field name */
    // $errors and $rules are now provided by LiveValidatorTrait

    /** @var array|null Pending download instruction for the frontend */
    protected ?array $download = null;

    /** @var array Cached computed property values for the current request */
    protected array $computedCache = [];

    /** @var array<string, array> Static cache of parsed PHP 8 Attribute metadata per class */
    protected static array $attributeCache = [];

    /** @var array Snapshot of initial public property values for reset() support */
    protected array $_initialPropertyValues = [];

    /** @var array Pending stream instructions for wire:stream targets */
    protected array $_pendingStreams = [];

    /** @var EventDispatch|null The most recent dispatch() call result for fluent chaining */
    protected ?EventDispatch $_lastDispatch = null;

    /**
     * Properties that are NEVER serialized into the client-side wire:state payload.
     * This prevents leaking protected/internal state and reduces payload size.
     */
    private const EXCLUDED_STATE_PROPS = [
        'rules', 'download', 'computedCache', 'attributeCache',
        '_initialPropertyValues', '_pendingStreams', '_lastDispatch',
    ];

    /**
     * Parse and cache PHP 8 Attributes from a component class using Reflection.
     *
     * Scans for class-level attributes (#[Lazy], #[Isolate], #[Title]) and
     * property-level attributes (#[Locked], #[Session], #[Computed], #[Validate], #[Url])
     * and method-level attributes (#[On]).
     *
     * Results are cached statically per class for the duration of the PHP process.
     *
     * @param string $class Fully-qualified class name of the LiveComponent subclass
     * @return array Parsed attribute metadata
     */
    public static function getParsedAttributes(string $class): array
    {
        if (isset(self::$attributeCache[$class])) {
            return self::$attributeCache[$class];
        }

        $cache = [
            'isLazy' => false,
            'lazyAction' => '$refresh',
            'isIsolated' => false,
            'isRenderless' => false,
            'title' => null,
            'lockedProps' => [],
            'sessionProps' => [],
            'urlProps' => [],
            'computedProps' => [],
            'validationRules' => [],
            'listeners' => [],
            'isAllowGuest' => false,
        ];

        if (PHP_VERSION_ID >= 80000) {
            $refClass = new ReflectionClass($class);
            
            $lazyAttrs = $refClass->getAttributes('SPPMod\SPPView\Attributes\Lazy');
            if (!empty($lazyAttrs)) {
                $cache['isLazy'] = true;
                $lazyInstance = $lazyAttrs[0]->newInstance();
                if (property_exists($lazyInstance, 'action')) {
                    $cache['lazyAction'] = $lazyInstance->action;
                }
            }
            
            $cache['isIsolated'] = !empty($refClass->getAttributes('SPPMod\SPPView\Attributes\Isolate'));
            
            $titleAttrs = $refClass->getAttributes('SPPMod\SPPView\Attributes\Title');
            if (!empty($titleAttrs)) {
                $cache['title'] = $titleAttrs[0]->newInstance()->title;
            }

            // Check for #[AllowGuest] on the class
            $cache['isAllowGuest'] = !empty($refClass->getAttributes('SPPMod\SPPView\Attributes\AllowGuest'));

            foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                $pName = $prop->getName();
                if (!empty($prop->getAttributes('SPP\Attributes\Locked'))) {
                    $cache['lockedProps'][] = $pName;
                }
                if (!empty($prop->getAttributes('SPP\Attributes\Session'))) {
                    $cache['sessionProps'][] = $pName;
                }
                if (!empty($prop->getAttributes('SPP\Attributes\Computed'))) {
                    $cache['computedProps'][] = $pName;
                }
                $valAttrs = $prop->getAttributes('SPP\Attributes\Validate');
                if (!empty($valAttrs)) {
                    $cache['validationRules'][$pName] = $valAttrs[0]->newInstance()->rules;
                }
                $urlAttrs = $prop->getAttributes('SPPMod\SPPView\Attributes\Url');
                if (!empty($urlAttrs)) {
                    $attrArgs = $urlAttrs[0]->getArguments();
                    $cache['urlProps'][$pName] = $attrArgs['as'] ?? $attrArgs[0] ?? $pName;
                }
            }

            foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $mName = $method->getName();
                $onAttrs = $method->getAttributes('SPP\Attributes\On');
                if (!empty($onAttrs)) {
                    $cache['listeners'][$mName] = $onAttrs[0]->newInstance()->event;
                }
            }
        }
        
        self::$attributeCache[$class] = $cache;
        return $cache;
    }

    public function __construct(string $id = null)
    {
        $this->id = $id ?? uniqid('live_');
    }

    // =========================================================================
    //  LIFECYCLE HOOKS — Override in subclasses
    // =========================================================================

    /**
     * Called at the start of every request (initial mount and subsequent updates).
     * Use for dependency injection or cross-cutting concerns.
     */
    public function boot(): void {}

    /**
     * Called after boot() and all trait boot methods have executed.
     */
    public function booted(): void {}

    /**
     * Called before any public property is mutated by a client update.
     *
     * @param string $name  The property being updated
     * @param mixed  $value The incoming value
     * @return void
     */
    public function updating(string $name, mixed $value): void {}

    /**
     * Called after a public property has been mutated by a client update.
     *
     * @param string $name  The property that was updated
     * @param mixed  $value The new value
     * @return void
     */
    public function updated(string $name, mixed $value): void {}

    /**
     * Called immediately before the render() method is invoked.
     */
    public function rendering(): void {}

    /**
     * Called immediately after the render() method has produced HTML.
     *
     * @param string $html The rendered HTML output
     * @return void
     */
    public function rendered(string $html): void {}

    /**
     * Called when an exception occurs during the component lifecycle.
     * Override to handle errors gracefully without crashing the component.
     *
     * @param \Throwable $e                The exception that occurred
     * @param \Closure   $stopPropagation  Call this to prevent the exception from propagating
     * @return void
     */
    public function exception(\Throwable $e, \Closure $stopPropagation): void {}

    // =========================================================================
    //  INITIAL RENDER
    // =========================================================================

    /**
     * Renders a LiveComponent from a regular PHP view (initial page load).
     *
     * Creates a new component instance, mounts it with the provided parameters,
     * dehydrates its state, signs it with an HMAC checksum, and wraps the rendered
     * HTML in a wire:id container element for the frontend to discover.
     *
     * @param string $class  Fully-qualified component class name
     * @param array  $params Initial parameters to pass to mount()
     * @return string The complete HTML with wire: attributes
     */
    public static function renderComponent(string $class, array $params = []): string
    {
        if (!class_exists($class)) {
            throw new \Exception("LiveComponent {$class} not found.");
        }

        /** @var LiveComponent $component */
        $component = new $class();
        $component->boot();
        $component->booted();
        $component->mount($params);
        $component->snapshotInitialState();

        $state = $component->dehydrate();
        $stateJson = htmlspecialchars(json_encode($state), ENT_QUOTES, 'UTF-8');
        $checksum = self::signState($state);
        $isolated = $component->isIsolated() ? ' wire:isolate' : '';

        // Check for #[Lazy]
        $isLazy = false;
        $lazyAction = '$refresh';
        
        $meta = self::getParsedAttributes($class);
        if ($meta['isLazy']) {
            $isLazy = true;
            $lazyAction = $meta['lazyAction'];
        }

        if ($isLazy && !isset($_GET['__spa'])) {
            $innerHtml = method_exists($component, 'placeholder')
                ? $component->placeholder()
                : (function() { ob_start(); include __DIR__ . '/livecomponent_placeholder.php'; return ob_get_clean(); })();
            
            $id = $component->id;
            $initAttribute = " wire:init=\"{$lazyAction}\"";
            ob_start();
            include __DIR__ . '/livecomponent_wrapper.php';
            return ob_get_clean();
        }

        // Full render
        $component->rendering();
        $html = self::resolveRenderedHtml($component);
        $component->rendered($html);

        // We wrap it in a div if it isn't already wrapped.
        $id = $component->id;
        $innerHtml = $html;
        $initAttribute = "";
        ob_start();
        include __DIR__ . '/livecomponent_wrapper.php';
        return ob_get_clean();
    }

    // =========================================================================
    //  MOUNT, HYDRATE, DEHYDRATE
    // =========================================================================

    /**
     * Mounts the component initially with default parameters.
     *
     * Hydration priority: query string (#[Url]) → session (#[Session]) → passed params.
     * Passed params take highest priority and overwrite all prior sources.
     *
     * @param array $params Key-value pairs to set on public properties
     */
    public function mount(array $params = []): void
    {
        // Hydrate from query string if supported (legacy property-based approach)
        if (property_exists($this, 'queryString') && is_array($this->queryString)) {
            foreach ($this->queryString as $key) {
                if (isset($_GET[$key]) && property_exists($this, $key)) {
                    $this->$key = $_GET[$key];
                }
            }
        }
        
        // Hydrate from #[Url] and #[Session] attributes
        $meta = self::getParsedAttributes(static::class);
        foreach ($meta['urlProps'] as $propName => $alias) {
            if (isset($_GET[$alias])) {
                $this->$propName = $_GET[$alias];
            }
        }
        foreach ($meta['sessionProps'] as $propName) {
            $key = 'spplive_session_' . static::class . '_' . $propName;
            if (isset($_SESSION[$key])) {
                $this->$propName = $_SESSION[$key];
            }
        }

        // Passed params take highest priority
        foreach ($params as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Hydrate public properties from incoming client state.
     * Only public properties are restored; protected/private are ignored for safety.
     *
     * @param array $state Key-value pairs from the client wire:state payload
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
     * Dehydrate (serialize) the component's PUBLIC properties into an array
     * suitable for JSON encoding and sending to the client.
     *
     * SECURITY: Only public properties are included. Protected/private properties,
     * internal caches, and properties in the EXCLUDED_STATE_PROPS list are never
     * sent to the client.
     *
     * Also persists #[Session]-tagged properties to $_SESSION.
     *
     * @return array The serialized public state
     */
    public function dehydrate(): array
    {
        $state = [];
        $meta = self::getParsedAttributes(static::class);
        $refClass = new ReflectionClass($this);

        foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $propName = $prop->getName();
            // Skip computed properties — they are derived, not stored
            if (in_array($propName, $meta['computedProps'])) {
                continue;
            }
            // Skip errors — they are sent separately in the response, not in state
            if ($propName === 'errors') {
                continue;
            }
            $state[$propName] = $prop->getValue($this);
        }

        // Persist #[Session] properties to PHP session
        foreach ($meta['sessionProps'] as $propName) {
            $key = 'spplive_session_' . static::class . '_' . $propName;
            $_SESSION[$key] = $this->$propName;
        }

        return $state;
    }

    /**
     * Snapshot the current public property values so reset() can restore them later.
     * Called once after mount() completes.
     */
    protected function snapshotInitialState(): void
    {
        $refClass = new ReflectionClass($this);
        foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if ($name !== 'id' && $name !== 'errors') {
                $this->_initialPropertyValues[$name] = $prop->getValue($this);
            }
        }
    }

    public function isIsolated(): bool
    {
        return self::getParsedAttributes(static::class)['isIsolated'];
    }

    public function __get(string $name)
    {
        if (array_key_exists($name, $this->computedCache)) {
            return $this->computedCache[$name];
        }
        
        if (method_exists($this, $name)) {
            $meta = self::getParsedAttributes(static::class);
            if (in_array($name, $meta['computedProps'])) {
                $val = $this->$name();
                $this->computedCache[$name] = $val;
                return $val;
            }
        }
        
        // Fallback for Livewire syntax get*Property
        $lwMethod = 'get' . ucfirst($name) . 'Property';
        if (method_exists($this, $lwMethod)) {
            $val = $this->$lwMethod();
            $this->computedCache[$name] = $val;
            return $val;
        }
        
        throw new \Exception("Property {$name} not found or is not computed.");
    }

    /**
     * Get dynamically computed properties from #[Computed] methods and get*Property() methods.
     *
     * @return array Key-value pairs of computed property names and their current values
     */
    public function getComputedProperties(): array
    {
        $computed = [];
        
        $meta = self::getParsedAttributes(static::class);
        foreach ($meta['computedProps'] as $method) {
            $computed[$method] = $this->$method();
        }
        // Legacy Livewire syntax support
        foreach (get_class_methods($this) as $methodName) {
            if (str_starts_with($methodName, 'get') && str_ends_with($methodName, 'Property') && $methodName !== 'getComputedProperties') {
                $propName = lcfirst(substr($methodName, 3, -8));
                $computed[$propName] = $this->$methodName();
            }
        }
        return $computed;
    }

    /**
     * Get URL bindings for query string sync (#[Url] attributes).
     *
     * @return array<string, string> Map of property names to URL parameter aliases
     */
    public function getUrlBindings(): array
    {
        return self::getParsedAttributes(static::class)['urlProps'];
    }

    /**
     * Parse #[On] attributes for JS event bindings.
     *
     * @return array<string, string> Map of event names to handler method names
     */
    public function getListeners(): array
    {
        return self::getParsedAttributes(static::class)['listeners'];
    }

    /**
     * Clear the computed property cache, optionally for specific properties.
     *
     * @param string ...$properties Property names to clear, or empty to clear all
     */
    public function forgetComputed(string ...$properties): void
    {
        if (empty($properties)) {
            $this->computedCache = [];
        } else {
            foreach ($properties as $prop) {
                unset($this->computedCache[$prop]);
            }
        }
    }

    // =========================================================================
    //  EVENT DISPATCHING (Livewire v3 API)
    // =========================================================================

    /**
     * Dispatch an event to other LiveComponents via SPP Live (WebSocket/SSE/AJAX).
     *
     * Returns a fluent EventDispatch object for targeting:
     *   $this->dispatch('post-saved', id: $post->id)->to(PostList::class);
     *   $this->dispatch('post-saved', id: $post->id)->self();
     *
     * @param string $event  Event name
     * @param mixed  ...$params Event data
     * @return EventDispatch Fluent dispatch object for chaining
     */
    public function dispatch(string $event, mixed ...$params): EventDispatch
    {
        $dispatch = new EventDispatch($this->id, $event, $params);
        $this->_lastDispatch = $dispatch;
        return $dispatch;
    }

    /**
     * @deprecated Use dispatch() instead. Will be removed in a future release.
     */
    protected function emit(string $event, ...$params): void
    {
        // Extract topic if the last parameter is a string starting with 'topic:'
        $topic = 'global';
        if (!empty($params) && is_string(end($params)) && str_starts_with(end($params), 'topic:')) {
            $topic = substr(array_pop($params), 6);
        }

        if (class_exists('\SPPMod\SPPLive\LiveEmitter')) {
            \SPPMod\SPPLive\LiveEmitter::emit($this->id, $event, $params, $topic);
        }
    }

    /**
     * @deprecated Use dispatch()->up() instead.
     */
    protected function emitUp(string $event, ...$params): void
    {
        $params[] = 'target:up';
        $this->emit($event, ...$params);
    }

    /**
     * @deprecated Use dispatch()->to() instead.
     */
    protected function emitTo(string $componentId, string $event, ...$params): void
    {
        $params[] = 'target:' . $componentId;
        $this->emit($event, ...$params);
    }

    // =========================================================================
    //  STREAMING
    // =========================================================================

    /**
     * Stream content to a wire:stream target element in real-time.
     * Ideal for LLM text generation, live feeds, or progressive rendering.
     *
     * @param string $to      The wire:stream target name
     * @param string $content The HTML/text content to stream
     * @param bool   $replace If true, replace existing content instead of appending
     */
    public function stream(string $to, string $content, bool $replace = false): void
    {
        $this->_pendingStreams[] = [
            'to' => $to,
            'content' => $content,
            'replace' => $replace,
        ];
    }

    // =========================================================================
    //  FILE DOWNLOADS
    // =========================================================================

    /**
     * Trigger a reactive file download on the frontend.
     *
     * @param string      $url      URL or path to the file to download
     * @param string|null $filename Override the filename for the downloaded file
     */
    public function download(string $url, string $filename = null): void
    {
        $this->download = ['url' => $url, 'name' => $filename ?? basename($url)];
    }

    // =========================================================================
    //  VALIDATION (Delegated to LiveValidatorTrait)
    // =========================================================================

    // =========================================================================
    //  STATE UTILITIES
    // =========================================================================

    /**
     * Reset specified properties (or all public properties) to their initial mount values.
     *
     * @param string ...$properties Property names to reset, or empty to reset all
     */
    public function reset(string ...$properties): void
    {
        $propsToReset = empty($properties)
            ? array_keys($this->_initialPropertyValues)
            : $properties;

        foreach ($propsToReset as $prop) {
            if (array_key_exists($prop, $this->_initialPropertyValues)) {
                $this->$prop = $this->_initialPropertyValues[$prop];
            }
        }
    }

    /**
     * Reset all properties EXCEPT the specified ones.
     *
     * @param string ...$except Property names to keep unchanged
     */
    public function resetExcept(string ...$except): void
    {
        foreach ($this->_initialPropertyValues as $prop => $value) {
            if (!in_array($prop, $except)) {
                $this->$prop = $value;
            }
        }
    }

    /**
     * Get the current value of a property and reset it to its initial value.
     *
     * @param string $property The property name
     * @return mixed The value before reset
     */
    public function pull(string $property): mixed
    {
        $value = $this->$property;
        $this->reset($property);
        return $value;
    }

    /**
     * Return only the specified properties as an associative array.
     *
     * @param string ...$properties Property names to include
     * @return array Key-value pairs
     */
    public function only(string ...$properties): array
    {
        $result = [];
        foreach ($properties as $prop) {
            if (property_exists($this, $prop)) {
                $result[$prop] = $this->$prop;
            }
        }
        return $result;
    }

    // =========================================================================
    //  RENDERING
    // =========================================================================

    /**
     * Render the component's view.
     * Inheriting classes must implement this.
     * Return a file path (e.g. 'views/counter.html') or raw HTML.
     * 
     * @return string
     */
    abstract public function render(): string;

    /**
     * Optional placeholder HTML for #[Lazy] components.
     * Override to provide a custom skeleton loader.
     *
     * @return string Placeholder HTML shown while the component loads
     */
    public function placeholder(): string
    {
        ob_start();
        include __DIR__ . '/livecomponent_placeholder.php';
        return ob_get_clean();
    }

    /**
     * Resolve the render() output into final HTML.
     * If render() returns a file path (.html, .php, .blade.php), it is compiled
     * and executed with the component's public state and computed properties.
     *
     * @param LiveComponent $component The component instance
     * @return string Resolved HTML
     */
    protected static function resolveRenderedHtml(LiveComponent $component): string
    {
        $rawHtml = $component->render();

        if (str_ends_with($rawHtml, '.html') || str_ends_with($rawHtml, '.php') || str_ends_with($rawHtml, '.blade.php')) {
            $filePath = SPP_APP_DIR . '/' . ltrim($rawHtml, '/');
            if (file_exists($filePath)) {
                $compiled = str_ends_with($rawHtml, '.html') ? ViewCompiler::compile($filePath) : $filePath;
                ob_start();
                extract($component->dehydrate());
                extract($component->getComputedProperties());
                $errors = $component->errors;
                $id = $component->id;
                include $compiled;
                return ob_get_clean();
            }
        }

        return $rawHtml;
    }

    // =========================================================================
    //  STATE SIGNING & SECURITY
    // =========================================================================

    /**
     * Generate a tamper-proof HMAC SHA-256 signature for the dehydrated state.
     *
     * Uses the application's persistent secret key (SPP_SECRET_KEY or app.secret config)
     * rather than session-based secrets to prevent false "tampering" errors when sessions expire.
     *
     * @param array $state The dehydrated state array to sign
     * @return string The HMAC SHA-256 hex digest
     */
    public static function signState(array $state): string
    {
        // Use a persistent application secret rather than a volatile session secret
        // to prevent "tampering" errors when the user's session expires (e.g. after 24 mins).
        $secret = defined('SPP_SECRET_KEY') ? SPP_SECRET_KEY : (class_exists('\SPP\Config') ? \SPP\Config::get('app.secret', 'spp-default-insecure-key') : 'spp-default-insecure-key');
        
        // Sort the state keys to ensure deterministic JSON encoding
        ksort($state);
        $payload = json_encode($state);
        return hash_hmac('sha256', $payload, $secret);
    }

    // =========================================================================
    //  REQUEST HANDLER (Server-side roundtrip)
    // =========================================================================

    /**
     * Handles an incoming component update request from the frontend.
     *
     * Full lifecycle:
     *   1. Verify state checksum (anti-tampering)
     *   2. Instantiate component, boot(), booted(), hydrate()
     *   3. Apply client updates with updating()/updated() hooks
     *   4. Validate (attribute-based or explicit)
     *   5. Execute the requested action method
     *   6. rendering() → render() → rendered()
     *   7. Dehydrate and return response payload
     *
     * @param string      $componentClass Fully-qualified component class name
     * @param array       $state          The client-side state payload
     * @param array       $updates        Property mutations from the client
     * @param string      $checksum       HMAC checksum to verify against
     * @param string|null $action         Method name to invoke, or null for model-only update
     * @param array       $params         Parameters to pass to the action method
     * @param array       $topics         Event topics for SPPLive broadcasting
     * @return array The response payload for the frontend
     */
    public static function handleRequest(string $componentClass, array $state, array $updates, string $checksum, ?string $action, array $params = [], array $topics = ['global']): array
    {
        if (!class_exists($componentClass)) {
            throw new \Exception("LiveComponent {$componentClass} not found.");
        }

        // Verify tampering on pristine state
        $expectedChecksum = self::signState($state);
        if (!hash_equals($expectedChecksum, $checksum)) {
            throw new \Exception("LiveComponent state tampering detected.");
        }

        /** @var LiveComponent $component */
        $component = new $componentClass($state['id'] ?? null);

        // Lifecycle: boot → booted → hydrate
        $component->boot();
        $component->booted();
        $component->hydrate($state);
        $component->snapshotInitialState();

        // Apply client updates safely with lifecycle hooks
        $meta = self::getParsedAttributes($componentClass);
        foreach ($updates as $key => $value) {
            if (property_exists($component, $key)) {
                if (in_array($key, $meta['lockedProps'])) {
                    throw new \Exception("Cannot update locked property: {$key}");
                }

                $refProp = new ReflectionProperty($component, $key);
                if (!$refProp->isPublic()) {
                    continue; // Silently skip non-public properties
                }

                // Fire updating hooks
                $component->updating($key, $value);
                $perPropHook = 'updating' . ucfirst($key);
                if (method_exists($component, $perPropHook)) {
                    $component->$perPropHook($value);
                }

                $component->$key = $value;

                // Fire updated hooks
                $component->updated($key, $value);
                $perPropHook = 'updated' . ucfirst($key);
                if (method_exists($component, $perPropHook)) {
                    $component->$perPropHook($value);
                }
            }
        }

        $validationRules = $meta['validationRules'];

        $flash = null;
        $stopped = false;
        $stopPropagation = function () use (&$stopped) { $stopped = true; };

        try {
            if (!empty($validationRules)) {
                if ($action) {
                    $component->validate($validationRules);
                } else {
                    $rulesForUpdates = array_intersect_key($validationRules, $updates);
                    if (!empty($rulesForUpdates)) {
                        $component->validate($rulesForUpdates);
                    }
                }
            }

            if ($action && $action !== '$refresh' && method_exists($component, $action)) {
                $refMethod = new \ReflectionMethod($component, $action);
                if (!$refMethod->isPublic()) {
                    throw new \Exception("Action {$action} is not public.");
                }
                if ($refMethod->getDeclaringClass()->getName() === self::class) {
                    throw new \Exception("Cannot execute base LiveComponent methods directly.");
                }
                if (str_starts_with($action, '__')) {
                    throw new \Exception("Cannot execute magic methods directly.");
                }
                call_user_func_array([$component, $action], $params);
            }
        } catch (ValidationException $e) {
            // Do not flash, let the errors render inline
        } catch (\Throwable $e) {
            $component->exception($e, $stopPropagation);
            if (!$stopped) {
                $flash = ['type' => 'error', 'message' => $e->getMessage()];
            }
        }

        // Check for #[Renderless] on the action method
        $isRenderless = false;
        if ($action && method_exists($component, $action)) {
            $refMethod = new \ReflectionMethod($component, $action);
            $renderlessAttrs = $refMethod->getAttributes('SPPMod\SPPView\Attributes\Renderless');
            if (!empty($renderlessAttrs)) {
                $isRenderless = true;
            }
        }

        $html = '';
        if (!$isRenderless) {
            try {
                $component->rendering();
                $html = self::resolveRenderedHtml($component);
                $component->rendered($html);
            } catch (\Throwable $e) {
                $component->exception($e, $stopPropagation);
                if (!$stopped) {
                    $flash = ['type' => 'error', 'message' => 'Render Error: ' . $e->getMessage()];
                }
            }
        }

        $newState = $component->dehydrate();

        $qsUpdates = [];
        if (property_exists($component, 'queryString') && is_array($component->queryString)) {
            foreach ($component->queryString as $prop) {
                if (property_exists($component, $prop)) {
                    $qsUpdates[$prop] = $component->$prop;
                }
            }
        }
        
        // Merge #[Url] bindings
        foreach ($component->getUrlBindings() as $prop => $alias) {
            $qsUpdates[$alias] = $newState[$prop] ?? null;
        }

        // Parse #[Title] with property interpolation
        $title = self::getParsedAttributes($componentClass)['title'];
        if ($title) {
            $title = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function($matches) use ($newState) {
                return $newState[$matches[1]] ?? $matches[0];
            }, $title);
        }

        $response = [
            'id' => $component->id,
            'state' => $newState,
            'checksum' => self::signState($newState),
            'html' => $html,
            'errors' => $component->errors,
            'queryString' => $qsUpdates,
            'isolated' => $component->isIsolated(),
            'title' => $title,
            'events' => class_exists('\\SPPMod\\SPPLive\\LiveEmitter') ? \SPPMod\SPPLive\LiveEmitter::flushEvents($topics) : [],
            'listeners' => $component->getListeners(),
        ];

        if ($flash) {
            $response['flash'] = $flash;
        }

        if ($component->download) {
            $response['download'] = $component->download;
        }

        if (!empty($component->_pendingStreams)) {
            $response['streams'] = $component->_pendingStreams;
        }

        return $response;
    }
}

// =========================================================================
//  EVENT DISPATCH FLUENT OBJECT
// =========================================================================

/**
 * Fluent event dispatch helper for LiveComponent->dispatch() calls.
 *
 * Usage:
 *   $this->dispatch('post-saved', id: 1)->to(PostList::class);
 *   $this->dispatch('post-saved', id: 1)->self();
 *   $this->dispatch('post-saved', id: 1)->up();
 */
class EventDispatch
{
    private string $componentId;
    private string $event;
    private array $params;

    public function __construct(string $componentId, string $event, array $params)
    {
        $this->componentId = $componentId;
        $this->event = $event;
        $this->params = $params;

        // Default: global dispatch
        $this->executeEmit('global');
    }

    /**
     * Target the event to all instances of a specific component class.
     */
    public function to(string $componentClassOrId): self
    {
        $this->executeEmit('target:' . $componentClassOrId);
        return $this;
    }

    /**
     * Target the event only to the originating component.
     */
    public function self(): self
    {
        $this->executeEmit('target:' . $this->componentId);
        return $this;
    }

    /**
     * Target the event to the parent component.
     */
    public function up(): self
    {
        $this->executeEmit('target:up');
        return $this;
    }

    private function executeEmit(string $target): void
    {
        $params = $this->params;
        if ($target !== 'global') {
            $params[] = $target;
        }

        if (class_exists('\SPPMod\SPPLive\LiveEmitter')) {
            \SPPMod\SPPLive\LiveEmitter::emit($this->componentId, $this->event, $params, 'global');
        }
    }
}
