<?php

declare(strict_types=1);

namespace MailFlow\Middleware;

use MailFlow\Core\Config\ConfigManager;

class SecurityHeadersMiddleware
{
    private ConfigManager $config;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function handle($request, callable $next): mixed
    {
        $response = $next($request);

        $headers = $this->config->get('security.headers', []);

        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                $value = $this->buildHeaderValue($name, $value);
            }
            
            header("{$name}: {$value}");
        }

        return $response;
    }

    private function buildHeaderValue(string $name, array $config): string
    {
        return match ($name) {
            'content_security_policy' => $this->buildCSP($config),
            'strict_transport_security' => $this->buildHSTS($config),
            default => implode('; ', array_map(
                fn($k, $v) => "{$k} {$v}",
                array_keys($config),
                array_values($config)
            ))
        };
    }

    private function buildCSP(array $config): string
    {
        $directives = [];
        
        foreach ($config as $directive => $sources) {
            if (is_array($sources)) {
                $directives[] = $directive . ' ' . implode(' ', $sources);
            } else {
                $directives[] = $directive . ' ' . $sources;
            }
        }

        return implode('; ', $directives);
    }

    private function buildHSTS(array $config): string
    {
        $value = "max-age={$config['max_age']}";
        
        if ($config['include_subdomains'] ?? false) {
            $value .= '; includeSubDomains';
        }
        
        if ($config['preload'] ?? false) {
            $value .= '; preload';
        }

        return $value;
    }
}