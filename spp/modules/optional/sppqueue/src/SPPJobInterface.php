<?php
namespace SPP\Core;

/**
 * Interface SPPJobInterface
 * Standard contract for asynchronous background jobs.
 */
interface SPPJobInterface {
    
    /**
     * Execute the job.
     */
    public function handle(): void;
    
    /**
     * Handle job failure.
     * 
     * @param \Throwable $exception
     */
    public function failed(\Throwable $exception): void;
    
    /**
     * Determine the number of times the job may be attempted.
     * 
     * @return int
     */
    public function tries(): int;
}
