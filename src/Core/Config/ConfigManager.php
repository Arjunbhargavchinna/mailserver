<?php

declare(strict_types=1);

namespace MailFlow\Core\Config;

class ConfigManager
{
    private array $config = [];
    private string $configPath;

    public function __construct(string $configPath)
    {
        $this->configPath = $configPath;
        $this->loadConfiguration();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config = $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function all(): array
    {
        return $this->config;
    }

    private function loadConfiguration(): void
    {
        if (!is_dir($this->configPath)) {
            return;
        }

        $files = glob($this->configPath . '/*.php');

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $this->config[$key] = require $file;
        }

        // Load environment-specific overrides
        $env = $_ENV['APP_ENV'] ?? 'production';
        $envFile = $this->configPath . "/env/{$env}.php";
        
        if (file_exists($envFile)) {
            $envConfig = require $envFile;
            $this->config = array_merge_recursive($this->config, $envConfig);
        }
    }
}