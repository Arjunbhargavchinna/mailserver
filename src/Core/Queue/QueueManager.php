<?php

declare(strict_types=1);

namespace MailFlow\Core\Queue;

use MailFlow\Core\Config\ConfigManager;
use MailFlow\Core\Database\DatabaseManager;
use MailFlow\Core\Logger\LoggerManager;
use Predis\Client as RedisClient;

class QueueManager
{
    private ConfigManager $config;
    private DatabaseManager $database;
    private LoggerManager $logger;
    private array $drivers = [];

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function setDatabase(DatabaseManager $database): void
    {
        $this->database = $database;
    }

    public function setLogger(LoggerManager $logger): void
    {
        $this->logger = $logger;
    }

    public function initialize(): void
    {
        // Initialize default queue driver
        $this->driver();
    }

    public function driver(string $name = 'default'): QueueDriverInterface
    {
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $config = $this->config->get("queue.connections.{$name}");
        
        if (!$config) {
            throw new QueueException("Queue connection '{$name}' not configured");
        }

        $driver = match ($config['driver']) {
            'redis' => new RedisQueueDriver($config),
            'database' => new DatabaseQueueDriver($config, $this->database),
            'sync' => new SyncQueueDriver($config),
            default => throw new QueueException("Unsupported queue driver: {$config['driver']}")
        };

        $this->drivers[$name] = $driver;
        return $driver;
    }

    public function push(string $job, array $data = [], string $queue = 'default'): string
    {
        return $this->driver()->push($job, $data, $queue);
    }

    public function pushDelayed(string $job, array $data = [], int $delay = 0, string $queue = 'default'): string
    {
        return $this->driver()->pushDelayed($job, $data, $delay, $queue);
    }

    public function pop(string $queue = 'default'): ?QueueJob
    {
        return $this->driver()->pop($queue);
    }

    public function size(string $queue = 'default'): int
    {
        return $this->driver()->size($queue);
    }

    public function clear(string $queue = 'default'): void
    {
        $this->driver()->clear($queue);
    }
}

interface QueueDriverInterface
{
    public function push(string $job, array $data = [], string $queue = 'default'): string;
    public function pushDelayed(string $job, array $data = [], int $delay = 0, string $queue = 'default'): string;
    public function pop(string $queue = 'default'): ?QueueJob;
    public function size(string $queue = 'default'): int;
    public function clear(string $queue = 'default'): void;
}

class QueueJob
{
    public string $id;
    public string $job;
    public array $data;
    public string $queue;
    public int $attempts;
    public int $createdAt;

    public function __construct(string $id, string $job, array $data, string $queue, int $attempts = 0, int $createdAt = null)
    {
        $this->id = $id;
        $this->job = $job;
        $this->data = $data;
        $this->queue = $queue;
        $this->attempts = $attempts;
        $this->createdAt = $createdAt ?? time();
    }
}

class RedisQueueDriver implements QueueDriverInterface
{
    private RedisClient $redis;
    private string $prefix;

    public function __construct(array $config)
    {
        $this->redis = new RedisClient($config['connection'] ?? []);
        $this->prefix = $config['prefix'] ?? 'mailflow:queue:';
    }

    public function push(string $job, array $data = [], string $queue = 'default'): string
    {
        $id = uniqid('job_', true);
        $payload = [
            'id' => $id,
            'job' => $job,
            'data' => $data,
            'queue' => $queue,
            'attempts' => 0,
            'created_at' => time()
        ];

        $this->redis->lpush($this->prefix . $queue, serialize($payload));
        return $id;
    }

    public function pushDelayed(string $job, array $data = [], int $delay = 0, string $queue = 'default'): string
    {
        $id = uniqid('job_', true);
        $payload = [
            'id' => $id,
            'job' => $job,
            'data' => $data,
            'queue' => $queue,
            'attempts' => 0,
            'created_at' => time()
        ];

        $score = time() + $delay;
        $this->redis->zadd($this->prefix . 'delayed:' . $queue, [$score => serialize($payload)]);
        return $id;
    }

    public function pop(string $queue = 'default'): ?QueueJob
    {
        // Move delayed jobs to ready queue
        $this->moveDelayedJobs($queue);

        $payload = $this->redis->rpop($this->prefix . $queue);
        
        if (!$payload) {
            return null;
        }

        $data = unserialize($payload);
        return new QueueJob(
            $data['id'],
            $data['job'],
            $data['data'],
            $data['queue'],
            $data['attempts'],
            $data['created_at']
        );
    }

