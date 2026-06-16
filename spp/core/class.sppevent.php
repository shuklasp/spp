<?php

namespace SPP;

/**
 * class \SPP\SPPEvent
 * Modern, fast array-based pub/sub routing for the Event System.
 *
 * @author Satya Prakash Shukla
 */
class SPPEvent extends \SPP\SPPObject
{
    private static array $listeners = [];
    private static array $eventDefinitions = [];
    private static array $collectedTrace = [];
    private static bool $booted = false;

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
            self::$listeners = $data['listeners'] ?? [];
            self::$eventDefinitions = $data['definitions'] ?? [];
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
            }
        }

        // Save cache
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $cacheableListeners = [];
        foreach (self::$listeners as $evt => $handlers) {
            foreach ($handlers as $h) {
                if (!($h['callback'] instanceof \Closure)) {
                    $cacheableListeners[$evt][] = $h;
                }
            }
        }
        $export = var_export(['listeners' => $cacheableListeners, 'definitions' => self::$eventDefinitions], true);
        @file_put_contents($cacheFile, "<?php\nreturn " . $export . ";\n");
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
     * Define an overridable event
     */
    public static function defineEvent(string $event, $defaultHandler = null, bool $overridable = false): void
    {
        self::$eventDefinitions[$event] = [
            'default_handler' => $defaultHandler,
            'overridable' => $overridable,
        ];
    }

    /**
     * Explicit hook registration
     */
    public static function listen(string $event, $callback, bool $isOverride = false, int $priority = 500): void
    {
        if ($isOverride) {
            $def = self::$eventDefinitions[$event] ?? null;
            if ($def && !$def['overridable']) {
                throw new \SPP\SPPException("Event {$event} is not overridable.");
            }
            // Override completely replaces listeners for the main event hook
            self::$listeners[$event] = [['callback' => $callback, 'priority' => $priority]];
            return;
        }

        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        self::$listeners[$event][] = ['callback' => $callback, 'priority' => $priority];

        // Sort by priority
        usort(self::$listeners[$event], function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
    }

    /**
     * Legacy registration wrapper
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
    public static function fireEvent($event_name, \SPP\EventParams $params, ?callable $inline_handler = null)
    {
        if (defined('SPP_DEBUG') && SPP_DEBUG) {
            $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
            @file_put_contents($logDir . '/events.log', "[" . date('Y-m-d H:i:s') . "] Firing event: [{$event_name}]\n", FILE_APPEND);
        }

        self::triggerHook('before_' . $event_name, $params);

        if (!$params->isPropagationStopped()) {
            if (!empty(self::$listeners['instead_' . $event_name])) {
                self::triggerHook('instead_' . $event_name, $params);
            } else {
                if (is_callable($inline_handler)) {
                    call_user_func($inline_handler, $params);
                }
                self::triggerHook($event_name, $params);
            }
        }
        
        if (self::checkStop($params)) return;

        // 3. After Hooks
        self::endEvent($event_name, $params);
    }

    public static function triggerHook(string $hookName, &$params)
    {
        if (empty(self::$listeners[$hookName])) return;

        foreach (self::$listeners[$hookName] as $listener) {
            $callback = $listener['callback'];
            
            // Auto-instantiate class names with __invoke
            if (is_string($callback) && class_exists($callback)) {
                $callback = [new $callback, '__invoke'];
            }
            
            if (is_callable($callback)) {
                call_user_func_array($callback, [&$params]);
            }
            if (self::checkStop($params)) {
                break;
            }
        }
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
    public static function scanAndRegisterDirs($dir, $top_dir = true) {}
    public static function getCollectedTrace(): array { return self::$collectedTrace; }
    public static function clearTrace(): void { self::$collectedTrace = []; }
    public static function persistTrace(): void {}
}
