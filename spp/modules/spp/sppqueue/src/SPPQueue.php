<?php

namespace SPP\Core;

/**
 * Abstract Class Job
 * Base class for all background jobs in the SPP framework.
 */
abstract class Job
{
    /** @var array Data passed to the job */
    protected array $data = [];

    /**
     * Job constructor.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    abstract public function handle(): void;

    /**
     * Get job data.
     */
    public function getData(): array
    {
        return $this->data;
    }
}

/**
 * Class Queue
 * Manages the background job queue.
 */
class Queue
{
    protected static string $table = 'spp_jobs';

    public static function push(Job $job, int $delay = 0): bool
    {
        $payload = json_encode([
            'class' => get_class($job),
            'data' => $job->getData()
        ]);
        try {
            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $availableAt = time() + $delay;
                $db->execute_query("INSERT INTO " . self::$table . " (payload, available_at, created_at) VALUES (?, ?, ?)", [$payload, $availableAt, time()]);
                return true;
            }
        } catch (\Exception $e) {
            error_log("Queue push failed: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Peek at the next job in the queue.
     * @return array|null
     */
    public static function pop(): ?array
    {
        try {
            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $now = time();

                // Fetch candidate without locking the whole table
                $sql = "SELECT id, payload FROM " . self::$table . " WHERE reserved_at IS NULL AND available_at <= $now ORDER BY id ASC LIMIT 1";
                $res = $db->query($sql);

                if (!empty($res) && isset($res[0])) {
                    $jobRow = $res[0];
                    // Optimistic lock attempt
                    $db->execute_query("UPDATE " . self::$table . " SET reserved_at = ?, attempts = attempts + 1 WHERE id = ? AND reserved_at IS NULL", [$now, $jobRow['id']]);

                    // Verify if we won the reservation
                    $check = $db->query("SELECT id FROM " . self::$table . " WHERE id = ? AND reserved_at = ?", [$jobRow['id'], $now]);

                    if (!empty($check)) {
                        $decoded = json_decode($jobRow['payload'], true);
                        if (is_array($decoded) && isset($decoded['class'])) {
                            $jobClass = $decoded['class'];
                            if (class_exists($jobClass) && is_subclass_of($jobClass, Job::class)) {
                                $job = new $jobClass($decoded['data'] ?? []);
                                if ($job instanceof Job) {
                                    return ['id' => $jobRow['id'], 'job' => $job];
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Queue pop failed: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Mark a job as complete.
     */
    public static function complete(int $id): void
    {
        try {
            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $db->execute_query("DELETE FROM " . self::$table . " WHERE id = ?", [$id]);
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * Get the size of the queue.
     */
    public static function size(): int
    {
        try {
            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $res = $db->query("SELECT COUNT(*) as count FROM " . self::$table);
                return (int) ($res[0]['count'] ?? 0);
            }
        } catch (\Exception $e) {
            // Ignore
        }
        return 0;
    }
}
