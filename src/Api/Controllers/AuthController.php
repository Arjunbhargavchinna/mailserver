<?php

declare(strict_types=1);

namespace MailFlow\Api\Controllers;

use MailFlow\Core\Security\SecurityManager;
use MailFlow\Core\Database\DatabaseManager;
use MailFlow\Core\Router\Response;

class AuthController
{
    private SecurityManager $security;
    private DatabaseManager $database;

    public function __construct(SecurityManager $security, DatabaseManager $database)
    {
        $this->security = $security;
        $this->database = $database;
    }

    public function login(): Response
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $remember = $input['remember'] ?? false;

        if (empty($email) || empty($password)) {
            return new Response(['error' => 'Email and password are required'], 400);
        }

        // Rate limiting
        $rateLimitKey = 'login_attempts:' . $_SERVER['REMOTE_ADDR'];
        if ($this->security->isRateLimited($rateLimitKey, 5, 15)) {
            return new Response(['error' => 'Too many login attempts'], 429);
        }

        // Get user
        $stmt = $this->database->connection()->prepare("
            SELECT * FROM users WHERE email = ? AND is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !$this->security->verifyPassword($password, $user['password'])) {
            return new Response(['error' => 'Invalid credentials'], 401);
        }

        // Check for 2FA
        if ($user['two_factor_enabled'] && empty($input['two_factor_code'])) {
            return new Response([
                'requires_2fa' => true,
                'message' => 'Two-factor authentication required'
            ], 200);
        }

        if ($user['two_factor_enabled']) {
            if (!$this->security->verify2FA($user['two_factor_secret'], $input['two_factor_code'])) {
                return new Response(['error' => 'Invalid two-factor code'], 401);
            }
        }

        // Generate tokens
        $tokenExpiry = $remember ? 86400 * 30 : 3600; // 30 days or 1 hour
        $accessToken = $this->security->generateJWT([
            'sub' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
        ], $tokenExpiry);

        $refreshToken = $this->security->generateJWT([
            'sub' => $user['id'],
            'type' => 'refresh',
        ], 86400 * 7); // 7 days

        // Update last login
        $stmt = $this->database->connection()->prepare("
            UPDATE users SET last_login = NOW(), failed_attempts = 0 WHERE id = ?
        ");
        $stmt->execute([$user['id']]);

        // Log successful login
        $this->logActivity($user['id'], 'login', 'User logged in successfully');

        // Clear rate limit
        $this->security->clearRateLimit($rateLimitKey);

        return new Response([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokenExpiry,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
            ],
        ]);
    }

    public function logout(): Response
    {
        $user = $_REQUEST['auth_user'] ?? null;
        
        if ($user) {
            $this->logActivity($user['id'], 'logout', 'User logged out');
        }

        // In a real implementation, you would blacklist the JWT token
        return new Response(['message' => 'Logged out successfully']);
    }

    public function refresh(): Response
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $input['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            return new Response(['error' => 'Refresh token required'], 400);
        }

        try {
            $payload = $this->security->verifyJWT($refreshToken);
            
            if ($payload['type'] !== 'refresh') {
                throw new \Exception('Invalid token type');
            }

            // Get user
            $stmt = $this->database->connection()->prepare("
                SELECT * FROM users WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$payload['sub']]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new \Exception('User not found');
            }

            // Generate new access token
            $accessToken = $this->security->generateJWT([
                'sub' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
            ], 3600);

            return new Response([
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]);

        } catch (\Exception $e) {
            return new Response(['error' => 'Invalid refresh token'], 401);
        }
    }

    public function me(): Response
    {
        $user = $_REQUEST['auth_user'] ?? null;
        
        if (!$user) {
            return new Response(['error' => 'Unauthorized'], 401);
        }

        return new Response([
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'created_at' => $user['created_at'],
            'last_login' => $user['last_login'],
        ]);
    }

    private function logActivity(int $userId, string $action, string $details): void
    {
        $stmt = $this->database->connection()->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
    }
}