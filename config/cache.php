<?php

return [
    'default' => env('CACHE_DRIVER', 'redis'),
    
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => [
                'scheme' => 'tcp',
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD'),
                'database' => env('REDIS_CACHE_DB', 1),
            ],
            'prefix' => env('CACHE_PREFIX', 'mailflow:cache:'),
        ],
        
        'file' => [
            'driver' => 'file',
            'path' => storage_path('cache'),
        ],
        
        'memory' => [
            'driver' => 'memory',
        ],
    ],
    
    'prefix' => env('CACHE_PREFIX', 'mailflow'),
    
    'ttl' => [
        'default' => 3600, // 1 hour
        'user_sessions' => 86400, // 24 hours
        'email_content' => 7200, // 2 hours
        'search_results' => 1800, // 30 minutes
        'system_stats' => 300, // 5 minutes
    ],
];

function storage_path(string $path = ''): string
{
    return __DIR__ . '/../storage/' . $path;
}