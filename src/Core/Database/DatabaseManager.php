<?php

declare(strict_types=1);

namespace MailFlow\Core\Database;

use MailFlow\Core\Config\ConfigManager;
use MailFlow\Core\Exception\DatabaseException;
use PDO;
use PDOException;

class DatabaseManager
{
    private ConfigManager $config;
    private array $connections = [];
    private array $queryLog = [];
    private bool $enableQueryLog = false;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->enableQueryLog = $config->get('database.log_queries', false);
    }

    public function initialize(): void
    {
        // Initialize default connection
        $this->connection();
    }

    public function connection(string $name = 'default'): PDO
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $config = $this->config->get("database.connections.{$name}");
        
        if (!$config) {
            throw new DatabaseException("Database connection '{$name}' not configured");
        }

        try {
            $dsn = $this->buildDsn($config);
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options'] ?? []
            );

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            if ($this->enableQueryLog) {
                $pdo = new QueryLoggerProxy($pdo, $this->queryLog);
            }

            $this->connections[$name] = $pdo;
            return $pdo;

        } catch (PDOException $e) {
            throw new DatabaseException("Failed to connect to database: " . $e->getMessage());
        }
    }

    public function beginTransaction(string $connection = 'default'): void
    {
        $this->connection($connection)->beginTransaction();
    }

    public function commit(string $connection = 'default'): void
    {
        $this->connection($connection)->commit();
    }

    public function rollback(string $connection = 'default'): void
    {
        $this->connection($connection)->rollBack();
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function enableQueryLog(): void
    {
        $this->enableQueryLog = true;
    }

    public function disableQueryLog(): void
    {
        $this->enableQueryLog = false;
    }

    private function buildDsn(array $config): string
    {
        $driver = $config['driver'];
        
        return match ($driver) {
            'mysql' => "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}",
            'pgsql' => "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
            'sqlite' => "sqlite:{$config['database']}",
            default => throw new DatabaseException("Unsupported database driver: {$driver}")
        };
    }
}

class QueryLoggerProxy
{
    private PDO $pdo;
    private array &$queryLog;

    public function __construct(PDO $pdo, array &$queryLog)
    {
        $this->pdo = $pdo;
        $this->queryLog = &$queryLog;
    }

    public function __call(string $method, array $args): mixed
    {
        $start = microtime(true);
        $result = $this->pdo->$method(...$args);
        $time = microtime(true) - $start;

        if (in_array($method, ['query', 'exec', 'prepare'])) {
            $this->queryLog[] = [
                'query' => $args[0] ?? '',
                'time' => $time,
                'method' => $method,
                'timestamp' => time()
            ];
        }

        return $result;
    }

    public function __get(string $name): mixed
    {
        return $this->pdo->$name;
    }
}