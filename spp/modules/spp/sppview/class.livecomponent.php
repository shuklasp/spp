<?php
namespace SPPMod\SPPView;

use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;

class ValidationException extends \Exception {}

use SPP\Core\Interfaces\FrontendComponentInterface;

/**
 * LiveComponent - Base class for reactive SPPLive components
 */
abstract class LiveComponent implements FrontendComponentInterface
{
    public string $id;
    public array $errors = [];
    protected array $rules = [];
    protected ?array $download = null;
    protected array $computedCache = [];
    protected static array $attributeCache = [];

    public static function getParsedAttributes(string $class): array
    {
        if (isset(self::$attributeCache[$class])) {
            return self::$attributeCache[$class];
        }

        $cache = [
            'isLazy' => false,
            'lazyAction' => '$refresh',
            'isIsolated' => false,
            'title' => null,
            'lockedProps' => [],
            'sessionProps' => [],
            'urlProps' => [],
            'computedProps' => [],
            'validationRules' => [],
            'listeners' => []
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

    /**
     * Renders a LiveComponent from a regular PHP view
     */
    public static function renderComponent(string $class, array $params = []): string
    {
        if (!class_exists($class)) {
            throw new \Exception("LiveComponent {$class} not found.");
        }

        /** @var LiveComponent $component */
        $component = new $class();
        $component->mount($params);

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
            $html = '<div>Loading...</div>'; // Placeholder
            return "<div wire:id=\"{$component->id}\" wire:component=\"{$class}\" wire:state=\"{$stateJson}\" wire:checksum=\"{$checksum}\" wire:init=\"{$lazyAction}\"{$isolated}>{$html}</div>";
        }

        // Full render
        $html = '';
        $rawHtml = $component->render();
        if (str_ends_with($rawHtml, '.html') || str_ends_with($rawHtml, '.php') || str_ends_with($rawHtml, '.blade.php')) {
            $filePath = SPP_APP_DIR . '/' . ltrim($rawHtml, '/');
            if (file_exists($filePath)) {
                $compiled = str_ends_with($rawHtml, '.html') ? ViewCompiler::compile($filePath) : $filePath;
                ob_start();
                extract($component->dehydrate());
                extract($component->getComputedProperties());
                include $compiled;
                $html = ob_get_clean();
            }
        } else {
            $html = $rawHtml;
        }

        // We wrap it in a div if it isn't already wrapped, but standard Livewire requires user to return a single root element.
        // For safety, we will just return the wrapper div around it. Or inject into the first root element.
        // Injecting into root element via regex is tricky. Wrapping is safer.
        return "<div wire:id=\"{$component->id}\" wire:component=\"{$class}\" wire:state=\"{$stateJson}\" wire:checksum=\"{$checksum}\"{$isolated}>{$html}</div>";
    }

    /**
     * Mounts the component initially with default parameters
     */
    public function mount(array $params = []): void
    {
        // Hydrate from query string if supported
        if (property_exists($this, 'queryString') && is_array($this->queryString)) {
            foreach ($this->queryString as $key) {
                if (isset($_GET[$key]) && property_exists($this, $key)) {
                    $this->$key = $_GET[$key];
                }
            }
        }
        
        // Hydrate from #[Url] and #[Session]
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

    public function dehydrate(): array
    {
        $state = [];
        $meta = self::getParsedAttributes(static::class);
        foreach (get_object_vars($this) as $propName => $val) {
            $state[$propName] = $val;
        }
        foreach ($meta['sessionProps'] as $propName) {
            $key = 'spplive_session_' . static::class . '_' . $propName;
            $_SESSION[$key] = $this->$propName;
        }
        return $state;
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
     * Get dynamically computed properties from get*Property() methods
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
     * Get URL bindings for query string sync
     */
    public function getUrlBindings(): array
    {
        return self::getParsedAttributes(static::class)['urlProps'];
    }

    /**
     * Parse #[On] attributes for JS event bindings
     */
    public function getListeners(): array
    {
        return self::getParsedAttributes(static::class)['listeners'];
    }

    /**
     * Dispatches an event over SPP Live (WebSocket/SSE) or SPP Ajax (Fallback)
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

    protected function emitUp(string $event, ...$params): void
    {
        $params[] = 'target:up';
        $this->emit($event, ...$params);
    }

    protected function emitTo(string $componentId, string $event, ...$params): void
    {
        $params[] = 'target:' . $componentId;
        $this->emit($event, ...$params);
    }

    /**
     * Trigger a reactive file download
     */
    public function download(string $url, string $filename = null): void
    {
        $this->download = ['url' => $url, 'name' => $filename ?? basename($url)];
    }

    /**
     * Validate public properties against rules
     */
    public function validate(array $rules = null): void
    {
        $this->errors = [];
        $rulesToUse = $rules ?? $this->rules;
        
        foreach ($rulesToUse as $field => $ruleString) {
            $value = property_exists($this, $field) ? $this->$field : null;
            $rulesArr = explode('|', $ruleString);
            
            foreach ($rulesArr as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $this->errors[$field] = ucfirst($field) . " is required.";
                    break;
                }
                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = ucfirst($field) . " must be a valid email address.";
                    break;
                }
                if ($rule === 'numeric' && !is_numeric($value)) {
                    $this->errors[$field] = ucfirst($field) . " must be a number.";
                    break;
                }
                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (is_string($value) && strlen($value) < $min) {
                        $this->errors[$field] = ucfirst($field) . " must be at least {$min} characters.";
                    } elseif (is_numeric($value) && $value < $min) {
                        $this->errors[$field] = ucfirst($field) . " must be at least {$min}.";
                    }
                }
                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (is_string($value) && strlen($value) > $max) {
                        $this->errors[$field] = ucfirst($field) . " must not exceed {$max} characters.";
                    } elseif (is_numeric($value) && $value > $max) {
                        $this->errors[$field] = ucfirst($field) . " must not exceed {$max}.";
                    }
                }
            }
        }
        
