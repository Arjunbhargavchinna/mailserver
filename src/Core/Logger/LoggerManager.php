<?php

declare(strict_types=1);

namespace MailFlow\Core\Logger;

use MailFlow\Core\Config\ConfigManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;

class LoggerManager
{
    private ConfigManager $config;
    private array $loggers = [];

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function initialize(): void
    {
        // Initialize default logger
        $this->getLogger();
    }

    public function getLogger(string $channel = 'default'): LoggerInterface
    {
        if (isset($this->loggers[$channel])) {
            return $this->loggers[$channel];
        }

        $config = $this->config->get("logging.channels.{$channel}") 
                 ?? $this->config->get('logging.channels.default');

        $logger = new Logger($channel);

        foreach ($config['handlers'] ?? [] as $handlerConfig) {
            $handler = $this->createHandler($handlerConfig);
            $logger->pushHandler($handler);
        }

        $this->loggers[$channel] = $logger;
        return $logger;
    }

    private function createHandler(array $config): mixed
    {
        $handler = match ($config['type']) {
            'stream' => new StreamHandler($config['path'], $config['level'] ?? Logger::DEBUG),
            'rotating' => new RotatingFileHandler(
                $config['path'], 
                $config['max_files'] ?? 5, 
                $config['level'] ?? Logger::DEBUG
            ),
            'syslog' => new SyslogHandler($config['ident'] ?? 'mailflow', LOG_USER, $config['level'] ?? Logger::DEBUG),
            default => throw new \InvalidArgumentException("Unsupported log handler: {$config['type']}")
        };

        if (isset($config['formatter'])) {
            $formatter = match ($config['formatter']) {
                'line' => new LineFormatter($config['format'] ?? null, $config['date_format'] ?? null),
                default => null
            };

            if ($formatter) {
                $handler->setFormatter($formatter);
            }
        }

        return $handler;
    }
}