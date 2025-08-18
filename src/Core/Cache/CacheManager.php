<?php

declare(strict_types=1);

namespace MailFlow\Core\Cache;

use MailFlow\Core\Config\ConfigManager;
use MailFlow\Core\Exception\CacheException;
use Predis\Client as RedisClient;

class CacheManager
{
    private ConfigManager $config;
    private array $stores = [];

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function initialize(): void
    {
        // Initialize default cache store
        $this->store();
    }

    public function store(string $name = 'default'): CacheStoreInterface
    {
        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        $config = $this->config->get("cache.stores.{$name}");
        
        if (!$config) {
            throw new CacheException("Cache store '{$name}' not configured");
        }

        $store = match ($config['driver']) {
            'redis' => new RedisStore($config),
            'file' => new FileStore($config),
            'memory' => new MemoryStore($config),
            default => throw new CacheException("Unsupported cache driver: {$config['driver']}")
        };

        $this->stores[$name] = $store;
        return $store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->store()->put($key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->store()->forget($key);
    }

    public function flush(): bool
    {
        return $this->store()->flush();
    }

    public function remember(string $key, callable $callback, int $ttl = 3600): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);
        
        return $value;
    }
}

interface CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value, int $ttl = 3600): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
}

class RedisStore implements CacheStoreInterface
{
    private RedisClient $redis;
    private string $prefix;

    public function __construct(array $config)
    {
        $this->redis = new RedisClient($config['connection'] ?? []);
        $this->prefix = $config['prefix'] ?? 'mailflow:';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);
        return $value !== null ? unserialize($value) : $default;
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->redis->setex($this->prefix . $key, $ttl, serialize($value)) === 'OK';
    }

    public function forget(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function flush(): bool
    {
        $keys = $this->redis->keys($this->prefix . '*');
        return empty($keys) || $this->redis->del($keys) > 0;
    }
}

class FileStore implements CacheStoreInterface
{
    private string $path;

    public function __construct(array $config)
    {
        $this->path = $config['path'] ?? sys_get_temp_dir() . '/mailflow_cache';
        
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));
        
        if ($data['expires'] < time()) {
            unlink($file);
            return $default;
        }

        return $data['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        return file_put_contents($file, serialize($data)) !== false;
    }

    public function forget(string $key): bool
    {
        $file = $this->getFilePath($key);
        return !file_exists($file) || unlink($file);
    }

    public function flush(): bool
    {
        $files = glob($this->path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    private function getFilePath(string $key): string
    {
        return $this->path . '/' . md5($key) . '.cache';
    }
}

class MemoryStore implements CacheStoreInterface
{
    private array $data = [];

    public function __construct(array $config)
    {
        // Memory store doesn't need configuration
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->data[$key])) {
            return $default;
        }

        $item = $this->data[$key];
        
        if ($item['expires'] < time()) {
            unset($this->data[$key]);
            return $default;
        }

        return $item['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $this->data[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        return true;
    }

    public function forget(string $key): bool
    {
        unset($this->data[$key]);
        return true;
    }

    public function flush(): bool
    {
        $this->data = [];
        return true;
    }
}