<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MailFlow\Core\Application;

try {
    $app = new Application(__DIR__ . '/../config');
    
    // Register API routes
    $router = $app->getContainer()->get(\MailFlow\Core\Router\Router::class);
    
    // API v1 routes
    $router->group(['prefix' => 'api/v1', 'middleware' => ['auth', 'rate-limit']], function ($router) {
        // Authentication routes
        $router->post('auth/login', 'MailFlow\\Api\\Controllers\\AuthController@login');
        $router->post('auth/logout', 'MailFlow\\Api\\Controllers\\AuthController@logout');
        $router->post('auth/refresh', 'MailFlow\\Api\\Controllers\\AuthController@refresh');
        $router->get('auth/me', 'MailFlow\\Api\\Controllers\\AuthController@me');
        
        // Email routes
        $router->get('emails', 'MailFlow\\Api\\Controllers\\EmailController@index');
        $router->post('emails', 'MailFlow\\Api\\Controllers\\EmailController@store');
        $router->get('emails/{id}', 'MailFlow\\Api\\Controllers\\EmailController@show');
        $router->put('emails/{id}', 'MailFlow\\Api\\Controllers\\EmailController@update');
        $router->delete('emails/{id}', 'MailFlow\\Api\\Controllers\\EmailController@destroy');
        $router->post('emails/{id}/star', 'MailFlow\\Api\\Controllers\\EmailController@star');
        $router->post('emails/{id}/unstar', 'MailFlow\\Api\\Controllers\\EmailController@unstar');
        $router->post('emails/{id}/read', 'MailFlow\\Api\\Controllers\\EmailController@markAsRead');
        $router->post('emails/{id}/unread', 'MailFlow\\Api\\Controllers\\EmailController@markAsUnread');
        
        // Search routes
        $router->get('search', 'MailFlow\\Api\\Controllers\\SearchController@search');
        $router->get('search/suggestions', 'MailFlow\\Api\\Controllers\\SearchController@suggestions');
        
        // User routes
        $router->get('users', 'MailFlow\\Api\\Controllers\\UserController@index');
        $router->post('users', 'MailFlow\\Api\\Controllers\\UserController@store');
        $router->get('users/{id}', 'MailFlow\\Api\\Controllers\\UserController@show');
        $router->put('users/{id}', 'MailFlow\\Api\\Controllers\\UserController@update');
        $router->delete('users/{id}', 'MailFlow\\Api\\Controllers\\UserController@destroy');
        
        // Admin routes
        $router->group(['prefix' => 'admin', 'middleware' => ['admin']], function ($router) {
            $router->get('dashboard', 'MailFlow\\Api\\Controllers\\AdminController@dashboard');
            $router->get('audit-logs', 'MailFlow\\Api\\Controllers\\AdminController@auditLogs');
            $router->get('system-stats', 'MailFlow\\Api\\Controllers\\AdminController@systemStats');
            $router->post('backup', 'MailFlow\\Api\\Controllers\\AdminController@createBackup');
            $router->get('backups', 'MailFlow\\Api\\Controllers\\AdminController@listBackups');
        });
        
        // Notification routes
        $router->get('notifications', 'MailFlow\\Api\\Controllers\\NotificationController@index');
        $router->post('notifications/{id}/read', 'MailFlow\\Api\\Controllers\\NotificationController@markAsRead');
        $router->delete('notifications/{id}', 'MailFlow\\Api\\Controllers\\NotificationController@destroy');
    });
    
    // Health check route (no auth required)
    $router->get('health', function () {
        return ['status' => 'ok', 'timestamp' => time()];
    });
    
    $app->run();
    
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
}