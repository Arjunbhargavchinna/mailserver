<?php

declare(strict_types=1);

namespace MailFlow\Middleware;

use MailFlow\Core\Security\SecurityManager;
use MailFlow\Core\Database\DatabaseManager;

class AuthenticationMiddleware
{
    private SecurityManager $security;
    private DatabaseManager $database;

    public function __construct(SecurityManager $security, DatabaseManager $database)
    {
        $this->security = $security;
        $this->database = $database;
    }

    public function handle($request, callable $next): mixed
    {
        $token = $this->extractToken();
        
        if (!$token) {
            return $this->unauthorized();
        }

        try {
            $payload = $this->security->verifyJWT($token);
            $user = $this->getUser($payload['sub']);
            
            if (!$user || !$user['is_active']) {
                return $this->unauthorized();
            }

            // Add user to request context
            $_REQUEST['auth_user'] = $user;
            
        } catch (\Exception $e) {
            return $this->unauthorized();
        }

        return $next($request);
    }

    private function extractToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return $_COOKIE['auth_token'] ?? null;
    }

    private function getUser(int $userId): ?array
    {
        $stmt = $this->database->connection()->prepare("
            SELECT * FROM users WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch() ?: null;
    }

    private function unauthorized(): array
    {
        http_response_code(401);
        return ['error' => 'Unauthorized'];
    }
}