<?php

namespace SPPMod\SPPQueue\Workflow;

use SPP\Core\Job;
use SPP\Core\Queue;
use SPP\Registry;

/**
 * DagJobOrchestrator
 * Implements Directed Acyclic Graph (DAG) task chaining and token-bucket throttling to
 * manage complex job dependencies and protect downstream APIs during high-throughput bursts.
 */
class DagJobOrchestrator
{
    private static array $dagStorage = [];
    private static int $tokens = 10;
    private static float $lastReplenish = 0.0;

    /**
     * Add a job node to the DAG execution graph with explicit dependencies.
     *
     * @param string $jobId Unique Job Identifier
     * @param Job|string $job The Job instance or class name
     * @param array $dependencies List of prerequisite job IDs
     * @param array $data Optional data payload if passing a string class name
     */
    public static function addJobNode(string $jobId, $job, array $dependencies = [], array $data = []): void
    {
        self::$dagStorage[$jobId] = [
            'job' => $job,
            'data' => $data,
            'dependencies' => $dependencies,
            'status' => 'pending' // pending, running, completed, failed
        ];
    }

    /**
     * Replenish token bucket based on elapsed time.
     *
     * @param int $capacity Max bucket capacity
     * @param float $fillRate Tokens added per second
     */
    private static function replenishTokens(int $capacity = 10, float $fillRate = 5.0): void
    {
        $now = microtime(true);
        if (self::$lastReplenish === 0.0) {
            self::$lastReplenish = $now;
            self::$tokens = $capacity;
            return;
        }

        $delta = $now - self::$lastReplenish;
        $added = (int)($delta * $fillRate);

        if ($added > 0) {
            self::$tokens = min($capacity, self::$tokens + $added);
            self::$lastReplenish = $now;
        }
    }

    /**
     * Execute the DAG graph, honoring topological order and token-bucket throttling.
     *
     * @param int $bucketCapacity Max burst tokens
     * @param float $fillRate Token replenish rate per second
     * @return bool True if all jobs completed successfully
     */
    public static function executeDag(int $bucketCapacity = 10, float $fillRate = 5.0): bool
    {
        echo "\033[32mINFO:\033[0m Starting DAG Job Orchestration with Token-Bucket throttling (Capacity: {$bucketCapacity}, Rate: {$fillRate}/s)\n";

        $completedCount = 0;
        $totalJobs = count(self::$dagStorage);

        while ($completedCount < $totalJobs) {
            self::replenishTokens($bucketCapacity, $fillRate);

            $progressMade = false;

            foreach (self::$dagStorage as $jobId => &$node) {
                if ($node['status'] !== 'pending') {
                    continue;
                }

                // Check dependencies
                $depsSatisfied = true;
                foreach ($node['dependencies'] as $depId) {
                    if (!isset(self::$dagStorage[$depId]) || self::$dagStorage[$depId]['status'] !== 'completed') {
                        $depsSatisfied = false;
                        break;
                    }
                }

                if ($depsSatisfied) {
                    // Check token bucket
                    if (self::$tokens <= 0) {
                        echo "\033[33mWARN:\033[0m Token bucket depleted. Throttling execution...\n";
                        usleep(200000); // 200ms
                        self::replenishTokens($bucketCapacity, $fillRate);
                        if (self::$tokens <= 0) {
                            continue;
                        }
                    }

                    // Consume token
                    self::$tokens--;
                    $node['status'] = 'running';
                    echo "Executing DAG Job: `{$jobId}`...\n";

                    try {
                        $jobInstance = $node['job'];
                        if (is_string($jobInstance) && class_exists($jobInstance)) {
                            if (is_subclass_of($jobInstance, Job::class)) {
                                $jobInstance = new $jobInstance($node['data']);
                            } else {
                                $jobInstance = Registry::make($jobInstance);
                            }
                        }

                        if ($jobInstance instanceof Job) {
                            $jobInstance->handle();
                        } elseif (is_object($jobInstance) && method_exists($jobInstance, 'handle')) {
                            $jobInstance->handle($node['data']);
                        }

                        $node['status'] = 'completed';
                        $completedCount++;
                        $progressMade = true;
                        echo "\033[32mSUCCESS:\033[0m DAG Job `{$jobId}` completed successfully.\n";
                    } catch (\Throwable $e) {
                        $node['status'] = 'failed';
                        echo "\033[31mERROR:\033[0m DAG Job `{$jobId}` failed: " . $e->getMessage() . "\n";
                        return false; // Fast fail on DAG branch failure
                    }
                }
            }

            if (!$progressMade && $completedCount < $totalJobs) {
                // Check if any jobs are stuck due to circular dependencies or unfulfilled deps
                $stuck = true;
                foreach (self::$dagStorage as $node) {
                    if ($node['status'] === 'running') {
                        $stuck = false;
                        break;
                    }
                }
                if ($stuck) {
                    echo "\033[31mERROR:\033[0m DAG execution deadlocked. Unresolved dependencies detected.\n";
                    return false;
                }
                usleep(100000); // 100ms wait for async jobs if applicable
            }
        }

        echo "\033[32mINFO:\033[0m All DAG jobs executed successfully.\n";
        return true;
    }

    /**
     * Get current DAG state.
     */
    public static function getDagState(): array
    {
        return self::$dagStorage;
    }

    /**
     * Clear DAG storage.
     */
    public static function clearDag(): void
    {
        self::$dagStorage = [];
    }
}
