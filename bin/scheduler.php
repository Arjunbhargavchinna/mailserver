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
    $logger = $container->get(LoggerManager::class)->getLogger('scheduler');
    
    $currentMinute = date('i');
    $currentHour = date('H');
    $currentDay = date('N'); // 1 = Monday, 7 = Sunday
    
    // Every minute tasks
    scheduleTask($queue, 'ProcessEmailQueueJob', [], 'email');
    scheduleTask($queue, 'CleanupTempFilesJob', []);
    
    // Every 5 minutes
    if ($currentMinute % 5 === 0) {
        scheduleTask($queue, 'UpdateSystemStatsJob', []);
        scheduleTask($queue, 'ProcessNotificationsJob', []);
    }
    
    // Every 15 minutes
    if ($currentMinute % 15 === 0) {
        scheduleTask($queue, 'CleanupExpiredSessionsJob', []);
    }
    
    // Every hour
    if ($currentMinute === '0') {
        scheduleTask($queue, 'UpdateSearchIndexJob', []);
        scheduleTask($queue, 'CleanupOldLogsJob', []);
        scheduleTask($queue, 'ProcessEmailDigestJob', []);
    }
    
    // Daily at 2 AM
    if ($currentHour === '02' && $currentMinute === '0') {
        scheduleTask($queue, 'DatabaseBackupJob', [], 'backup');
        scheduleTask($queue, 'CleanupOldAuditLogsJob', []);
        scheduleTask($queue, 'OptimizeDatabaseJob', []);
    }
    
    // Daily at 6 AM
    if ($currentHour === '06' && $currentMinute === '0') {
        scheduleTask($queue, 'GenerateDailyReportsJob', [], 'reports');
        scheduleTask($queue, 'SendDailyDigestJob', [], 'email');
    }
    
    // Weekly on Monday at 3 AM
    if ($currentDay === 1 && $currentHour === '03' && $currentMinute === '0') {
        scheduleTask($queue, 'GenerateWeeklyReportsJob', [], 'reports');
        scheduleTask($queue, 'CleanupOldBackupsJob', []);
        scheduleTask($queue, 'UpdateSecurityScansJob', []);
    }
    
    // Monthly on 1st at 4 AM
    if (date('j') === '1' && $currentHour === '04' && $currentMinute === '0') {
        scheduleTask($queue, 'GenerateMonthlyReportsJob', [], 'reports');
        scheduleTask($queue, 'ArchiveOldEmailsJob', []);
        scheduleTask($queue, 'UpdateLicenseCheckJob', []);
    }
    
    $logger->info('Scheduler completed', ['time' => date('Y-m-d H:i:s')]);
    
} catch (\Exception $e) {
    echo "Scheduler error: " . $e->getMessage() . "\n";
    exit(1);
}

function scheduleTask(QueueManager $queue, string $job, array $data = [], string $queueName = 'default'): void
{
    try {
        $queue->push($job, $data, $queueName);
    } catch (\Exception $e) {
        error_log("Failed to schedule task {$job}: " . $e->getMessage());
    }
}