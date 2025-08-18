<?php

declare(strict_types=1);

namespace MailFlow\Middleware;

use MailFlow\Core\Config\ConfigManager;

class CorsMiddleware
{
    private ConfigManager $config;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function handle($request, callable $next): mixed
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = $this->config->get('cors.allowed_origins', []);
        
        if ($this->isOriginAllowed($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $this->config->get('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'])));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->config->get('cors.allowed_headers', ['Content-Type', 'Authorization'])));
        header('Access-Control-Allow-Credentials: ' . ($this->config->get('cors.allow_credentials', false) ? 'true' : 'false'));
        header('Access-Control-Max-Age: ' . $this->config->get('cors.max_age', 86400));

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        return $next($request);
    }

    private function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        if (in_array('*', $allowedOrigins)) {
            return true;
        }

        return in_array($origin, $allowedOrigins);
    }
}