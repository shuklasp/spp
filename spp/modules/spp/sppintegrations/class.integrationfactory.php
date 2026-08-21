<?php
namespace SPPMod\SPPIntegrations;

use Exception;

/**
 * Class IntegrationFactory
 * 
 * Central registry for all SPP external application drivers.
 */
class IntegrationFactory
{
    private static array $drivers = [];

    /**
     * Register a new integration driver.
     * 
     * @param string $name Short name identifier (e.g., 'wordpress')
     * @param string $className Fully qualified class name
     */
    public static function registerDriver(string $name, string $className): void
    {
        if (!is_subclass_of($className, ExternalAppDriverInterface::class)) {
            throw new Exception("Driver {$className} must implement ExternalAppDriverInterface.");
        }
        
        self::$drivers[strtolower($name)] = $className;
    }

    /**
     * Instantiate and return a driver.
     * 
     * @param string $name Short name identifier
     * @param array $config Configuration array for the driver
     * @return ExternalAppDriverInterface
     * @throws Exception If driver is not found
     */
    public static function getDriver(string $name, array $config = []): ExternalAppDriverInterface
    {
        $name = strtolower($name);
        
        if (!isset(self::$drivers[$name])) {
            throw new Exception("Integration driver '{$name}' is not registered.");
        }

        $className = self::$drivers[$name];
        return new $className($config);
    }

    /**
     * Get all registered drivers.
     * 
     * @return array
     */
    public static function getRegisteredDrivers(): array
    {
        return self::$drivers;
    }

    /**
     * Broadcast a user synchronization event to all registered drivers synchronously.
     * Used by CDC Gateway and Queue Workers.
     * 
     * @param array $userData The user payload to broadcast
     * @param string $excludeAlias Optional alias to exclude (e.g. the source of the event)
     * @return array Array of success statuses keyed by driver alias
     */
    public static function broadcastUserSync(array $userData, string $excludeAlias = ''): array
    {
        $results = [];
        $useDag = class_exists('\SPPMod\SPPQueue\DagJobOrchestrator');
        
        $jobs = [];

        $traceId = null;
        if (class_exists('\SPPMod\SPPReport\W3CTraceContext')) {
            $traceId = \SPPMod\SPPReport\W3CTraceContext::getCurrentTraceId();
        }

        foreach (self::$drivers as $alias => $class) {
            if ($alias === $excludeAlias) continue;
            
            $jobPayload = ['driver_alias' => $alias, 'user_data' => $userData];
            if ($traceId) {
                $jobPayload['trace_id'] = $traceId;
            }

            if ($useDag) {
                // Saga Pattern: Orchestrate as a DAG Job
                $jobs[] = [
                    'id' => "sync_{$alias}",
                    'handler' => [IntegrationSyncJob::class, 'process'],
                    'payload' => $jobPayload,
                    'retries' => 3
                ];
                $results[$alias] = true; // Job queued successfully
            } else {
                // Poor Man's Cron / Synchronous Fallback
                register_shutdown_function(function() use ($jobPayload) {
                    try {
                        IntegrationSyncJob::process($jobPayload);
                    } catch (\Exception $e) {
                        // Suppress in shutdown
                    }
                });
                $results[$alias] = true;
            }
        }
        
        if ($useDag && !empty($jobs)) {
            \SPPMod\SPPQueue\DagJobOrchestrator::dispatchGroup($jobs);
        }

        return $results;
    }

    /**
     * Generates a SQL CREATE TRIGGER statement to hook an external application's table
     * into the SPP Integration Queue for Zero-Touch CDC (Path 3).
     * 
     * @param string $driverAlias The target application (e.g., phpbb)
     * @param string $tableName The external table to watch
     * @return string The raw SQL statement
     */
    public static function generateTriggers(string $driverAlias, string $tableName): string
    {
        return "
CREATE TRIGGER after_{$driverAlias}_update 
AFTER UPDATE ON {$tableName}
FOR EACH ROW
BEGIN
    INSERT INTO spp_integration_events (app, event_type, payload, created_at)
    VALUES ('{$driverAlias}', 'user_update', JSON_OBJECT('id', NEW.id), NOW());
END;
";
    }
}
