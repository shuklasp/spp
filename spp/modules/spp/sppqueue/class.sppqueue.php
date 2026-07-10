<?php

namespace SPPMod\SPPQueue;

/**
 * class SppQueue
 *
 * A cross-app task queue system powered by the Polyglot Registry.
 */
class SppQueue
{
    /**
     * Push a new task onto the queue.
     */
    public static function push(string $jobClass, array $data = []): void
    {
        $queue = \SPP\Registry::get('__shared=>queue') ?: [];
        $queue[] = [
            'job' => $jobClass,
            'data' => $data,
            'created_at' => time(),
            'id' => uniqid('job_')
        ];

        \SPP\Registry::register('__shared=>queue', $queue);
    }

    /**
     * Process the next job in the queue.
     */
    public static function work(): void
    {
        $queue = \SPP\Registry::get('__shared=>queue') ?: [];
        if (empty($queue)) {
            return;
        }

        $jobData = array_shift($queue);
        \SPP\Registry::register('__shared=>queue', $queue);

        $jobClass = $jobData['job'];
        if (class_exists($jobClass)) {
            $job = \SPP\Registry::make($jobClass);
            if (method_exists($job, 'handle')) {
                $job->handle($jobData['data']);
            }
        }
    }

    /**
     * Push a task node into the DAG execution graph with explicit dependencies.
     */
    public static function pushDagNode(string $jobId, $job, array $dependencies = [], array $data = []): void
    {
        if (!class_exists('\SPPMod\SPPQueue\Workflow\DagJobOrchestrator')) {
            require_once __DIR__ . '/Workflow/DagJobOrchestrator.php';
        }
        \SPPMod\SPPQueue\Workflow\DagJobOrchestrator::addJobNode($jobId, $job, $dependencies, $data);
    }

    /**
     * Process the full DAG execution graph with token-bucket throttling.
     */
    public static function workDag(int $bucketCapacity = 10, float $fillRate = 5.0): bool
    {
        if (!class_exists('\SPPMod\SPPQueue\Workflow\DagJobOrchestrator')) {
            require_once __DIR__ . '/Workflow/DagJobOrchestrator.php';
        }
        return \SPPMod\SPPQueue\Workflow\DagJobOrchestrator::executeDag($bucketCapacity, $fillRate);
    }
}

