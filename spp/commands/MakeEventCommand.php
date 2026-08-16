<?php

namespace SPP\CLI\Commands;

use Symfony\Component\Yaml\Yaml;

/**
 * Class MakeEventCommand
 * Registers a new event in events.yml and scaffolds a handler class.
 */
class MakeEventCommand extends BaseMakeCommand
{
    protected string $name = 'make:event';
    protected string $description = 'Create a new event entry and scaffold its handler';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $eventName = $this->getArgument($args, 0) ?? null;
        $handlerName = $this->getArgument($args, 1) ?? null;

        if (!$eventName || !$handlerName) {
            echo "Usage: php spp.php make:event <EventName> <HandlerClassName> [--app=appname] [--overridable] [--default-handler]\n";
            return;
        }

        $app = $this->getContext($args);
        if ($app === 'default') {
            echo "Error: Events should be registered within an application context. Use --app=appname\n";
            return;
        }

        $isOverridable = in_array('--overridable', $args, true);
        $isDefaultHandler = in_array('--default-handler', $args, true);

        $className = ucfirst($handlerName);
        $namespace = $this->getNamespace('Events', $app);
        
        $targetDir = $this->getTargetDir('events', $app);
        $targetPath = "{$targetDir}/{$className}.php";

        $success = $this->buildFromStub('eventhandler', $targetPath, [
            'namespace' => $namespace,
            'className' => $className
        ]);

        if ($success) {
            echo "Success: Event Handler {$className} created at {$targetPath}\n";

            // Add to events.yml
            $eventsYmlPath = SPP_APP_DIR . "/src/{$app}/etc/events.yml";
            $eventsData = ['events' => []];

            if (file_exists($eventsYmlPath)) {
                try {
                    $parsed = Yaml::parseFile($eventsYmlPath);
                    if (is_array($parsed)) {
                        $eventsData = $parsed;
                    }
                } catch (\Exception $e) {
                    echo "Warning: Failed to parse events.yml. Appending manually might be required.\n";
                }
            }

            if (!isset($eventsData['events']) || !is_array($eventsData['events'])) {
                $eventsData['events'] = [];
            }

            $fullClassName = "\\" . $namespace . "\\" . $className;
            
            // If the event doesn't exist, initialize it
            if (!isset($eventsData['events'][$eventName])) {
                if ($isOverridable || $isDefaultHandler) {
                    $eventsData['events'][$eventName] = [
                        'listeners' => []
                    ];
                } else {
                    $eventsData['events'][$eventName] = [];
                }
            } else {
                // If the event already exists but is a simple array, and we need complex features, upgrade it
                if (is_array($eventsData['events'][$eventName]) && !isset($eventsData['events'][$eventName]['listeners']) && ($isOverridable || $isDefaultHandler)) {
                    $oldListeners = $eventsData['events'][$eventName];
                    $eventsData['events'][$eventName] = [
                        'listeners' => $oldListeners
                    ];
                }
            }

            $eventDef = &$eventsData['events'][$eventName];

            if (is_array($eventDef) && isset($eventDef['listeners'])) {
                if ($isOverridable) {
                    $eventDef['overridable'] = true;
                }
                if ($isDefaultHandler) {
                    $eventDef['default_handler'] = ltrim($fullClassName, '\\');
                } else {
                    if (!in_array(ltrim($fullClassName, '\\'), $eventDef['listeners'])) {
                        $eventDef['listeners'][] = ltrim($fullClassName, '\\');
                    }
                }
            } else {
                // Simple list mode
                if (!in_array(ltrim($fullClassName, '\\'), $eventDef)) {
                    $eventDef[] = ltrim($fullClassName, '\\');
                }
            }
            
            try {
                $yamlContent = Yaml::dump($eventsData, 4, 2);
                if (!is_dir(dirname($eventsYmlPath))) {
                    mkdir(dirname($eventsYmlPath), 0777, true);
                }
                file_put_contents($eventsYmlPath, $yamlContent);
                echo "Success: Registered event '{$eventName}' to handler '{$fullClassName}' in events.yml.\n";
            } catch (\Exception $e) {
                echo "Error: Failed to save events.yml: " . $e->getMessage() . "\n";
            }

            // Auto-clear cache
            $sppBin = escapeshellarg(dirname(SPP_BASE_DIR) . '/spp.php');
            shell_exec("php {$sppBin} cache:clear");
            echo "Framework cache cleared.\n";
        }
    }
}
