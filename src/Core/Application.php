<?php

declare(strict_types=1);

namespace MailFlow\Core;

use MailFlow\Core\Container\Container;
use MailFlow\Core\Config\ConfigManager;
use MailFlow\Core\Database\DatabaseManager;
use MailFlow\Core\Cache\CacheManager;
use MailFlow\Core\Logger\LoggerManager;
use MailFlow\Core\Security\SecurityManager;
use MailFlow\Core\Queue\QueueManager;
use MailFlow\Core\Storage\StorageManager;
use MailFlow\Core\Search\SearchManager;
use MailFlow\Core\Notification\NotificationManager;
use MailFlow\Core\Middleware\MiddlewareStack;
use MailFlow\Core\Router\Router;
use MailFlow\Core\Exception\ApplicationException;

class Application
{
    private Container $container;
    private ConfigManager $config;
    private bool $booted = false;

    public function __construct(string $configPath = null)
    {
        $this->container = new Container();
        $this->config = new ConfigManager($configPath ?? __DIR__ . '/../../config');
        $this->registerCoreServices();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->initializeServices();
        $this->registerMiddleware();
        $this->booted = true;
    }

    public function run(): void
    {
        if (!$this->booted) {
            $this->boot();
        }

        try {
            $router = $this->container->get(Router::class);
            $response = $router->dispatch();
            $response->send();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    private function registerCoreServices(): void
    {
        $this->container->singleton(ConfigManager::class, fn() => $this->config);
        $this->container->singleton(DatabaseManager::class, fn() => new DatabaseManager($this->config));
        $this->container->singleton(CacheManager::class, fn() => new CacheManager($this->config));
        $this->container->singleton(LoggerManager::class, fn() => new LoggerManager($this->config));
        $this->container->singleton(SecurityManager::class, fn() => new SecurityManager($this->config));
        $this->container->singleton(QueueManager::class, fn() => new QueueManager($this->config));
        $this->container->singleton(StorageManager::class, fn() => new StorageManager($this->config));
        $this->container->singleton(SearchManager::class, fn() => new SearchManager($this->config));
        $this->container->singleton(NotificationManager::class, fn() => new NotificationManager($this->config));
        $this->container->singleton(Router::class, fn() => new Router($this->container));
        $this->container->singleton(MiddlewareStack::class, fn() => new MiddlewareStack());
    }

    private function initializeServices(): void
    {
        $this->container->get(DatabaseManager::class)->initialize();
        $this->container->get(CacheManager::class)->initialize();
        $this->container->get(LoggerManager::class)->initialize();
        $this->container->get(QueueManager::class)->initialize();
        $this->container->get(SearchManager::class)->initialize();
    }

    private function registerMiddleware(): void
    {
        $middleware = $this->container->get(MiddlewareStack::class);
        
        // Core middleware
        $middleware->add(\MailFlow\Middleware\SecurityHeadersMiddleware::class);
        $middleware->add(\MailFlow\Middleware\CorsMiddleware::class);
        $middleware->add(\MailFlow\Middleware\RateLimitMiddleware::class);
        $middleware->add(\MailFlow\Middleware\AuthenticationMiddleware::class);
        $middleware->add(\MailFlow\Middleware\AuthorizationMiddleware::class);
        $middleware->add(\MailFlow\Middleware\AuditMiddleware::class);
    }

    private function handleException(\Throwable $e): void
    {
        $logger = $this->container->get(LoggerManager::class)->getLogger('application');
        $logger->error('Application error', [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        if ($this->config->get('app.debug', false)) {
            throw $e;
        }

        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
}