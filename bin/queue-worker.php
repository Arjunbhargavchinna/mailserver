#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MailFlow\Core\Application;
use MailFlow\Core\Queue\QueueManager;
use MailFlow\Core\Logger\LoggerManager;

try {
    $app = new Application(__DIR__ . '/../config');
    $app->boot();
    
    $container = $app->getContainer();
    $queue = $container->get(QueueManager::class);
    $logger = $container->get(LoggerManager::class)->getLogger('queue');
    
    $logger->info('Queue worker started');
    
    // Process jobs continuously
    while (true) {
        try {
            $job = $queue->pop();
            
            if ($job) {
                $logger->info('Processing job', ['id' => $job->id, 'job' => $job->job]);
                
                $startTime = microtime(true);
                
                // Execute job
                if (class_exists($job->job)) {
                    $instance = $container->get($job->job);
                    if (method_exists($instance, 'handle')) {
                        $instance->handle($job->data);
                    }
                }
                
                $duration = microtime(true) - $startTime;
                $logger->info('Job completed', [
                    'id' => $job->id,
                    'duration' => round($duration * 1000, 2) . 'ms'
                ]);
                
            } else {
                // No jobs available, sleep for a bit
                sleep(3);
            }
            
        } catch (\Exception $e) {
            $logger->error('Job failed', [
                'job_id' => $job->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Sleep before retrying
            sleep(5);
        }
    }
    
} catch (\Exception $e) {
    echo "Queue worker error: " . $e->getMessage() . "\n";
    exit(1);
}