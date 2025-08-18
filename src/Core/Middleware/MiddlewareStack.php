<?php

declare(strict_types=1);

namespace MailFlow\Core\Middleware;

use MailFlow\Core\Container\Container;

class MiddlewareStack
{
    private array $middleware = [];
    private Container $container;

    public function __construct(Container $container = null)
    {
        $this->container = $container ?? new Container();
    }

    public function add(string $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function handle($request, callable $next): mixed
    {
        return $this->processMiddleware($request, $next, 0);
    }

    private function processMiddleware($request, callable $final, int $index): mixed
    {
        if ($index >= count($this->middleware)) {
            return $final($request);
        }

        $middlewareClass = $this->middleware[$index];
        $middleware = $this->container->get($middlewareClass);

        return $middleware->handle($request, function ($request) use ($final, $index) {
            return $this->processMiddleware($request, $final, $index + 1);
        });
    }
}