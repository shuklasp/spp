<?php

namespace SPPMod\SPPQueue;

/**
 * class SppQueue
 *
 * An enterprise task queue system supporting external brokers (RabbitMQ, Beanstalkd)
 * with a database fallback mechanism.
 */
class SppQueue
{
    /**
     * Push a new task onto the queue.
     */
    public static function push(string $jobClass, array $data = []): void
    {
        $payload = json_encode([
            'job' => $jobClass,
            'data' => $data,
            'created_at' => time(),
            'id' => uniqid('job_')
        ]);

        if (self::tryRabbitMQ('push', $payload)) {
            return;
        }

        if (self::tryBeanstalkd('push', $payload)) {
            return;
        }

        self::fallbackDbPush($payload);
    }

    /**
     * Process the next job in the queue.
     */
    public static function work(): void
    {
        if (self::tryRabbitMQ('work')) {
            return;
        }

        if (self::tryBeanstalkd('work')) {
            return;
        }

        $jobDataStr = self::fallbackDbWork();
        if ($jobDataStr) {
            self::processJob($jobDataStr);
        }
    }

    private static function processJob(string $jobDataStr): void
    {
        $jobData = json_decode($jobDataStr, true);
        if (!$jobData || !isset($jobData['job'])) {
            return;
        }

        $jobClass = $jobData['job'];
        if (class_exists($jobClass)) {
            $job = \SPP\Registry::make($jobClass);
            if (method_exists($job, 'handle')) {
                $job->handle($jobData['data'] ?? []);
            }
        }
    }

    private static function tryRabbitMQ(string $action, string $payload = null): bool
    {
        if (!class_exists('\PhpAmqpLib\Connection\AMQPStreamConnection')) {
            return false;
        }

        try {
            $host = \SPP\App::getConfig('rabbitmq_host') ?: 'localhost';
            $port = \SPP\App::getConfig('rabbitmq_port') ?: 5672;
            $user = \SPP\App::getConfig('rabbitmq_user') ?: 'guest';
            $pass = \SPP\App::getConfig('rabbitmq_pass') ?: 'guest';

            $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection($host, $port, $user, $pass);
            $channel = $connection->channel();
            $channel->queue_declare('spp_jobs', false, true, false, false);

            if ($action === 'push') {
                $msg = new \PhpAmqpLib\Message\AMQPMessage($payload);
                $channel->basic_publish($msg, '', 'spp_jobs');
            } elseif ($action === 'work') {
                $message = $channel->basic_get('spp_jobs');
                if ($message) {
                    $channel->basic_ack($message->getDeliveryTag());
                    self::processJob($message->body);
                }
            }

            $channel->close();
            $connection->close();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function tryBeanstalkd(string $action, string $payload = null): bool
    {
        if (!class_exists('\Pheanstalk\Pheanstalk')) {
            return false;
        }

        try {
            $host = \SPP\App::getConfig('beanstalkd_host') ?: '127.0.0.1';
            $pheanstalk = \Pheanstalk\Pheanstalk::create($host);

            if ($action === 'push') {
                $pheanstalk->useTube('spp_jobs')->put($payload);
            } elseif ($action === 'work') {
                $job = $pheanstalk->watch('spp_jobs')->reserveWithTimeout(0);
                if ($job) {
                    $pheanstalk->delete($job);
                    self::processJob($job->getData());
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function fallbackDbPush(string $payload): void
    {
        if (!class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            return;
        }

        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            if (method_exists($db, 'pdo')) {
                $stmt = $db->pdo()->prepare("INSERT INTO spp_jobs (payload, status, created_at) VALUES (?, 'pending', ?)");
                $stmt->execute([$payload, time()]);
            }
        } catch (\Exception $e) {
            error_log("Queue DB push failed: " . $e->getMessage());
        }
    }

    private static function fallbackDbWork(): ?string
    {
        if (!class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            return null;
        }

        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            if (method_exists($db, 'pdo')) {
                $pdo = $db->pdo();
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("SELECT id, payload FROM spp_jobs WHERE status = 'pending' ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $stmt->execute();
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($row) {
                    $update = $pdo->prepare("UPDATE spp_jobs SET status = 'processing' WHERE id = ?");
                    $update->execute([$row['id']]);
                    $pdo->commit();
                    
                    $delete = $pdo->prepare("DELETE FROM spp_jobs WHERE id = ?");
                    $delete->execute([$row['id']]);
                    
                    return $row['payload'];
                }
                
                $pdo->rollBack();
            }
        } catch (\Exception $e) {
            error_log("Queue DB work failed: " . $e->getMessage());
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        return null;
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

