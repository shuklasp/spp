<?php
namespace SPP;

/**
 * class \SPP\SPPEvent
 * Implements event system in Satya Portal Pack.
 *
 * @author Satya Prakash Shukla
 * 
 */
class SPPEvent extends \SPP\SPPObject
{
    private static array $events = [];
    private static array $activeHandlers = [];
    private static array $collectedTrace = [];
    private static array $scannedDirs = [];
    private static bool $dirsRegistered = false;

    /**
     * Constructor
     * Declared private to prevent creation of an object.
     */
    private function __construct()
    {
        // parent::__construct();
    }

    /**
     * function registerEvent()
     * Registers an event.
     * 
     * @param string $event_name Name of event.
     */
    public static function registerEvent(string $event_name, ?string $default_handler = null)
    {
        if (!array_key_exists($event_name, self::$events)) {
            self::$events[$event_name] = array(
                'defaulthandler' => $default_handler,
                'handlers' => array(),
                'overriders' => false
            );
        }
    }

    /**
     * function getEvents()
     * Returns all registered events.
     * 
     * @return array All registered events.
     */
    public static function getEvents()
    {
        return self::$events;
    }

    public static function markOverrider($event_name)
    {
        if (isset(self::$events[$event_name])) {
            self::$events[$event_name]['overriders'] = true;
        }
    }

    public static function getDefaultHandler($event_name)
    {
        $events = self::getEvents();

        if (array_key_exists($event_name, $events)) {
            return $events[$event_name]['defaulthandler'];
        } else {
            throw new \SPP\SPPException('Event "' . $event_name . '" not registered!');
        }
    }

    /**
     * function hasDefaultHandler()
     * Checks if a default handler is registered for an event.
     * 
     * @param string $event_name Name of event.
     */
    public static function hasDefaultHandler($event_name)
    {
        return (self::getDefaultHandler($event_name) !== null);
    }

    /**
     * function registerEvents()
     * Registers multiple events.
     * 
     * @param array $events Array of event names.
     */
    public static function registerEvents(array $events)
    {
        foreach ($events as $event) {
            self::registerEvent($event);
        }
    }

    /**
     * function registerHandler()
     * Registers a handler for an event.
     * 
     * @param string $event_name Name of event.
     * @param string $handler_name Name of handler function or FQCN.
     * @param bool $default Default handler.
     * @param string|null $method Specific method for Subscribers.
     * @param int|null $priority Execution priority.
     */
    public static function registerHandler(string $event_name, string $handler_name, bool $default = false, ?string $method = null, ?int $priority = null)
    {
        // Normalize event name if it's a class
        if (class_exists($event_name) && is_subclass_of($event_name, SPPEventObject::class)) {
            $temp = new $event_name();
            $event_name = $temp->getName();
        }

        if (!array_key_exists($event_name, self::$events)) {
            self::registerEvent($event_name, $default ? $handler_name : null);
        }

        // Resolve class name
        $className = $handler_name;
        if (!class_exists($className)) {
            if (class_exists('EventHandlers\\' . $handler_name)) {
                $className = 'EventHandlers\\' . $handler_name;
            } elseif (class_exists('EventHandlers\\Defaults\\' . $handler_name)) {
                $className = 'EventHandlers\\Defaults\\' . $handler_name;
            }
        }

        if (!is_subclass_of($className, '\\SPP\\EventHandler')) {
            return false;
        }

        if ($default) {
            self::$events[$event_name]['defaulthandler'] = $handler_name;
        } else {
            // Check for duplicates
            foreach (self::$events[$event_name]['handlers'] as $h) {
                if (is_array($h) && $h['class'] === $className && $h['method'] === $method) return true;
                if (!is_array($h) && $h === $className && $method === null) return true;
            }

            // Get priority from class if not provided
            if ($priority === null) {
                $instance = self::getHandlerInstance($className);
                $priority = $instance ? $instance->getPriority() : 500;
            }

            self::$events[$event_name]['handlers'][] = [
                'class'    => $className,
                'method'   => $method,
                'priority' => $priority
            ];
        }

        return true;
    }

    /**
     * function registerHandlers()
     * Registers multiple handlers for an event.
     * 
     * @param array $handlers Array of handler names.
     * @param bool $default Default handler.
     */
    public static function registerHandlers(string $event_name, array $handlers, bool $default = false)
    {
        foreach ($handlers as $handler) {
            self::registerHandler($event_name, $handler, $default);
        }
    }

