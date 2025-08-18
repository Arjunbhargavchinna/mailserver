<?php

return [
    'name' => env('APP_NAME', 'MailFlow Enterprise'),
    'version' => '2.0.0',
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'en'),
    'debug' => env('APP_DEBUG', false),
    'env' => env('APP_ENV', 'production'),
    
    'encryption' => [
        'key' => env('APP_KEY'),
        'cipher' => 'AES-256-CBC',
    ],
    
    'maintenance' => [
        'enabled' => env('MAINTENANCE_MODE', false),
        'secret' => env('MAINTENANCE_SECRET'),
        'template' => 'maintenance',
    ],
    
    'features' => [
        'two_factor_auth' => env('FEATURE_2FA', true),
        'email_encryption' => env('FEATURE_EMAIL_ENCRYPTION', true),
        'advanced_search' => env('FEATURE_ADVANCED_SEARCH', true),
        'email_templates' => env('FEATURE_EMAIL_TEMPLATES', true),
        'workflow_automation' => env('FEATURE_WORKFLOW_AUTOMATION', true),
        'analytics' => env('FEATURE_ANALYTICS', true),
        'api_access' => env('FEATURE_API_ACCESS', true),
    ],
];

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    return match (strtolower($value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        'empty', '(empty)' => '',
        default => $value
    };
}