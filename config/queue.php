<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        
        'database' => [
            'driver' => 'database',
            'table' => 'queue_jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],
        
        'redis' => [
            'driver' => 'redis',
            'connection' => [
                'scheme' => 'tcp',
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD'),
                'database' => env('REDIS_QUEUE_DB', 0),
            ],
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],
        
        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],
    ],
    
    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],
    
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
    
    'workers' => [
        'default' => [
            'processes' => env('QUEUE_WORKERS', 3),
            'memory' => 128,
            'timeout' => 60,
            'sleep' => 3,
            'max_tries' => 3,
            'force' => false,
            'stop_when_empty' => false,
        ],
        
        'email' => [
            'processes' => env('EMAIL_QUEUE_WORKERS', 5),
            'memory' => 256,
            'timeout' => 300,
            'sleep' => 1,
            'max_tries' => 5,
        ],
        
        'reports' => [
            'processes' => 1,
            'memory' => 512,
            'timeout' => 1800,
            'sleep' => 10,
            'max_tries' => 1,
        ],
    ],
];