    /****
     * function startEvent()
     * Starts an event.
     */
    public static function startEvent(string $event_name, array &$params = array())
    {
        if (!array_key_exists($event_name, self::$events)) {
            return;
        }

        self::trace("Starting event: [{$event_name}]");
        $handlers = self::getSortedHandlers($event_name);
        foreach ($handlers as $h) {
            $instance = self::callHandler($h, 'before', $params);
            if ($instance instanceof \SPP\EventHandler && $instance->isPropagationStopped()) {
                self::trace("  !! Propagation STOPPED during StartEvent");
                break;
            }
        }
    }

    /**
     * function endEvent()
     * Ends an event.
     */
    public static function endEvent($event_name, &$params = array())
    {
        if (!array_key_exists($event_name, self::$events)) {
            return;
        }

        self::trace("Ending event: [{$event_name}]");
        $handlers = self::getSortedHandlers($event_name);
        foreach ($handlers as $h) {
            $instance = self::callHandler($h, 'after', $params);
            if ($instance instanceof \SPP\EventHandler && $instance->isPropagationStopped()) {
                self::trace("  !! Propagation STOPPED during EndEvent");
                break;
            }
        }
    }

    /**
     * function overrideEvent()
     * Overrides a fireable event.
     */
    public static function overrideEvent($event_name, &$params = array())
    {
        if (!array_key_exists($event_name, self::$events)) {
            return;
        }

        self::trace("Overriding event: [{$event_name}]");
        $handlers = self::getSortedHandlers($event_name);
        foreach ($handlers as $h) {
            $h_class = is_array($h) ? $h['class'] : $h;
            if (self::hasOverrider($h_class)) {
                $instance = self::callHandler($h, 'override', $params);
                self::$events[$event_name]['overriders'] = true;
                if ($instance instanceof \SPP\EventHandler && $instance->isPropagationStopped()) {
                    self::trace("  !! Propagation STOPPED during OverrideEvent");
                    break;
                }
            }
        }
    }

    /**
     * function hasOverrider()
     * Checks if an event has an overrider.
     * 
     * @param string $handler_name Name of handler.
     * @return bool
     */
    public static function hasOverrider($handler_name)
    {
        return method_exists($handler_name, 'overrideHandler') || 
               method_exists('EventHandlers\\' . $handler_name, 'overrideHandler');
    }

    /**
     * function dispatch()
     * Dispatches a modern Event Object.
     * 
     * @param SPPEventObject $event
     */
    public static function dispatch(SPPEventObject $event)
    {
        self::fireEvent($event->getName(), $event);
    }

    /**
     * function fireEvent()
     * Fires an overridable event.
     */
    public static function fireEvent($event_name, mixed &$params = array(), mixed $inline_handler = null)
    {
        if (!array_key_exists($event_name, self::$events)) {
            return;
        }

        self::trace("Firing event: [{$event_name}]");
        $traceId = count(self::$collectedTrace);
        self::$collectedTrace[] = [
            'event' => $event_name,
            'timestamp' => microtime(true),
            'handlers' => []
        ];

        $overridden = false;
        $handlers = self::getSortedHandlers($event_name);

        // Stage 1: Before
        foreach ($handlers as $h) {
            $h_desc = is_array($h) ? ($h['class'] . ($h['method'] ? "@{$h['method']}" : "")) : $h;
            self::trace("  -> Executing Before: {$h_desc}");
            $instance = self::callHandler($h, 'before', $params);
            
            self::$collectedTrace[$traceId]['handlers'][] = [
                'stage' => 'before',
                'handler' => $h_desc,
                'stopped' => false
            ];

            // Handle propagation stop (for both legacy and object modes)
            $stopped = ($params instanceof SPPEventObject) ? $params->isPropagationStopped() : ($instance && $instance->isPropagationStopped());
            if ($stopped) {
                self::trace("  !! Propagation STOPPED by {$h_desc}");
                $lastIdx = count(self::$collectedTrace[$traceId]['handlers']) - 1;
                self::$collectedTrace[$traceId]['handlers'][$lastIdx]['stopped'] = true;
                return;
            }
        }

        // Stage 2: Override
        foreach ($handlers as $h) {
            $h_class = is_array($h) ? $h['class'] : $h;
            if (self::hasOverrider($h_class)) {
                $h_desc = is_array($h) ? ($h['class'] . ($h['method'] ? "@{$h['method']}" : "")) : $h;
                self::trace("  -> Executing Override: {$h_desc}");
                $instance = self::callHandler($h, 'override', $params);
                $overridden = true;
                
                self::$collectedTrace[$traceId]['handlers'][] = [
                    'stage' => 'override',
                    'handler' => $h_desc,
                    'stopped' => false
                ];

                $stopped = ($params instanceof SPPEventObject) ? $params->isPropagationStopped() : ($instance && $instance->isPropagationStopped());
                if ($stopped) {
                    self::trace("  !! Propagation STOPPED during Override");
                    $lastIdx = count(self::$collectedTrace[$traceId]['handlers']) - 1;
                    self::$collectedTrace[$traceId]['handlers'][$lastIdx]['stopped'] = true;
                    break; 
                }
            }
        }

        // Stage 3: Default (if not overridden)
        if (!$overridden) {
            if ($inline_handler !== null && (is_object($inline_handler) || is_array($inline_handler)) && is_callable($inline_handler)) {
                self::trace("  -> Executing Inline/Anonymous handler");
                $inline_handler($params);
            } elseif ($inline_handler !== null && is_string($inline_handler)) {
                // String handler class name passed as inline default
                self::trace("  -> Executing Inline Default Handler (class): {$inline_handler}");
                self::callHandler($inline_handler, 'default', $params);
            } else {
                $default = self::$events[$event_name]['defaulthandler'];

                if ($default !== null) {
                    self::trace("  -> Executing Default Handler: {$default}");
                    self::callHandler($default, 'default', $params);
                } else {
                    // Optional event hook - return silently
                    self::trace("  .. Event '{$event_name}' has no handlers. Continuing.");
                    return;
                }
            }
        }

        // Stage 4: After
        foreach ($handlers as $h) {
            $h_desc = is_array($h) ? ($h['class'] . ($h['method'] ? "@{$h['method']}" : "")) : $h;
            self::trace("  -> Executing After: {$h_desc}");
            $instance = self::callHandler($h, 'after', $params);

            self::$collectedTrace[$traceId]['handlers'][] = [
                'stage' => 'after',
                'handler' => $h_desc,
                'stopped' => false
            ];

            $stopped = ($params instanceof SPPEventObject) ? $params->isPropagationStopped() : ($instance && $instance->isPropagationStopped());
            if ($stopped) {
                self::trace("  !! Propagation STOPPED during After stage");
                $lastIdx = count(self::$collectedTrace[$traceId]['handlers']) - 1;
                self::$collectedTrace[$traceId]['handlers'][$lastIdx]['stopped'] = true;
                break;
            }
        }
    }

