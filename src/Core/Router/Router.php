<?php

declare(strict_types=1);

namespace MailFlow\Core\Router;

use MailFlow\Core\Container\Container;
use MailFlow\Core\Exception\RouterException;

class Router
{
    private Container $container;
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(string $path, $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function options(string $path, $handler): Route
    {
        return $this->addRoute('OPTIONS', $path, $handler);
    }

    public function group(array $attributes, callable $callback): void
    {
        $oldPrefix = $this->prefix;
        $oldMiddleware = $this->middleware;

        if (isset($attributes['prefix'])) {
            $this->prefix .= '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $this->middleware = array_merge($this->middleware, (array) $attributes['middleware']);
        }

        $callback($this);

        $this->prefix = $oldPrefix;
        $this->middleware = $oldMiddleware;
    }

    public function dispatch(): Response
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                return $this->handleRoute($route, $path);
            }
        }

        throw new RouterException("Route not found: {$method} {$path}", 404);
    }

    private function addRoute(string $method, string $path, $handler): Route
    {
        $fullPath = $this->prefix . '/' . ltrim($path, '/');
        $route = new Route($method, $fullPath, $handler);
        $route->middleware($this->middleware);
        
        $this->routes[] = $route;
        return $route;
    }

    private function handleRoute(Route $route, string $path): Response
    {
        $parameters = $route->extractParameters($path);
        $handler = $route->getHandler();

        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controller, $method] = explode('@', $handler);
            $controllerInstance = $this->container->get($controller);
            $response = $controllerInstance->$method(...array_values($parameters));
        } elseif (is_callable($handler)) {
            $response = $handler(...array_values($parameters));
        } else {
            throw new RouterException("Invalid route handler");
        }

        if (!$response instanceof Response) {
            $response = new Response($response);
        }

        return $response;
    }
}

class Route
{
    private string $method;
    private string $path;
    private $handler;
    private array $middleware = [];
    private array $parameters = [];

    public function __construct(string $method, string $path, $handler)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
    }

    public function middleware($middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    public function name(string $name): self
    {
        // Store route name for URL generation
        return $this;
    }

    public function matches(string $method, string $path): bool
    {
        if ($this->method !== $method) {
            return false;
        }

        $pattern = $this->convertToRegex($this->path);
        return preg_match($pattern, $path, $this->parameters);
    }

    public function extractParameters(string $path): array
    {
        $pattern = $this->convertToRegex($this->path);
        preg_match($pattern, $path, $matches);
        
        return array_slice($matches, 1);
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}

class Response
{
    private $content;
    private int $statusCode;
    private array $headers;

    public function __construct($content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if (is_array($this->content) || is_object($this->content)) {
            header('Content-Type: application/json');
            echo json_encode($this->content);
        } else {
            echo $this->content;
        }
    }

    public function json($data, int $statusCode = 200): self
    {
        $this->content = $data;
        $this->statusCode = $statusCode;
        $this->headers['Content-Type'] = 'application/json';
        return $this;
    }

    public function redirect(string $url, int $statusCode = 302): self
    {
        $this->statusCode = $statusCode;
        $this->headers['Location'] = $url;
        return $this;
    }
}

class RouterException extends \Exception
{
}