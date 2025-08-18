<?php

declare(strict_types=1);

namespace MailFlow\Core\Security;

use MailFlow\Core\Config\ConfigManager;
use MailFlow\Core\Cache\CacheManager;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PragmaRX\Google2FA\Google2FA;

class SecurityManager
{
    private ConfigManager $config;
    private CacheManager $cache;
    private Google2FA $google2fa;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
        $this->google2fa = new Google2FA();
    }

    public function setCache(CacheManager $cache): void
    {
        $this->cache = $cache;
    }

    public function generateJWT(array $payload, int $expiry = 3600): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;
        $payload['iss'] = $this->config->get('app.url');

        return JWT::encode($payload, $this->getJWTSecret(), 'HS256');
    }

    public function verifyJWT(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->getJWTSecret(), 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            throw new SecurityException('Invalid JWT token: ' . $e->getMessage());
        }
    }

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    public function generate2FASecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function verify2FA(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    public function generateQRCode(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            $this->config->get('app.name', 'MailFlow'),
            $email,
            $secret
        );
    }

    public function isRateLimited(string $key, int $maxAttempts = 5, int $decayMinutes = 15): bool
    {
        if (!$this->cache) {
            return false;
        }

        $attempts = $this->cache->get("rate_limit:{$key}", 0);
        
        if ($attempts >= $maxAttempts) {
            return true;
        }

        $this->cache->put("rate_limit:{$key}", $attempts + 1, $decayMinutes * 60);
        return false;
    }

    public function clearRateLimit(string $key): void
    {
        if ($this->cache) {
            $this->cache->forget("rate_limit:{$key}");
        }
    }

    public function encryptData(string $data): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    public function decryptData(string $encryptedData): string
    {
        $key = $this->getEncryptionKey();
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    public function sanitizeInput(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public function validateCSRFToken(string $token, string $sessionToken): bool
    {
        return hash_equals($sessionToken, $token);
    }

    public function generateCSRFToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function getJWTSecret(): string
    {
        $secret = $this->config->get('security.jwt_secret');
        
        if (!$secret) {
            throw new SecurityException('JWT secret not configured');
        }

        return $secret;
    }

    private function getEncryptionKey(): string
    {
        $key = $this->config->get('security.encryption_key');
        
        if (!$key) {
            throw new SecurityException('Encryption key not configured');
        }

        return $key;
    }
}

class SecurityException extends \Exception
{
}