    /**
     * Internal helper to sort handlers by priority.
     */
    private static function getSortedHandlers(string $event_name): array
    {
        $handlers = self::$events[$event_name]['handlers'] ?? [];
        if (empty($handlers)) return [];

        // Normalize handlers and get priorities
        $normalized = [];
        foreach ($handlers as $h) {
            if (is_array($h)) {
                $normalized[] = $h;
            } else {
                $instance = self::getHandlerInstance($h);
                $priority = $instance ? $instance->getPriority() : 500;
                $normalized[] = ['class' => $h, 'method' => null, 'priority' => $priority];
            }
        }

        usort($normalized, function ($a, $b) {
            return ($b['priority'] ?? 500) <=> ($a['priority'] ?? 500);
        });

        return $normalized;
    }

    /**
     * Internal helper to get/create handler instance and call it.
     */
    private static function callHandler($handler_data, $occurence, mixed &$params = array()): ?\SPP\EventHandler
    {
        $handler_name = is_array($handler_data) ? $handler_data['class'] : $handler_data;
        $custom_method = is_array($handler_data) ? $handler_data['method'] : null;

        $instance = self::getHandlerInstance($handler_name, $occurence);
        if (!$instance) return null;

        // If a custom method is provided (Subscriber), we call it instead of stage-specific ones
        if ($custom_method && $occurence !== 'default') {
            if (method_exists($instance, $custom_method)) {
                $instance->$custom_method($params, $occurence);
            }
            return $instance;
        }

        if ($occurence === 'before' && method_exists($instance, 'beforeHandler')) {
            $instance->beforeHandler($params);
        } elseif ($occurence === 'override' && method_exists($instance, 'overrideHandler')) {
            $instance->overrideHandler($params);
        } elseif ($occurence === 'after' && method_exists($instance, 'afterHandler')) {
            $instance->afterHandler($params);
        } elseif ($occurence === 'default' && method_exists($instance, 'overrideHandler')) {
            $instance->overrideHandler($params);
        }

        return $instance;
    }

    private static function trace(string $message): void
    {
        if (defined('SPP_DEBUG') && SPP_DEBUG) {
            $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
            $logFile = $logDir . '/events.log';
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
        }
    }

