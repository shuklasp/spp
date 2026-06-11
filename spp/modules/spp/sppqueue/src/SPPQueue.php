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
        $payload = base64_encode(serialize($job));
        try {
            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $db = new \SPPMod\SPPDB\SPPDB();
                $availableAt = time() + $delay;
                $db->exec("CREATE TABLE IF NOT EXISTS " . self::$table . " (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    payload LONGTEXT NOT NULL,
                    attempts INT DEFAULT 0,
                    reserved_at INT NULL,
                    available_at INT NOT NULL,
                    created_at INT NOT NULL
                )");
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
                $db->exec("START TRANSACTION");
                
                $now = time();
                $sql = "SELECT id, payload FROM " . self::$table . " WHERE reserved_at IS NULL AND available_at <= $now ORDER BY id ASC LIMIT 1 FOR UPDATE";
                $res = $db->query($sql);
                
                if (!empty($res) && isset($res[0])) {
                    $jobRow = $res[0];
                    $db->execute_query("UPDATE " . self::$table . " SET reserved_at = ?, attempts = attempts + 1 WHERE id = ?", [$now, $jobRow['id']]);
                    $db->exec("COMMIT");
                    
                    $job = unserialize(base64_decode($jobRow['payload']));
                    if ($job instanceof Job) {
                        return ['id' => $jobRow['id'], 'job' => $job];
                    }
                }
                $db->exec("COMMIT");
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
        } catch (\Exception $e) {}
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