        if (!empty($this->errors)) {
            throw new ValidationException("Validation failed.");
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
        // Use a persistent application secret rather than a volatile session secret
        // to prevent "tampering" errors when the user's session expires (e.g. after 24 mins).
        $secret = defined('SPP_SECRET_KEY') ? SPP_SECRET_KEY : (class_exists('\SPP\Config') ? \SPP\Config::get('app.secret', 'spp-default-insecure-key') : 'spp-default-insecure-key');
        
        // Sort the state keys to ensure deterministic JSON encoding
        ksort($state);
        $payload = json_encode($state);
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Handles an incoming component update request
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
        $component->hydrate($state);

        // Apply client updates safely
        $meta = self::getParsedAttributes($componentClass);
        foreach ($updates as $key => $value) {
            if (property_exists($component, $key)) {
                if (in_array($key, $meta['lockedProps'])) {
                    throw new \Exception("Cannot update locked property: {$key}");
                }
                $component->$key = $value;
            }
        }

        $validationRules = $meta['validationRules'];

        $flash = null;
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

            if ($action && method_exists($component, $action)) {
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
        } catch (\Exception $e) {
            $flash = ['type' => 'error', 'message' => $e->getMessage()];
        }

        $html = '';
        try {
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
                    extract($component->getComputedProperties());
                    include $compiled;
                    $html = ob_get_clean();
                }
            }
        } catch (\Exception $e) {
            $flash = ['type' => 'error', 'message' => 'Render Error: ' . $e->getMessage()];
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

        // Parse #[Title]
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
            'queryString' => $qsUpdates,
            'isolated' => $component->isIsolated(),
            'title' => $title,
            'events' => class_exists('\\SPPMod\\SPPLive\\LiveEmitter') ? \SPPMod\SPPLive\LiveEmitter::flushEvents($topics) : [],
            'listeners' => $component->getListeners()
        ];

        if ($flash) {
            $response['flash'] = $flash;
        }

        if ($component->download) {
            $response['download'] = $component->download;
        }

        return $response;
    }
}
