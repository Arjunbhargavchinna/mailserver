<?php

return [
    'channels' => [
        'mail' => [
            'driver' => 'mail',
            'queue' => env('NOTIFICATION_MAIL_QUEUE', true),
        ],
        
        'database' => [
            'driver' => 'database',
            'table' => 'notifications',
            'queue' => env('NOTIFICATION_DATABASE_QUEUE', false),
        ],
        
        'slack' => [
            'driver' => 'slack',
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
            'channel' => env('SLACK_CHANNEL', '#general'),
            'username' => env('SLACK_USERNAME', 'MailFlow'),
            'icon' => env('SLACK_ICON', ':mailbox:'),
            'queue' => env('NOTIFICATION_SLACK_QUEUE', true),
        ],
        
        'sms' => [
            'driver' => 'sms',
            'provider' => env('SMS_PROVIDER', 'twilio'),
            'from' => env('SMS_FROM'),
            'queue' => env('NOTIFICATION_SMS_QUEUE', true),
            'twilio' => [
                'sid' => env('TWILIO_SID'),
                'token' => env('TWILIO_TOKEN'),
            ],
        ],
        
        'push' => [
            'driver' => 'push',
            'provider' => env('PUSH_PROVIDER', 'fcm'),
            'queue' => env('NOTIFICATION_PUSH_QUEUE', true),
            'fcm' => [
                'server_key' => env('FCM_SERVER_KEY'),
            ],
        ],
        
        'webhook' => [
            'driver' => 'webhook',
            'default_url' => env('WEBHOOK_DEFAULT_URL'),
            'timeout' => env('WEBHOOK_TIMEOUT', 30),
            'retry_attempts' => env('WEBHOOK_RETRY_ATTEMPTS', 3),
            'queue' => env('NOTIFICATION_WEBHOOK_QUEUE', true),
        ],
    ],
    
    'templates' => [
        'path' => resource_path('views/notifications'),
        'cache' => env('NOTIFICATION_TEMPLATES_CACHE', true),
    ],
    
    'rate_limiting' => [
        'enabled' => env('NOTIFICATION_RATE_LIMITING', true),
        'per_minute' => env('NOTIFICATION_RATE_LIMIT_PER_MINUTE', 60),
        'per_hour' => env('NOTIFICATION_RATE_LIMIT_PER_HOUR', 1000),
    ],
    
    'retry' => [
        'max_attempts' => env('NOTIFICATION_MAX_RETRY_ATTEMPTS', 3),
        'delay' => env('NOTIFICATION_RETRY_DELAY', 60), // seconds
        'backoff' => env('NOTIFICATION_RETRY_BACKOFF', 'exponential'), // linear, exponential
    ],
];