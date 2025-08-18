<?php

declare(strict_types=1);

namespace MailFlow\Middleware;

use MailFlow\Core\Cache\CacheManager;
use MailFlow\Core\Config\ConfigManager;

class RateLimitMiddleware
{
    private CacheManager $cache;
    private ConfigManager $config;

    public function __construct(CacheManager $cache, ConfigManager $config)
    {
        $this->cache = $cache;
        $this->config = $config;
    }

    public function handle($request, callable $next): mixed
    {
        $key = $this->getRateLimitKey();
        $maxAttempts = $this->config->get('security.rate_limiting.api.max_attempts', 1000);
        $decayMinutes = $this->config->get('security.rate_limiting.api.decay_minutes', 60);

        $attempts = $this->cache->get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            http_response_code(429);
            header('Retry-After: ' . ($decayMinutes * 60));
            echo json_encode(['error' => 'Too Many Requests']);
            exit;
        }

        $this->cache->put($key, $attempts + 1, $decayMinutes * 60);

        $response = $next($request);

        header('X-RateLimit-Limit: ' . $maxAttempts);
        header('X-RateLimit-Remaining: ' . max(0, $maxAttempts - $attempts - 1));
        header('X-RateLimit-Reset: ' . (time() + ($decayMinutes * 60)));

        return $response;
    }

    private function getRateLimitKey(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        return 'rate_limit:' . md5($ip . $userAgent);
    }
}