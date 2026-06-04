<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\Queue;

class QueueWorkCommand extends Command
{
    protected string $name = 'queue:work';
    protected string $description = 'Starts a worker loop to process background jobs from the queue.';

    public function execute(array $args): void
    {
        echo "Starting SPP Queue Worker Daemon...\n";
        
        // Define sleep duration between polling empty queue
        $sleep = 2; // seconds

        while (true) {
            $jobData = Queue::pop();

            if ($jobData) {
                $id = $jobData['id'];
                $job = $jobData['job'];
                
                echo "[" . date('Y-m-d H:i:s') . "] Processing Job ID: {$id} (" . get_class($job) . ")...\n";
                
                try {
                    $job->handle();
                    Queue::complete($id);
                    echo "[" . date('Y-m-d H:i:s') . "] Job ID {$id} Completed.\n";
                } catch (\Throwable $e) {
                    echo "[" . date('Y-m-d H:i:s') . "] Job ID {$id} Failed: " . $e->getMessage() . "\n";
                    // Optionally push back to queue or log failure
                }
            } else {
                sleep($sleep);
            }
        }
    }
}