    /**
     * Internal helper to get/create handler instance.
     */
    private static function getHandlerInstance(string $handler_name, string $occurence = 'before'): ?\SPP\EventHandler
    {
        $class = $handler_name;
        if (!class_exists($class)) {
            $namespace = ($occurence === 'default') ? '\\EventHandlers\\Defaults\\' : '\\EventHandlers\\';
            $class = $namespace . $handler_name;
        }

        // File-based fallback: try to load from known event directories
        if (!class_exists($class)) {
            $subDir = ($occurence === 'default') ? 'Defaults' . SPP_DS : '';
            $candidateDirs = [
                SPP_BASE_DIR . SPP_DS . 'events' . SPP_DS . $subDir,
                SPP_APP_DIR . SPP_DS . 'events' . SPP_DS . $subDir,
            ];
            foreach ($candidateDirs as $dir) {
                $file = $dir . $handler_name . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    break;
                }
            }
        }

        if (!class_exists($class)) return null;

        if (!isset(self::$activeHandlers[$class])) {
            $instance = new $class();
            if (!$instance instanceof \SPP\EventHandler) return null;
            self::$activeHandlers[$class] = $instance;
        }
        return self::$activeHandlers[$class];
    }

    /**
     * function scanHandlers()
     * Scans the event handlers.
     */
    public static function scanHandlers()
    {
        self::scanDirectory(SPP_BASE_DIR . SPP_DS . 'events', 'EventHandlers');
        self::scanDirectory(SPP_APP_DIR . SPP_DS . 'events', 'EventHandlers');

        $mods = \SPP\Registry::get('__mods');
        if (is_array($mods)) {
            foreach ($mods as $modname => $modpath) {
                $dir = $modpath . SPP_DS . 'events';
                $namespace = 'SPPMod\\' . ucfirst($modname) . '\\Events';
                self::scanDirectory($dir, $namespace);
            }
        }
    }

    /**
     * Internal helper to scan a directory for handlers.
     */
    private static function scanDirectory(string $dir, string $namespace): void
    {
        if (!is_dir($dir) || isset(self::$scannedDirs[$dir])) {
            return;
        }
        self::$scannedDirs[$dir] = true;

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $dir . SPP_DS . $file;
            if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                require_once $path;
                $class = pathinfo($file, PATHINFO_FILENAME);
                
                $fqcn = '\\' . trim($namespace, '\\') . '\\' . $class;
                if (class_exists($fqcn) && is_subclass_of($fqcn, '\\SPP\\EventHandler')) {
                    // Check for Subscriber
                    $subscribed = $fqcn::getSubscribedEvents();
                    if (!empty($subscribed)) {
                        foreach ($subscribed as $event => $mapping) {
                            $method = is_array($mapping) ? $mapping[0] : $mapping;
                            $priority = is_array($mapping) ? ($mapping[1] ?? 500) : 500;
                            self::registerHandler($event, $fqcn, false, $method, $priority);
                        }
                    } else {
                        self::registerHandler($class, $fqcn);
                    }
                }
            }
        }
    }

    /**
     * function registerDirs()
     * Registers directories for events.
     */
    public static function registerDirs()
    {
        if (self::$dirsRegistered) return;

        self::scanAndRegisterDirs(SPP_BASE_DIR . SPP_DS . 'events');
        self::scanAndRegisterDirs(SPP_APP_DIR . SPP_DS . 'events');

        $mods = \SPP\Registry::get('__mods');
        if (is_array($mods)) {
            foreach ($mods as $modname => $modpath) {
                $dir = $modpath . SPP_DS . 'events';
                if (is_dir($dir)) self::scanAndRegisterDirs($dir);
            }
        }
        self::$dirsRegistered = true;
    }

    /**
     * function scanAndRegisterDirs()
     * Recursively scans and registers directories for events.
     */
    public static function scanAndRegisterDirs($dir, $top_dir = true)
    {
        if (!is_dir($dir)) return;
        if ($top_dir) \SPP\Registry::registerDir('events', $dir);

        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . SPP_DS . $file;
            if (is_dir($path)) {
                if (is_link($path)) continue;
                \SPP\Registry::registerDir('events', $path);
                self::scanAndRegisterDirs($path, false);
            }
        }
    }

    public static function getCollectedTrace(): array
    {
        return self::$collectedTrace;
    }

    public static function clearTrace(): void
    {
        self::$collectedTrace = [];
    }

    public static function persistTrace(): void
    {
        if (empty(self::$collectedTrace)) return;
        
        $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : SPP_BASE_DIR . '/var/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
        
        $logFile = $logDir . '/event_trace.json';
        $existing = [];
        if (file_exists($logFile)) {
            $data = json_decode(file_get_contents($logFile), true);
            if (is_array($data)) $existing = $data;
        }
        
        // Keep only last 20 traces
        $existing[] = [
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'timestamp' => date('Y-m-d H:i:s'),
            'trace' => self::$collectedTrace
        ];
        
        if (count($existing) > 20) {
            $existing = array_slice($existing, -20);
        }
        
        @file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT));
    }
}
