<?php

namespace SPP;

if (!class_exists('\SPP\SPPObject', false)) {
    $sppObj = __DIR__ . '/class.sppobject.php';
    if (file_exists($sppObj)) {
        require_once $sppObj;
    }
}
if (!class_exists('\\SPP\\Event\\EventRecord', false)) {
    $recFile = __DIR__ . '/class.eventrecord.php';
    if (file_exists($recFile)) {
        require_once $recFile;
    }
}
if (!class_exists('\\SPP\\Event\\EventBuilder', false)) {
    $builderFile = __DIR__ . '/class.eventbuilder.php';
    if (file_exists($builderFile)) {
        require_once $builderFile;
    }
}
if (!class_exists('\\SPP\\Event\\CompoundTrigger', false)) {
    $compoundFile = __DIR__ . '/class.compoundtrigger.php';
    if (file_exists($compoundFile)) {
        require_once $compoundFile;
    }
}
if (!class_exists('\\SPP\\Exceptions\\EventAbortedException', false)) {
    $abortFile = __DIR__ . '/exceptions/EventAbortedException.php';
    if (file_exists($abortFile)) {
        require_once $abortFile;
    }
}

/**
 * class \SPP\SPPEvent
 * Modern, fast array-based pub/sub routing for the Event System.
 * Enhanced with sovereign SPP Enterprise Triggers, state pseudorecords, WHEN clauses, Compound Triggers, and transactional hooks.
 *
 * @author Satya Prakash Shukla
 */
class SPPEvent extends \SPP\SPPObject
{
    protected static array $listeners = [];
    protected static array $eventDefinitions = [];
    protected static array $collectedTrace = [];
    protected static bool $booted = false;
    protected static array $activeEventStack = [];
    protected static int $maxRecursionDepth = 25;
    protected static int $maxSameEventRecursion = 5;
    protected static ?object $fake = null;
    protected static array $recentlyDispatched = [];
    protected static array $afterCommitCallbacks = [];
    protected static array $onRollbackCallbacks = [];
    protected static int $transactionDepth = 0;

    public static function setFake(?object $fake): void
    {
        self::$fake = $fake;
    }

    public static function getFake(): ?object
    {
        return self::$fake;
    }

    private function __construct() {}

