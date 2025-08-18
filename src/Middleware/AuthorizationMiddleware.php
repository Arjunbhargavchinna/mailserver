<?php

declare(strict_types=1);

namespace MailFlow\Middleware;

class AuthorizationMiddleware
{
    public function handle($request, callable $next): mixed
    {
        $user = $_REQUEST['auth_user'] ?? null;
        
        if (!$user) {
            return $this->forbidden();
        }

        $requiredRole = $this->getRequiredRole();
        
        if ($requiredRole && !$this->hasRole($user, $requiredRole)) {
            return $this->forbidden();
        }

        return $next($request);
    }

    private function getRequiredRole(): ?string
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        if (strpos($path, '/admin') === 0) {
            return 'Administrator';
        }
        
        if (strpos($path, '/api/admin') === 0) {
            return 'Administrator';
        }

        return null;
    }

    private function hasRole(array $user, string $requiredRole): bool
    {
        $roleHierarchy = [
            'User' => 1,
            'Manager' => 2,
            'Administrator' => 3,
        ];

        $userLevel = $roleHierarchy[$user['role']] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;

        return $userLevel >= $requiredLevel;
    }

    private function forbidden(): array
    {
        http_response_code(403);
        return ['error' => 'Forbidden'];
    }
}