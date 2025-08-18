<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),
    
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],
        
        'ses' => [
            'transport' => 'ses',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        ],
        
        'mailgun' => [
            'transport' => 'mailgun',
            'domain' => env('MAILGUN_DOMAIN'),
            'secret' => env('MAILGUN_SECRET'),
            'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
            'scheme' => 'https',
        ],
        
        'postmark' => [
            'transport' => 'postmark',
            'token' => env('POSTMARK_TOKEN'),
        ],
        
        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],
        
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
        
        'array' => [
            'transport' => 'array',
        ],
        
        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],
    ],
    
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],
    
    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],
    
    'encryption' => [
        'enabled' => env('MAIL_ENCRYPTION_ENABLED', false),
        'key' => env('MAIL_ENCRYPTION_KEY'),
        'algorithm' => 'AES-256-GCM',
    ],
    
    'tracking' => [
        'enabled' => env('MAIL_TRACKING_ENABLED', true),
        'pixel' => true,
        'links' => true,
    ],
    
    'templates' => [
        'enabled' => env('MAIL_TEMPLATES_ENABLED', true),
        'path' => resource_path('views/mail/templates'),
        'cache' => env('MAIL_TEMPLATES_CACHE', true),
    ],
    
    'rate_limiting' => [
        'enabled' => env('MAIL_RATE_LIMITING_ENABLED', true),
        'per_minute' => env('MAIL_RATE_LIMIT_PER_MINUTE', 60),
        'per_hour' => env('MAIL_RATE_LIMIT_PER_HOUR', 1000),
        'per_day' => env('MAIL_RATE_LIMIT_PER_DAY', 10000),
    ],
];

function resource_path(string $path = ''): string
{
    return __DIR__ . '/../resources/' . $path;
}