    /**
     * Boot the event system by loading explicit definitions from events.yml 
     * in core, app, and modules.
     */
    public static function boot()
    {
        if (self::$booted) return;
        self::$booted = true;

        // Try to load cached events
        $cacheFile = (defined('SPP_APP_DIR') ? SPP_APP_DIR : SPP_BASE_DIR) . SPP_DS . 'var' . SPP_DS . 'cache' . SPP_DS . 'events_compiled.php';
        if (file_exists($cacheFile)) {
            $data = require $cacheFile;
            $cachedListeners = $data['listeners'] ?? [];
            foreach ($cachedListeners as $evt => $handlers) {
                if (!isset(self::$listeners[$evt])) {
                    self::$listeners[$evt] = [];
                }
                self::$listeners[$evt] = array_merge(self::$listeners[$evt], $handlers);
                usort(self::$listeners[$evt], function($a, $b) {
                    return $b['priority'] <=> $a['priority'];
                });
            }
            self::$eventDefinitions = array_merge(self::$eventDefinitions, $data['definitions'] ?? []);
        }

        $appContext = \SPP\Scheduler::getContext() ?: 'default';
        $modCacheFile = (defined('SPP_APP_DIR') ? SPP_APP_DIR : SPP_BASE_DIR) . SPP_DS . 'var' . SPP_DS . 'cache' . SPP_DS . 'events_' . $appContext . '.php';
        if (file_exists($modCacheFile)) {
            $modEvents = require $modCacheFile;
            foreach ($modEvents as $evt => $handlers) {
                if (!isset(self::$listeners[$evt])) {
                    self::$listeners[$evt] = [];
                }
                foreach ($handlers as $handlerInfo) {
                    self::$listeners[$evt][] = [
                        'callback' => $handlerInfo['handler'],
                        'priority' => 10, // Default priority for module events
                        'id' => uniqid()
                    ];
                }
                usort(self::$listeners[$evt], function($a, $b) {
                    return $b['priority'] <=> $a['priority'];
                });
            }
        }
        
        if (file_exists($cacheFile)) {
            if (isset($data['triggers'])) {
                foreach ($data['triggers'] as $triggerClass) {
                    if (class_exists($triggerClass, true)) {
                        $triggerClass::register();
                    }
                }
            } else {
                self::scanTriggers();
            }
            return;
        }

        // We only scan explicitly known yml files, NO SCANDIR loops over class files!
        self::parseEventsYml(SPP_BASE_DIR . SPP_DS . 'etc' . SPP_DS . 'events.yml');
        self::parseEventsYml((defined('SPP_APP_DIR') ? SPP_APP_DIR : SPP_BASE_DIR) . SPP_DS . 'etc' . SPP_DS . 'events.yml');

        // Check active context
        $context = \SPP\Scheduler::getContext();
        if ($context !== '') {
            $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : SPP_BASE_DIR;
            self::parseEventsYml($appDir . SPP_DS . 'src' . SPP_DS . $context . SPP_DS . 'etc' . SPP_DS . 'events.yml');
        }

        // Modules
        $mods = \SPP\Registry::get('__mods');
        if (is_array($mods)) {
            foreach ($mods as $modname => $modpath) {
                self::parseEventsYml($modpath . SPP_DS . 'etc' . SPP_DS . 'events.yml');
                if (file_exists($modpath . SPP_DS . 'events.yml')) {
                    self::parseEventsYml($modpath . SPP_DS . 'events.yml');
                }
            }
        }

        self::scanAttributes();
        $discoveredTriggers = self::scanTriggers();

        // Save cache atomically
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $cacheableListeners = [];
        foreach (self::$listeners as $evt => $handlers) {
            foreach ($handlers as $h) {
                $cb = $h['callback'] ?? null;
                if ($cb instanceof \Closure) {
                    continue;
                }
                if (is_array($cb) && count($cb) === 2 && is_object($cb[0])) {
                    // Normalize object instance callback to class string for safe var_export caching
                    $h['callback'] = [get_class($cb[0]), $cb[1]];
                } elseif (is_object($cb)) {
                    continue;
                }
                $cacheableListeners[$evt][] = $h;
            }
        }
        $export = var_export([
            'listeners' => $cacheableListeners,
            'definitions' => self::$eventDefinitions,
            'triggers' => $discoveredTriggers
        ], true);
        $tmpFile = $cacheFile . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmpFile, "<?php\nreturn " . $export . ";\n", LOCK_EX) !== false) {
            @rename($tmpFile, $cacheFile);
        }
    }

    private static function scanAttributes()
    {
        if (PHP_VERSION_ID < 80000) return;

        $dirsToScan = [];
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : SPP_BASE_DIR;
        if (is_dir($appDir . SPP_DS . 'src')) $dirsToScan[] = $appDir . SPP_DS . 'src';
        
        $mods = \SPP\Registry::get('__mods');
        if (is_array($mods)) {
            foreach ($mods as $modpath) {
                if (is_dir($modpath)) $dirsToScan[] = $modpath;
            }
        }

        foreach ($dirsToScan as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    if (str_contains($content, '#[On')) {
                        self::parseFileForOnAttribute($content);
                    }
                }
            }
        }
    }

    private static function parseFileForOnAttribute(string $content)
    {
        $namespace = '';
        if (preg_match('/namespace\s+([^;{]+)[;{]/i', $content, $nsMatch)) {
            $namespace = trim($nsMatch[1]) . '\\';
        }

        if (preg_match('/class\s+([a-zA-Z0-9_]+)/i', $content, $clsMatch)) {
            $className = $namespace . trim($clsMatch[1]);
            if (class_exists($className, true)) {
                // Skip Frontend UI Components to prevent them from being instantiated on backend server events
                if (is_subclass_of($className, '\\SPP\\Core\\Interfaces\\FrontendComponentInterface')) return;
                
                try {
                    $ref = new \ReflectionClass($className);
                    foreach ($ref->getMethods() as $method) {
                        $attributes = $method->getAttributes('SPP\Attributes\On');
                        foreach ($attributes as $attr) {
                            $instance = $attr->newInstance();
                            self::listen($instance->event, [$className, $method->getName()], false, $instance->priority);
                        }
                    }
                } catch (\Throwable $e) {}
            }
        }
    }

    /**
     * Convention auto-discovery for sovereign enterprise triggers extending CompoundTrigger.
     * Scans src/{app}/Triggers/ and src/Triggers/ for *Trigger.php files and auto-registers them.
     *
     * @return array List of discovered trigger class names
     */
    public static function scanTriggers(): array
    {
        $discovered = [];
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : SPP_BASE_DIR;
        $searchDirs = [];

        $srcDir = $appDir . SPP_DS . 'src';
        if (is_dir($srcDir)) {
            $searchDirs[] = $srcDir;
        }

        $mods = \SPP\Registry::get('__mods');
        if (is_array($mods)) {
            foreach ($mods as $modpath) {
                if (is_dir($modpath)) {
                    $searchDirs[] = $modpath;
                }
            }
        }

        foreach ($searchDirs as $dir) {
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile() && str_ends_with($file->getFilename(), 'Trigger.php')) {
                        $pathname = $file->getPathname();
                        require_once $pathname;
                        $className = self::resolveClassFromFile($pathname);
                        if ($className && class_exists($className, false)) {
                            if (is_subclass_of($className, '\\SPP\\Event\\CompoundTrigger') || is_subclass_of($className, '\\SPP\\CompoundTrigger')) {
                                $className::register();
                                $discovered[] = $className;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        return array_values(array_unique($discovered));
    }

    private static function resolveClassFromFile(string $filePath): ?string
    {
        $content = @file_get_contents($filePath);
        if (!$content) return null;

        $namespace = '';
        if (preg_match('/namespace\s+([^;{]+)[;{]/i', $content, $nsMatch)) {
            $namespace = trim($nsMatch[1]) . '\\';
        }

        if (preg_match('/class\s+([a-zA-Z0-9_]+)/i', $content, $clsMatch)) {
            return $namespace . trim($clsMatch[1]);
        }

        return null;
    }

    private static function parseEventsYml(string $ymlFile)
    {
        if (!file_exists($ymlFile)) return;

        $parsed = @\Symfony\Component\Yaml\Yaml::parseFile($ymlFile);
        if (is_array($parsed) && isset($parsed['events']) && is_array($parsed['events'])) {
            foreach ($parsed['events'] as $evtName => $eventData) {
                // If it's a complex definition object
                if (is_array($eventData) && (isset($eventData['listeners']) || isset($eventData['overridable']) || isset($eventData['default_handler']))) {
                    
                    if (isset($eventData['overridable']) || isset($eventData['default_handler'])) {
                        self::defineEvent($evtName, $eventData['default_handler'] ?? null, $eventData['overridable'] ?? false);
                    }
                    
                    $handlersList = $eventData['listeners'] ?? [];
                } else {
                    // Backwards compatible simple list of handlers
                    $handlersList = $eventData;
                }

                if (is_array($handlersList)) {
                    foreach ($handlersList as $handlerData) {
                        if (is_array($handlerData) && isset($handlerData['class'])) {
                            self::registerHandler($evtName, $handlerData['class'], false, $handlerData['priority'] ?? 500);
                        } else {
                            self::registerHandler($evtName, $handlerData);
                        }
                    }
                } elseif (is_string($handlersList)) {
                    self::registerHandler($evtName, $handlersList);
                }
            }
        }
    }

    /**
     * Normalizes event identifiers to ensure seamless interoperability between
     * dot notation ('workflow.before_transition') and colon notation ('workflow:before_transition').
     */
    public static function normalizeEventName(string $event): string
    {
        return str_replace(':', '.', trim($event));
    }

    /**
     * Define an overridable event
     */
    public static function defineEvent(string $event, $defaultHandler = null, bool $overridable = false): void
    {
        $def = [
            'default_handler' => $defaultHandler,
            'overridable' => $overridable,
        ];
        self::$eventDefinitions[$event] = $def;
        $norm = self::normalizeEventName($event);
        if ($norm !== $event) {
            self::$eventDefinitions[$norm] = $def;
        }
    }

    /**
     * Fluent entry point returning an EventBuilder (declarative WHEN clauses, once, priority, etc.).
     */
    public static function on(string $event): \SPP\Event\EventBuilder
    {
        return new \SPP\Event\EventBuilder($event);
    }

    /**
     * Registers a one-time event listener that automatically unbinds after first execution.
     */
    public static function once(string $event, $callback, int $priority = 500): string
    {
        return self::on($event)->priority($priority)->once()->handle($callback);
    }

    /**
     * Unregisters all listeners for a given event name.
     */
    public static function forget(string $event): void
    {
        $norm = self::normalizeEventName($event);
        unset(self::$listeners[$norm]);
        if ($norm !== $event) {
            unset(self::$listeners[$event]);
        }
    }

    /**
     * Unregisters a specific listener by its ID.
     */
    public static function forgetListener(string $event, string $id): void
    {
        $norm = self::normalizeEventName($event);
        if (isset(self::$listeners[$norm])) {
            self::$listeners[$norm] = array_values(array_filter(self::$listeners[$norm], fn($h) => ($h['id'] ?? '') !== $id));
        }
        if ($norm !== $event && isset(self::$listeners[$event])) {
            self::$listeners[$event] = array_values(array_filter(self::$listeners[$event], fn($h) => ($h['id'] ?? '') !== $id));
        }
    }

    /**
     * Explicit hook registration with automatic normalization and deduplication.
     */
    public static function listen(string $event, $callback, bool $isOverride = false, int $priority = 500): void
    {
        self::listenAdvanced($event, $callback, $isOverride, $priority);
    }

    /**
     * Advanced hook registration supporting declarative WHEN conditions and single-use execution.
     *
     * @return string Unique listener registration ID
     */
    public static function listenAdvanced(
        string $event,
        $callback,
        bool $isOverride = false,
        int $priority = 500,
        ?callable $condition = null,
        bool $once = false
    ): string {
        $norm = self::normalizeEventName($event);
        $id = uniqid('l_', true);

        if ($isOverride) {
            $def = self::$eventDefinitions[$event] ?? (self::$eventDefinitions[$norm] ?? null);
            if ($def && !$def['overridable']) {
                throw new \SPP\SPPException("Event {$event} is not overridable.");
            }
            $overrideEntry = [['callback' => $callback, 'priority' => $priority, 'id' => $id, 'condition' => $condition, 'once' => $once]];
            self::$listeners[$event] = $overrideEntry;
            if ($norm !== $event) {
                self::$listeners[$norm] = $overrideEntry;
            }
            return $id;
        }

        $entry = ['callback' => $callback, 'priority' => $priority, 'id' => $id, 'condition' => $condition, 'once' => $once];

        if (!isset(self::$listeners[$norm])) {
            self::$listeners[$norm] = [];
        }
        self::$listeners[$norm][] = $entry;
        usort(self::$listeners[$norm], fn($a, $b) => ($b['priority'] ?? 500) <=> ($a['priority'] ?? 500));

        if ($event !== $norm) {
            if (!isset(self::$listeners[$event])) {
                self::$listeners[$event] = [];
            }
            self::$listeners[$event][] = $entry;
            usort(self::$listeners[$event], fn($a, $b) => ($b['priority'] ?? 500) <=> ($a['priority'] ?? 500));
        }

        return $id;
    }

    /**
     * Dispatches an event and returns the first non-null response (short-circuiting).
     */
    public static function until(string $eventName, mixed $params = null): mixed
    {
        if ($params === null) {
            $params = new \SPP\EventParams();
        } elseif (is_array($params)) {
            $params = new \SPP\EventParams($params);
        }

        $listeners = self::getListenersForEvent($eventName);
        foreach ($listeners as $listener) {
            if (!empty($listener['condition']) && is_callable($listener['condition'])) {
                try {
                    if (!call_user_func($listener['condition'], $params)) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            if (!empty($listener['once']) && !empty($listener['id'])) {
                self::forgetListener($eventName, $listener['id']);
            }

            $callback = self::resolveCallableHandler($listener['callback']);
            if (is_callable($callback)) {
                $res = call_user_func_array($callback, [&$params]);
                if ($res !== null) {
                    return $res;
                }
            }

            if ($params instanceof \SPP\EventParams && $params->isPropagationStopped()) {
                break;
            }
        }
        return null;
    }

    /**
     * Executes a callback within an isolated error-suppressed sandbox (autonomous transaction pattern).
     * Ensures secondary auditing/telemetry cannot abort the primary business transaction.
     */
    public static function autonomous(callable $callback): mixed
    {
        try {
            return call_user_func($callback);
        } catch (\Throwable $e) {
            error_log("[SPPEvent Autonomous Error] Exception during autonomous execution: " . $e->getMessage());
            if (defined('SPP_DEBUG') && SPP_DEBUG) {
                $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
                @file_put_contents($logDir . '/events.log', "[" . date('Y-m-d H:i:s') . "] [AUTONOMOUS_EXCEPTION] " . $e->getMessage() . "\n", FILE_APPEND);
            }
            return null;
        }
    }

    /**
     * Registers a callback to execute after the active DB transaction successfully commits.
     * If no transaction is active, executes immediately.
     */
    public static function afterCommit(callable $callback): void
    {
        if (self::$transactionDepth === 0) {
            call_user_func($callback);
            return;
        }
        self::$afterCommitCallbacks[] = $callback;
    }

    /**
     * Registers a callback to execute if the active DB transaction rolls back.
     */
    public static function onRollback(callable $callback): void
    {
        if (self::$transactionDepth === 0) {
            return;
        }
        self::$onRollbackCallbacks[] = $callback;
    }

    public static function recordBeginTransaction(): void
    {
        self::$transactionDepth++;
    }

    public static function recordCommit(): void
    {
        if (self::$transactionDepth > 0) {
            self::$transactionDepth--;
        }
        if (self::$transactionDepth === 0) {
            $callbacks = self::$afterCommitCallbacks;
            self::$afterCommitCallbacks = [];
            self::$onRollbackCallbacks = [];
            foreach ($callbacks as $cb) {
                try {
                    call_user_func($cb);
                } catch (\Throwable $e) {
                    error_log("[SPPEvent Error] afterCommit callback failed: " . $e->getMessage());
                }
            }
        }
    }

    public static function recordRollback(): void
    {
        self::$transactionDepth = 0;
        self::$afterCommitCallbacks = [];
        $callbacks = self::$onRollbackCallbacks;
        self::$onRollbackCallbacks = [];
        foreach ($callbacks as $cb) {
            try {
                call_user_func($cb);
            } catch (\Throwable $e) {
                error_log("[SPPEvent Error] onRollback callback failed: " . $e->getMessage());
            }
        }
    }

    public static function getTransactionDepth(): int
    {
        return self::$transactionDepth;
    }

    /**
     * Dispatches an event to the background queue or DAG orchestrator if available.
     */
    public static function queue(string $eventName, mixed $payload = []): bool
    {
        if (class_exists('\\SPPMod\\SPPQueue\\SppQueue')) {
            $data = ($payload instanceof \SPP\EventParams) ? $payload->getPayload() : $payload;
            return (bool)\SPPMod\SPPQueue\SppQueue::push('event_dispatch', [
                'event' => $eventName,
                'payload' => $data,
                'queued_at' => microtime(true)
            ]);
        }
        self::fireEvent($eventName, $payload);
        return true;
    }

    /**
     * Auto-instantiates and resolves callable handlers.
     */
    public static function resolveCallableHandler(mixed $callback): mixed
    {
        if (is_string($callback)) {
            if (class_exists($callback)) {
                if (method_exists($callback, '__invoke')) {
                    return [new $callback, '__invoke'];
                } elseif (method_exists($callback, 'handle')) {
                    return [new $callback, 'handle'];
                }
            } elseif (class_exists('\\EventHandlers\\Defaults\\' . $callback)) {
                $className = '\\EventHandlers\\Defaults\\' . $callback;
                if (method_exists($className, '__invoke')) {
                    return [new $className, '__invoke'];
                } elseif (method_exists($className, 'handle')) {
                    return [new $className, 'handle'];
                }
            }
        }

        if (is_array($callback) && is_string($callback[0])) {
            if (class_exists($callback[0])) {
                try {
                    $refMethod = new \ReflectionMethod($callback[0], $callback[1]);
                    if (!$refMethod->isStatic()) {
                        $callback[0] = new $callback[0]();
                    }
                } catch (\ReflectionException $e) {}
            } elseif (class_exists('\\EventHandlers\\Defaults\\' . $callback[0])) {
                $className = '\\EventHandlers\\Defaults\\' . $callback[0];
                $callback[0] = $className;
                try {
                    $refMethod = new \ReflectionMethod($className, $callback[1]);
                    if (!$refMethod->isStatic()) {
                        $callback[0] = new $className();
                    }
                } catch (\ReflectionException $e) {}
            }
        }

        return $callback;
    }

    /**
     * Registers a subscriber class or instance implementing getSubscribedEvents().
     */
    public static function registerSubscriber(string|object $subscriber): void
    {
        $className = is_object($subscriber) ? get_class($subscriber) : $subscriber;
        if (!class_exists($className)) {
            return;
        }

        if (!method_exists($className, 'getSubscribedEvents')) {
            return;
        }

        $events = $className::getSubscribedEvents();
        if (!is_array($events)) {
            return;
        }

        $targetObj = is_object($subscriber) ? $subscriber : null;
        if ($targetObj === null) {
            try {
                $refClass = new \ReflectionClass($className);
                if ($refClass->isInstantiable()) {
                    $targetObj = new $className();
                }
            } catch (\Throwable $e) {}
        }

        foreach ($events as $eventName => $params) {
            $normEvent = self::normalizeEventName((string)$eventName);
            if (is_string($params)) {
                $cb = $targetObj !== null ? [$targetObj, $params] : [$className, $params];
                self::listen($normEvent, $cb, false, 500);
            } elseif (is_array($params)) {
                if (isset($params[0]) && is_string($params[0]) && (!isset($params[1]) || is_numeric($params[1]))) {
                    $method = $params[0];
                    $priority = isset($params[1]) ? (int)$params[1] : 500;
                    $cb = $targetObj !== null ? [$targetObj, $method] : [$className, $method];
                    self::listen($normEvent, $cb, false, $priority);
                } else {
                    foreach ($params as $item) {
                        if (is_array($item)) {
                            $method = $item[0];
                            $priority = isset($item[1]) ? (int)$item[1] : 500;
                            $cb = $targetObj !== null ? [$targetObj, $method] : [$className, $method];
                            self::listen($normEvent, $cb, false, $priority);
                        } elseif (is_string($item)) {
                            $cb = $targetObj !== null ? [$targetObj, $item] : [$className, $item];
                            self::listen($normEvent, $cb, false, 500);
                        }
                    }
                }
            }
        }
    }

    private static function getCallbackKey(array $listener): string
    {
        $cb = $listener['callback'] ?? null;
        if (is_string($cb)) {
            return $cb;
        }
        if (is_array($cb)) {
            $prefix = is_object($cb[0]) ? get_class($cb[0]) : (string)$cb[0];
            return $prefix . '::' . ($cb[1] ?? '__invoke');
        }
        if ($cb instanceof \Closure) {
            return 'closure_' . spl_object_hash($cb);
        }
        return $listener['id'] ?? ('cb_' . bin2hex(random_bytes(6)));
    }

    /**
     * Resolves all matching listeners for a given event, supporting exact matches,
     * normalized aliases, hook prefixes, and wildcard patterns ('workflow.*', 'order.*').
     */
    public static function getListenersForEvent(string $eventName): array
    {
        $norm = self::normalizeEventName($eventName);
        $matched = [];
        $seen = [];

        // 1. Direct match on normalized name
        if (!empty(self::$listeners[$norm])) {
            foreach (self::$listeners[$norm] as $h) {
                $cbKey = self::getCallbackKey($h);
                if (!isset($seen[$cbKey])) {
                    $matched[] = $h;
                    $seen[$cbKey] = true;
                }
            }
        }

        // 2. Direct match on raw eventName
        if ($eventName !== $norm && !empty(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $h) {
                $cbKey = self::getCallbackKey($h);
                if (!isset($seen[$cbKey])) {
                    $matched[] = $h;
                    $seen[$cbKey] = true;
                }
            }
        }

        // 3. Match hook prefixes (hook.X vs X)
        if (str_starts_with($norm, 'hook.')) {
            $bare = substr($norm, 5);
            if (!empty(self::$listeners[$bare])) {
                foreach (self::$listeners[$bare] as $h) {
                    $cbKey = self::getCallbackKey($h);
                    if (!isset($seen[$cbKey])) {
                        $matched[] = $h;
                        $seen[$cbKey] = true;
                    }
                }
            }
        } else {
            $hookPrefixed = 'hook.' . $norm;
            if (!empty(self::$listeners[$hookPrefixed])) {
                foreach (self::$listeners[$hookPrefixed] as $h) {
                    $cbKey = self::getCallbackKey($h);
                    if (!isset($seen[$cbKey])) {
                        $matched[] = $h;
                        $seen[$cbKey] = true;
                    }
                }
            }
        }

        // 4. Wildcard pattern matching (e.g. 'workflow.*', 'order.*')
        foreach (self::$listeners as $pattern => $handlers) {
            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace(['.', '*'], ['\.', '.*'], $pattern) . '$/i';
                if (preg_match($regex, $norm) || preg_match($regex, $eventName)) {
                    foreach ($handlers as $h) {
                        $cbKey = self::getCallbackKey($h);
                        if (!isset($seen[$cbKey])) {
                            $matched[] = $h;
                            $seen[$cbKey] = true;
                        }
                    }
                }
            }
        }

        usort($matched, fn($a, $b) => ($b['priority'] ?? 500) <=> ($a['priority'] ?? 500));
        return $matched;
    }

    /**
     * Legacy registration wrapper with automatic subscriber detection.
     */
    public static function registerHandler(string $event_name, string $handler_name, bool $default = false, int $priority = 500)
    {
        $className = $handler_name;
        if (!class_exists($className)) {
            if (class_exists('EventHandlers\\' . $handler_name)) {
                $className = 'EventHandlers\\' . $handler_name;
            } elseif (class_exists('EventHandlers\\Defaults\\' . $handler_name)) {
                $className = 'EventHandlers\\Defaults\\' . $handler_name;
            }
        }

        // If the class is a subscriber, wire all its subscriptions directly
        if (class_exists($className) && method_exists($className, 'getSubscribedEvents')) {
            self::registerSubscriber($className);
            return true;
        }

        // For backwards compatibility, if they pass a class name, we assume it's an EventHandler 
        // that handles its own registration via initHandler(), so we instantiate it ONCE.
        if (class_exists($className) && is_subclass_of($className, '\\SPP\\EventHandler')) {
            $instance = new $className($event_name, $default);
            return true;
        }
        
        // If it's a string but not an EventHandler class, we register it as a callable fallback
        if (!$default) {
            self::listen($event_name, $className, false, $priority);
        } else {
            self::defineEvent($event_name, $className, true);
        }
        return true;
    }

    /**
     * Trigger explicit before hooks
     */
    public static function startEvent(string $event_name, \SPP\EventParams $params)
    {
        self::triggerHook('before_' . $event_name, $params);
    }

    /**
     * Trigger explicit after hooks
     */
    public static function endEvent(string $event_name, \SPP\EventParams $params)
    {
        self::triggerHook('after_' . $event_name, $params);
    }

    /**
     * Dispatch an event.
     */
    public static function fireEvent($event_name, \SPP\EventParams|array $params, callable|string|array|null $inline_handler = null)
    {
        if (is_array($params)) {
            $params = new \SPP\EventParams($params);
        }

        $dedupKey = self::normalizeEventName((string)$event_name) . ':' . spl_object_hash($params);
        self::$recentlyDispatched[$dedupKey] = microtime(true);

        if (self::$fake !== null && method_exists(self::$fake, 'record')) {
            self::$fake->record($event_name, $params);
            if (method_exists(self::$fake, 'shouldPreventExecution') && self::$fake->shouldPreventExecution($event_name)) {
                return;
            }
        }

        static $shutdownRegistered = false;
        if (!$shutdownRegistered) {
            register_shutdown_function([__CLASS__, 'persistTrace']);
            $shutdownRegistered = true;
        }

        // Infinite recursion & cyclic event loop detection guard
        $sameEventCount = 0;
        foreach (self::$activeEventStack as $activeEvt) {
            if ($activeEvt === $event_name) {
                $sameEventCount++;
            }
        }

        if ($sameEventCount >= self::$maxSameEventRecursion || count(self::$activeEventStack) >= self::$maxRecursionDepth) {
            $stackTraceStr = implode(' -> ', self::$activeEventStack) . ' -> ' . $event_name;
            error_log("[SPPEvent WARNING] Suppressed potential infinite event loop: [{$stackTraceStr}]");
            if (defined('SPP_DEBUG') && SPP_DEBUG) {
                $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
                @file_put_contents($logDir . '/events.log', "[" . date('Y-m-d H:i:s') . "] [RECURSION_SUPPRESSED] {$stackTraceStr}\n", FILE_APPEND);
            }
            return;
        }

        self::$activeEventStack[] = $event_name;
        try {
            $eventTrace = [
                'event' => $event_name,
                'timestamp' => microtime(true),
                'handlers' => []
            ];

            if (defined('SPP_DEBUG') && SPP_DEBUG) {
                $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
                if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
                @file_put_contents($logDir . '/events.log', "[" . date('Y-m-d H:i:s') . "] Firing event: [{$event_name}]\n", FILE_APPEND);
            }

            self::triggerHookWithTrace('before_' . $event_name, $params, $eventTrace, 'before');

        if (!$params->isPropagationStopped()) {
            if (!empty(self::$listeners['instead_' . $event_name])) {
                self::triggerHookWithTrace('instead_' . $event_name, $params, $eventTrace, 'instead');
            } else {
                if ($inline_handler !== null) {
                    $hStart = microtime(true);
                    $callback = $inline_handler;
                    $hName = 'Inline Handler';
                    if (is_string($callback)) {
                        $hName = $callback;
                        if (class_exists($callback)) {
                            $callback = [new $callback, '__invoke'];
                        } elseif (class_exists('\\EventHandlers\\Defaults\\' . $callback)) {
                            $className = '\\EventHandlers\\Defaults\\' . $callback;
                            $callback = [new $className, '__invoke'];
                        }
                    } elseif (is_array($callback) && is_string($callback[0])) {
                        $hName = $callback[0] . '::' . ($callback[1] ?? '');
                        if (class_exists($callback[0])) {
                            try {
                                $refMethod = new \ReflectionMethod($callback[0], $callback[1]);
                                if (!$refMethod->isStatic()) {
                                    $callback[0] = new $callback[0]();
                                }
                            } catch (\ReflectionException $e) {}
                        } elseif (class_exists('\\EventHandlers\\Defaults\\' . $callback[0])) {
                            $className = '\\EventHandlers\\Defaults\\' . $callback[0];
                            $callback[0] = $className;
                            try {
                                $refMethod = new \ReflectionMethod($className, $callback[1]);
                                if (!$refMethod->isStatic()) {
                                    $callback[0] = new $className();
                                }
                            } catch (\ReflectionException $e) {}
                        }
                    }

                    if (is_callable($callback)) {
                        call_user_func_array($callback, [&$params]);
                    }
                    $hEnd = microtime(true);
                    $eventTrace['handlers'][] = [
                        'stage' => 'main',
                        'name' => $hName,
                        'stopped' => $params->isPropagationStopped(),
                        'duration' => round(($hEnd - $hStart) * 1000, 2)
                    ];
                }

                if (isset(self::$eventDefinitions[$event_name]['default_handler']) && self::$eventDefinitions[$event_name]['default_handler']) {
                    $hStart = microtime(true);
                    $defHandler = self::$eventDefinitions[$event_name]['default_handler'];
                    $hName = is_string($defHandler) ? $defHandler : 'Default Handler';
                    if (is_string($defHandler)) {
                        if (class_exists($defHandler)) {
                            $defHandler = [new $defHandler, '__invoke'];
                        } elseif (class_exists('\\EventHandlers\\Defaults\\' . $defHandler)) {
                            $className = '\\EventHandlers\\Defaults\\' . $defHandler;
                            $defHandler = [new $className, '__invoke'];
                        }
                    } elseif (is_array($defHandler) && is_string($defHandler[0])) {
                        $hName = $defHandler[0] . '::' . ($defHandler[1] ?? '');
                        if (class_exists($defHandler[0])) {
                            try {
                                $refMethod = new \ReflectionMethod($defHandler[0], $defHandler[1]);
                                if (!$refMethod->isStatic()) {
                                    $defHandler[0] = new $defHandler[0]();
                                }
                            } catch (\ReflectionException $e) {}
                        } elseif (class_exists('\\EventHandlers\\Defaults\\' . $defHandler[0])) {
                            $className = '\\EventHandlers\\Defaults\\' . $defHandler[0];
                            $defHandler[0] = $className;
                            try {
                                $refMethod = new \ReflectionMethod($className, $defHandler[1]);
                                if (!$refMethod->isStatic()) {
                                    $defHandler[0] = new $className();
                                }
                            } catch (\ReflectionException $e) {}
                        }
                    }
                    if (is_callable($defHandler)) {
                        call_user_func_array($defHandler, [&$params]);
                    }
                    $hEnd = microtime(true);
                    $eventTrace['handlers'][] = [
                        'stage' => 'default',
                        'name' => $hName,
                        'stopped' => $params->isPropagationStopped(),
                        'duration' => round(($hEnd - $hStart) * 1000, 2)
                    ];
                }

                self::triggerHookWithTrace($event_name, $params, $eventTrace, 'main');
            }
        }

        // Dispatch Declarative Manifest Event Subscriptions (__spp_file_props.yml)
        if (!class_exists('\\SPPMod\\SPPStorage\\ManifestEventDispatcher')) {
            $evtDispFile = (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__)) . '/spp/modules/spp/sppstorage/src/ManifestEventDispatcher.php';
            if (file_exists($evtDispFile)) {
                require_once $evtDispFile;
            }
        }
        if (class_exists('\\SPPMod\\SPPStorage\\ManifestEventDispatcher')) {
            try {
                \SPPMod\SPPStorage\ManifestEventDispatcher::dispatch((string)$event_name, $params);
            } catch (\Throwable $e) {}
        }

        self::$collectedTrace[] = $eventTrace;

        if (self::checkStop($params)) return;

        // 3. After Hooks
        self::endEvent($event_name, $params);
        } finally {
            array_pop(self::$activeEventStack);
        }
    }

    public static function triggerHook(string $hookName, mixed $params = null)
    {
        if ($params !== null && is_object($params)) {
            $dedupKey = self::normalizeEventName((string)$hookName) . ':' . spl_object_hash($params);
            if (isset(self::$recentlyDispatched[$dedupKey]) && (microtime(true) - self::$recentlyDispatched[$dedupKey]) < 0.1) {
                // Suppress redundant immediate dual-bus dispatch following fireEvent
                return null;
            }
        }

        if (self::$fake !== null && method_exists(self::$fake, 'recordHook')) {
            self::$fake->recordHook($hookName, $params);
        }
        $dummy = [];
        return self::triggerHookWithTrace($hookName, $params, $dummy, 'hook');
    }

    public static function triggerHookWithTrace(string $hookName, mixed $params, array &$eventTrace, string $stage = 'hook')
    {
        $listeners = self::getListenersForEvent($hookName);
        $norm = self::normalizeEventName($hookName);
        $lastResult = null;

        if (empty($listeners)) {
            $defHandler = self::$eventDefinitions[$hookName]['default_handler'] 
                ?? (self::$eventDefinitions[$norm]['default_handler'] ?? null);

            if ($defHandler !== null) {
                $hStart = microtime(true);
                $callback = $defHandler;
                $hName = is_string($defHandler) ? $defHandler : 'Default Handler';

                if (is_string($callback)) {
                    if (class_exists($callback)) {
                        $callback = [new $callback, '__invoke'];
                    } elseif (class_exists('\\EventHandlers\\Defaults\\' . $callback)) {
                        $className = '\\EventHandlers\\Defaults\\' . $callback;
                        $callback = [new $className, '__invoke'];
                    }
                }

                if (is_callable($callback)) {
                    try {
                        $res = call_user_func_array($callback, [&$params]);
                        if ($res !== null) {
                            $lastResult = $res;
                        }
                    } catch (\Throwable $e) {
                        error_log("[SPPEvent Error] Default handler exception in [{$hookName}]: " . $e->getMessage());
                    }
                }

                $hEnd = microtime(true);
                if (isset($eventTrace['handlers'])) {
                    $eventTrace['handlers'][] = [
                        'stage' => 'default',
                        'name' => $hName,
                        'stopped' => self::checkStop($params),
                        'duration' => round(($hEnd - $hStart) * 1000, 2)
                    ];
                }
            }
            return $lastResult;
        }

        foreach ($listeners as $listener) {
            // Declarative WHEN condition evaluation
            if (!empty($listener['condition']) && is_callable($listener['condition'])) {
                try {
                    if (!call_user_func($listener['condition'], $params)) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    error_log("[SPPEvent Error] WHEN condition exception for event [{$hookName}]: " . $e->getMessage());
                    continue;
                }
            }

            // Once execution unbinding
            if (!empty($listener['once']) && !empty($listener['id'])) {
                self::forgetListener($hookName, $listener['id']);
            }

            $hStart = microtime(true);
            $callback = self::resolveCallableHandler($listener['callback']);
            $hName = is_string($listener['callback']) ? $listener['callback'] : (is_array($listener['callback']) && is_string($listener['callback'][0]) ? ($listener['callback'][0] . '::' . ($listener['callback'][1] ?? '')) : 'Callable');
            
            if (is_callable($callback)) {
                try {
                    $res = call_user_func_array($callback, [&$params]);
                    if ($res !== null) {
                        $lastResult = $res;
                    }
                } catch (\Throwable $e) {
                    error_log("[SPPEvent Error] Listener exception in [{$hName}] for event [{$hookName}]: " . $e->getMessage());
                    if (defined('SPP_DEBUG') && SPP_DEBUG) {
                        $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
                        @file_put_contents($logDir . '/events.log', "[" . date('Y-m-d H:i:s') . "] [LISTENER_EXCEPTION] [{$hookName}] [{$hName}]: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                    if (isset($eventTrace['handlers'])) {
                        $eventTrace['handlers'][] = [
                            'stage' => $stage,
                            'name' => $hName,
                            'error' => $e->getMessage(),
                            'stopped' => false,
                            'duration' => round((microtime(true) - $hStart) * 1000, 2)
                        ];
                    }
                    if (self::shouldRethrowException($hookName, $params, $e)) {
                        throw $e;
                    }
                }
            }
            $hEnd = microtime(true);

            if (isset($eventTrace['handlers'])) {
                $eventTrace['handlers'][] = [
                    'stage' => $stage,
                    'name' => $hName,
                    'stopped' => self::checkStop($params),
                    'duration' => round(($hEnd - $hStart) * 1000, 2)
                ];
            }

            if (self::checkStop($params)) {
                break;
            }
        }

        return $lastResult;
    }

    protected static function shouldRethrowException(string $hookName, mixed $params, ?\Throwable $e = null): bool
    {
        if ($e instanceof \SPP\Exceptions\EventAbortedException) {
            return true;
        }
        if (str_contains($hookName, 'authorize') || str_contains($hookName, 'security')) {
            return true;
        }
        if ($params instanceof \SPP\EventParams) {
            return (bool)($params->get('critical', false) || $params->get('throw_on_error', false) || $params->isAborted());
        }
        return false;
    }

    private static function checkStop(&$params): bool
    {
        if ($params instanceof \SPP\EventParams) {
            return $params->isPropagationStopped();
        }
        return false;
    }

    // Keep legacy signatures alive but gut them
    public static function registerEvent(string $event_name, ?string $default_handler = null) {
        self::defineEvent($event_name, $default_handler, true);
    }
    public static function getEvents() { return []; }
    public static function markOverrider($event_name) {}
    public static function getDefaultHandler($event_name) { return null; }
    public static function hasDefaultHandler($event_name) { return false; }
    public static function registerEvents(array $events) {}
    public static function registerHandlers(string $event_name, array $handlers, bool $default = false) {}
    public static function overrideEvent($event_name, &$params = []) {}
    public static function hasOverrider($handler_name) { return false; }
    public static function dispatch($event) {}
    public static function scanHandlers() { self::boot(); }
    public static function registerDirs() { self::boot(); }
    public static function scanAndRegisterDirs($dir, $top_dir = true) {
        if (!is_dir($dir)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
    }
    public static function getRegisteredListeners(): array
    {
        self::boot();
        $clean = [];
        foreach (self::$listeners as $evt => $handlers) {
            $clean[$evt] = [];
            foreach ($handlers as $h) {
                $cb = $h['callback'];
                $cbName = 'Closure';
                if (is_string($cb)) {
                    $cbName = $cb;
                } elseif (is_array($cb)) {
                    $cbName = (is_object($cb[0]) ? get_class($cb[0]) : $cb[0]) . '::' . ($cb[1] ?? '__invoke');
                }
                $clean[$evt][] = [
                    'handler' => $cbName,
                    'priority' => $h['priority'] ?? 500,
                    'id' => $h['id'] ?? null
                ];
            }
        }
        return $clean;
    }
    public static function getEventDefinitions(): array
    {
        self::boot();
        return self::$eventDefinitions;
    }
    public static function getCollectedTrace(): array { return self::$collectedTrace; }
    public static function clearTrace(): void { self::$collectedTrace = []; }
    public static function persistTrace(): void
    {
        if (empty(self::$collectedTrace)) return;
        $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
        $traceFile = $logDir . '/event_trace.json';

        $existing = [];
        if (file_exists($traceFile)) {
            $content = @file_get_contents($traceFile);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }
        }

        $uri = (PHP_SAPI === 'cli') ? ('CLI ' . implode(' ', $_SERVER['argv'] ?? [])) : ($_SERVER['REQUEST_URI'] ?? 'HTTP');
        $entry = [
            'request_uri' => $uri,
            'timestamp' => date('Y-m-d H:i:s'),
            'trace' => self::$collectedTrace
        ];

        $existing[] = $entry;
        if (count($existing) > 50) {
            $existing = array_slice($existing, -50);
        }

        @file_put_contents($traceFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        self::$collectedTrace = [];
    }
}
