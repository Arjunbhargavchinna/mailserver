<?php

return [
    'default' => env('SEARCH_DRIVER', 'elasticsearch'),
    
    'drivers' => [
        'elasticsearch' => [
            'driver' => 'elasticsearch',
            'hosts' => [
                env('ELASTICSEARCH_HOST', 'localhost:9200'),
            ],
            'username' => env('ELASTICSEARCH_USERNAME'),
            'password' => env('ELASTICSEARCH_PASSWORD'),
            'cloud_id' => env('ELASTICSEARCH_CLOUD_ID'),
            'api_key' => env('ELASTICSEARCH_API_KEY'),
            'ssl_verification' => env('ELASTICSEARCH_SSL_VERIFICATION', true),
            'retries' => 2,
            'indices' => [
                'emails' => [
                    'name' => env('ELASTICSEARCH_EMAIL_INDEX', 'mailflow_emails'),
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0,
                        'analysis' => [
                            'analyzer' => [
                                'email_analyzer' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'standard',
                                    'filter' => [
                                        'lowercase',
                                        'stop',
                                        'snowball',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'mappings' => [
                        'properties' => [
                            'subject' => [
                                'type' => 'text',
                                'analyzer' => 'email_analyzer',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 256,
                                    ],
                                ],
                            ],
                            'body' => [
                                'type' => 'text',
                                'analyzer' => 'email_analyzer',
                            ],
                            'sender_email' => [
                                'type' => 'keyword',
                            ],
                            'recipient_email' => [
                                'type' => 'keyword',
                            ],
                            'created_at' => [
                                'type' => 'date',
                            ],
                            'is_read' => [
                                'type' => 'boolean',
                            ],
                            'is_starred' => [
                                'type' => 'boolean',
                            ],
                            'labels' => [
                                'type' => 'keyword',
                            ],
                            'attachments' => [
                                'type' => 'nested',
                                'properties' => [
                                    'filename' => [
                                        'type' => 'text',
                                        'analyzer' => 'email_analyzer',
                                    ],
                                    'content_type' => [
                                        'type' => 'keyword',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'users' => [
                    'name' => env('ELASTICSEARCH_USER_INDEX', 'mailflow_users'),
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0,
                    ],
                    'mappings' => [
                        'properties' => [
                            'name' => [
                                'type' => 'text',
                                'analyzer' => 'standard',
                            ],
                            'email' => [
                                'type' => 'keyword',
                            ],
                            'role' => [
                                'type' => 'keyword',
                            ],
                            'department' => [
                                'type' => 'keyword',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'mysql'),
            'full_text' => env('DB_FULL_TEXT_SEARCH', true),
        ],
        
        'algolia' => [
            'driver' => 'algolia',
            'app_id' => env('ALGOLIA_APP_ID'),
            'secret' => env('ALGOLIA_SECRET'),
            'indices' => [
                'emails' => env('ALGOLIA_EMAIL_INDEX', 'emails'),
                'users' => env('ALGOLIA_USER_INDEX', 'users'),
            ],
        ],
    ],
    
    'indexing' => [
        'batch_size' => env('SEARCH_BATCH_SIZE', 100),
        'queue' => env('SEARCH_QUEUE', 'search'),
        'auto_index' => env('SEARCH_AUTO_INDEX', true),
        'real_time' => env('SEARCH_REAL_TIME', true),
    ],
    
    'features' => [
        'autocomplete' => env('SEARCH_AUTOCOMPLETE', true),
        'suggestions' => env('SEARCH_SUGGESTIONS', true),
        'faceted_search' => env('SEARCH_FACETED', true),
        'highlighting' => env('SEARCH_HIGHLIGHTING', true),
        'spell_check' => env('SEARCH_SPELL_CHECK', true),
    ],
];