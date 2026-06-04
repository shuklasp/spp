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
}
