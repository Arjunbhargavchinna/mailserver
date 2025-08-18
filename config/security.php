<?php

return [
    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'ttl' => env('JWT_TTL', 3600), // 1 hour
        'refresh_ttl' => env('JWT_REFRESH_TTL', 86400), // 24 hours
        'algo' => env('JWT_ALGO', 'HS256'),
        'required_claims' => ['iss', 'iat', 'exp', 'nbf', 'sub', 'jti'],
        'persistent_claims' => [],
        'lock_subject' => true,
        'leeway' => env('JWT_LEEWAY', 0),
        'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),
        'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),
        'decrypt_cookies' => false,
        'providers' => [
            'jwt' => 'MailFlow\\Core\\Security\\JWTProvider',
            'auth' => 'MailFlow\\Core\\Security\\AuthProvider',
            'storage' => 'MailFlow\\Core\\Security\\StorageProvider',
        ],
    ],
    
    'encryption' => [
        'key' => env('APP_KEY'),
        'cipher' => 'AES-256-GCM',
        'key_rotation' => [
            'enabled' => env('KEY_ROTATION_ENABLED', false),
            'interval' => env('KEY_ROTATION_INTERVAL', 2592000), // 30 days
        ],
    ],
    
    'two_factor' => [
        'enabled' => env('TWO_FACTOR_ENABLED', true),
        'issuer' => env('TWO_FACTOR_ISSUER', 'MailFlow Enterprise'),
        'digits' => 6,
        'period' => 30,
        'algorithm' => 'sha1',
        'recovery_codes' => 8,
        'backup_codes_enabled' => true,
    ],
    
    'rate_limiting' => [
        'login' => [
            'max_attempts' => env('LOGIN_MAX_ATTEMPTS', 5),
            'decay_minutes' => env('LOGIN_DECAY_MINUTES', 15),
            'lockout_duration' => env('LOGIN_LOCKOUT_DURATION', 900), // 15 minutes
        ],
        'api' => [
            'max_attempts' => env('API_RATE_LIMIT', 1000),
            'decay_minutes' => env('API_RATE_DECAY', 60),
        ],
        'password_reset' => [
            'max_attempts' => env('PASSWORD_RESET_MAX_ATTEMPTS', 3),
            'decay_minutes' => env('PASSWORD_RESET_DECAY', 60),
        ],
    ],
    
    'password' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 12),
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('PASSWORD_REQUIRE_SYMBOLS', true),
        'history_limit' => env('PASSWORD_HISTORY_LIMIT', 5),
        'expiry_days' => env('PASSWORD_EXPIRY_DAYS', 90),
        'algorithm' => PASSWORD_ARGON2ID,
        'options' => [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ],
    ],
    
    'session' => [
        'lifetime' => env('SESSION_LIFETIME', 120), // minutes
        'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
        'encrypt' => env('SESSION_ENCRYPT', true),
        'files' => storage_path('framework/sessions'),
        'connection' => env('SESSION_CONNECTION'),
        'table' => 'sessions',
        'store' => env('SESSION_STORE'),
        'lottery' => [2, 100],
        'cookie' => env('SESSION_COOKIE', 'mailflow_session'),
        'path' => '/',
        'domain' => env('SESSION_DOMAIN'),
        'secure' => env('SESSION_SECURE_COOKIE', true),
        'http_only' => true,
        'same_site' => 'lax',
    ],
    
    'csrf' => [
        'enabled' => env('CSRF_ENABLED', true),
        'token_lifetime' => env('CSRF_TOKEN_LIFETIME', 3600),
        'regenerate_on_login' => true,
    ],
    
    'headers' => [
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' https://cdn.tailwindcss.com",
            'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com",
            'font-src' => "'self' https://fonts.gstatic.com",
            'img-src' => "'self' data: https:",
            'connect-src' => "'self'",
            'frame-ancestors' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
        ],
        'strict_transport_security' => [
            'max_age' => 31536000,
            'include_subdomains' => true,
            'preload' => true,
        ],
    ],
    
    'audit' => [
        'enabled' => env('AUDIT_ENABLED', true),
        'events' => [
            'login',
            'logout',
            'password_change',
            'email_sent',
            'email_read',
            'user_created',
            'user_updated',
            'user_deleted',
            'role_changed',
            'settings_updated',
            'file_uploaded',
            'file_downloaded',
            'backup_created',
            'backup_restored',
        ],
        'retention_days' => env('AUDIT_RETENTION_DAYS', 2555), // 7 years
        'anonymize_after_days' => env('AUDIT_ANONYMIZE_DAYS', 365),
    ],
    
    'ip_whitelist' => [
        'enabled' => env('IP_WHITELIST_ENABLED', false),
        'admin_only' => env('IP_WHITELIST_ADMIN_ONLY', true),
        'addresses' => array_filter(explode(',', env('IP_WHITELIST', ''))),
    ],
    
    'geo_blocking' => [
        'enabled' => env('GEO_BLOCKING_ENABLED', false),
        'blocked_countries' => array_filter(explode(',', env('GEO_BLOCKED_COUNTRIES', ''))),
        'allowed_countries' => array_filter(explode(',', env('GEO_ALLOWED_COUNTRIES', ''))),
    ],
];