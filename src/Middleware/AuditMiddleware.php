<?php

declare(strict_types=1);

namespace MailFlow\Middleware;

use MailFlow\Core\Database\DatabaseManager;
use MailFlow\Core\Config\ConfigManager;

class AuditMiddleware
{
    private DatabaseManager $database;
    private ConfigManager $config;

    public function __construct(DatabaseManager $database, ConfigManager $config)
    {
        $this->database = $database;
        $this->config = $config;
    }

    public function handle($request, callable $next): mixed
    {
        $startTime = microtime(true);
        $response = $next($request);
        $endTime = microtime(true);

        if ($this->shouldAudit()) {
            $this->logRequest($startTime, $endTime);
        }

        return $response;
    }

    private function shouldAudit(): bool
    {
        if (!$this->config->get('security.audit.enabled', true)) {
            return false;
        }

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // Don't audit static assets or health checks
        if (preg_match('/\.(css|js|png|jpg|gif|ico|woff|woff2)$/', $path)) {
            return false;
        }

        if ($path === '/health' || $path === '/ping') {
            return false;
        }

        return true;
    }

    private function logRequest(float $startTime, float $endTime): void
    {
        $user = $_REQUEST['auth_user'] ?? null;
        $duration = round(($endTime - $startTime) * 1000, 2); // milliseconds

        $stmt = $this->database->connection()->prepare("
            INSERT INTO audit_logs (
                user_id, action, details, ip_address, user_agent, 
                request_method, request_path, response_time, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $user['id'] ?? null,
            'api_request',
            json_encode([
                'method' => $_SERVER['REQUEST_METHOD'],
                'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
                'query' => $_SERVER['QUERY_STRING'] ?? '',
                'response_time_ms' => $duration,
            ]),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $_SERVER['REQUEST_METHOD'],
            parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
            $duration,
        ]);
    }
}