    public function size(string $queue = 'default'): int
    {
        return $this->redis->llen($this->prefix . $queue);
    }

    public function clear(string $queue = 'default'): void
    {
        $this->redis->del($this->prefix . $queue);
        $this->redis->del($this->prefix . 'delayed:' . $queue);
    }

    private function moveDelayedJobs(string $queue): void
    {
        $now = time();
        $jobs = $this->redis->zrangebyscore(
            $this->prefix . 'delayed:' . $queue,
            0,
            $now
        );

        foreach ($jobs as $job) {
            $this->redis->lpush($this->prefix . $queue, $job);
            $this->redis->zrem($this->prefix . 'delayed:' . $queue, $job);
        }
    }
}

class DatabaseQueueDriver implements QueueDriverInterface
{
    private array $config;
    private DatabaseManager $database;

    public function __construct(array $config, DatabaseManager $database)
    {
        $this->config = $config;
        $this->database = $database;
    }

    public function push(string $job, array $data = [], string $queue = 'default'): string
    {
        $id = uniqid('job_', true);
        
        $stmt = $this->database->connection()->prepare("
            INSERT INTO queue_jobs (id, queue, job, data, attempts, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        $stmt->execute([$id, $queue, $job, json_encode($data)]);
        return $id;
    }

    public function pushDelayed(string $job, array $data = [], int $delay = 0, string $queue = 'default'): string
    {
        $id = uniqid('job_', true);
        $availableAt = date('Y-m-d H:i:s', time() + $delay);
        
        $stmt = $this->database->connection()->prepare("
            INSERT INTO queue_jobs (id, queue, job, data, attempts, available_at, created_at)
            VALUES (?, ?, ?, ?, 0, ?, NOW())
        ");
        
        $stmt->execute([$id, $queue, $job, json_encode($data), $availableAt]);
        return $id;
    }

    public function pop(string $queue = 'default'): ?QueueJob
    {
        $stmt = $this->database->connection()->prepare("
            SELECT * FROM queue_jobs 
            WHERE queue = ? AND (available_at IS NULL OR available_at <= NOW())
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        
        $stmt->execute([$queue]);
        $row = $stmt->fetch();
        
        if (!$row) {
            return null;
        }

        // Delete the job from the queue
        $deleteStmt = $this->database->connection()->prepare("DELETE FROM queue_jobs WHERE id = ?");
        $deleteStmt->execute([$row['id']]);

        return new QueueJob(
            $row['id'],
            $row['job'],
            json_decode($row['data'], true),
            $row['queue'],
            $row['attempts'],
            strtotime($row['created_at'])
        );
    }

    public function size(string $queue = 'default'): int
    {
        $stmt = $this->database->connection()->prepare("
            SELECT COUNT(*) FROM queue_jobs 
            WHERE queue = ? AND (available_at IS NULL OR available_at <= NOW())
        ");
        
        $stmt->execute([$queue]);
        return $stmt->fetchColumn();
    }

    public function clear(string $queue = 'default'): void
    {
        $stmt = $this->database->connection()->prepare("DELETE FROM queue_jobs WHERE queue = ?");
        $stmt->execute([$queue]);
    }
}

class SyncQueueDriver implements QueueDriverInterface
{
    public function push(string $job, array $data = [], string $queue = 'default'): string
    {
        $this->executeJob($job, $data);
        return uniqid('sync_', true);
    }

    public function pushDelayed(string $job, array $data = [], int $delay = 0, string $queue = 'default'): string
    {
        if ($delay > 0) {
            sleep($delay);
        }
        return $this->push($job, $data, $queue);
    }

    public function pop(string $queue = 'default'): ?QueueJob
    {
        return null; // Sync driver executes immediately
    }

    public function size(string $queue = 'default'): int
    {
        return 0; // Sync driver has no queue
    }

    public function clear(string $queue = 'default'): void
    {
        // Nothing to clear in sync driver
    }

    private function executeJob(string $job, array $data): void
    {
        if (class_exists($job)) {
            $instance = new $job();
            if (method_exists($instance, 'handle')) {
                $instance->handle($data);
            }
        }
    }
}

class QueueException extends \Exception
{
}