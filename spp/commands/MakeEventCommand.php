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

    public function execute(array $args): void
    {
        $eventName = $args[2] ?? null;
        $handlerName = $args[3] ?? null;

        if (!$eventName || !$handlerName) {
            echo "Usage: php spp.php make:event <EventName> <HandlerClassName> [--app=appname]\n";
            return;
        }

        $app = $this->getContext($args);
        if ($app === 'default') {
            echo "Error: Events should be registered within an application context. Use --app=appname\n";
            return;
        }

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

            if (!isset($eventsData['events'][$eventName]) || !is_array($eventsData['events'][$eventName])) {
                $eventsData['events'][$eventName] = [];
            }

            $fullClassName = "\\" . $namespace . "\\" . $className;
            
            if (!in_array($fullClassName, $eventsData['events'][$eventName])) {
                $eventsData['events'][$eventName][] = ltrim($fullClassName, '\\');
                
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
            } else {
                echo "Notice: Event handler already registered in events.yml.\n";
            }

            // Auto-clear cache
            $sppBin = escapeshellarg(dirname(SPP_BASE_DIR) . '/spp.php');
            shell_exec("php {$sppBin} cache:clear");
            echo "Framework cache cleared.\n";
        }
    }
}
