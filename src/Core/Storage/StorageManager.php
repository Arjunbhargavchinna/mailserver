<?php

declare(strict_types=1);

namespace MailFlow\Core\Storage;

use MailFlow\Core\Config\ConfigManager;
use MailFlow\Core\Exception\StorageException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Sftp\SftpAdapter;
use Aws\S3\S3Client;

class StorageManager
{
    private ConfigManager $config;
    private array $disks = [];

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function disk(string $name = 'default'): Filesystem
    {
        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        $config = $this->config->get("storage.disks.{$name}");
        
        if (!$config) {
            throw new StorageException("Storage disk '{$name}' not configured");
        }

        $adapter = $this->createAdapter($config);
        $filesystem = new Filesystem($adapter);

        $this->disks[$name] = $filesystem;
        return $filesystem;
    }

    public function put(string $path, string $contents, string $disk = 'default'): bool
    {
        try {
            $this->disk($disk)->write($path, $contents);
            return true;
        } catch (\Exception $e) {
            throw new StorageException("Failed to write file: " . $e->getMessage());
        }
    }

    public function get(string $path, string $disk = 'default'): string
    {
        try {
            return $this->disk($disk)->read($path);
        } catch (\Exception $e) {
            throw new StorageException("Failed to read file: " . $e->getMessage());
        }
    }

    public function exists(string $path, string $disk = 'default'): bool
    {
        return $this->disk($disk)->fileExists($path);
    }

    public function delete(string $path, string $disk = 'default'): bool
    {
        try {
            $this->disk($disk)->delete($path);
            return true;
        } catch (\Exception $e) {
            throw new StorageException("Failed to delete file: " . $e->getMessage());
        }
    }

    public function copy(string $from, string $to, string $disk = 'default'): bool
    {
        try {
            $this->disk($disk)->copy($from, $to);
            return true;
        } catch (\Exception $e) {
            throw new StorageException("Failed to copy file: " . $e->getMessage());
        }
    }

    public function move(string $from, string $to, string $disk = 'default'): bool
    {
        try {
            $this->disk($disk)->move($from, $to);
            return true;
        } catch (\Exception $e) {
            throw new StorageException("Failed to move file: " . $e->getMessage());
        }
    }

    public function size(string $path, string $disk = 'default'): int
    {
        try {
            return $this->disk($disk)->fileSize($path);
        } catch (\Exception $e) {
            throw new StorageException("Failed to get file size: " . $e->getMessage());
        }
    }

    public function lastModified(string $path, string $disk = 'default'): int
    {
        try {
            return $this->disk($disk)->lastModified($path);
        } catch (\Exception $e) {
            throw new StorageException("Failed to get last modified time: " . $e->getMessage());
        }
    }

    public function url(string $path, string $disk = 'default'): string
    {
        $config = $this->config->get("storage.disks.{$disk}");
        
        if ($config['driver'] === 's3') {
            $client = new S3Client([
                'version' => 'latest',
                'region' => $config['region'],
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ],
            ]);

            return $client->getObjectUrl($config['bucket'], $path);
        }

        if ($config['driver'] === 'local') {
            return $config['url'] . '/' . ltrim($path, '/');
        }

        throw new StorageException("URL generation not supported for disk: {$disk}");
    }

    public function temporaryUrl(string $path, int $expiration, string $disk = 'default'): string
    {
        $config = $this->config->get("storage.disks.{$disk}");
        
        if ($config['driver'] === 's3') {
            $client = new S3Client([
                'version' => 'latest',
                'region' => $config['region'],
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ],
            ]);

            $command = $client->getCommand('GetObject', [
                'Bucket' => $config['bucket'],
                'Key' => $path,
            ]);

            return (string) $client->createPresignedRequest($command, "+{$expiration} seconds")->getUri();
        }

        throw new StorageException("Temporary URL generation not supported for disk: {$disk}");
    }

    private function createAdapter(array $config): mixed
    {
        return match ($config['driver']) {
            'local' => new LocalFilesystemAdapter($config['root']),
            's3' => new AwsS3V3Adapter(
                new S3Client([
                    'version' => 'latest',
                    'region' => $config['region'],
                    'credentials' => [
                        'key' => $config['key'],
                        'secret' => $config['secret'],
                    ],
                ]),
                $config['bucket'],
                $config['prefix'] ?? ''
            ),
            'ftp' => new FtpAdapter([
                'host' => $config['host'],
                'username' => $config['username'],
                'password' => $config['password'],
                'port' => $config['port'] ?? 21,
                'root' => $config['root'] ?? '/',
                'passive' => $config['passive'] ?? true,
                'ssl' => $config['ssl'] ?? false,
                'timeout' => $config['timeout'] ?? 30,
            ]),
            'sftp' => new SftpAdapter([
                'host' => $config['host'],
                'username' => $config['username'],
                'password' => $config['password'],
                'port' => $config['port'] ?? 22,
                'root' => $config['root'] ?? '/',
                'timeout' => $config['timeout'] ?? 10,
            ]),
            default => throw new StorageException("Unsupported storage driver: {$config['driver']}")
        };
    }
}

class StorageException extends \Exception
{
}