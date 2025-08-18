<?php

declare(strict_types=1);

namespace MailFlow\Api\Controllers;

use MailFlow\Core\Database\DatabaseManager;
use MailFlow\Core\Cache\CacheManager;
use MailFlow\Core\Queue\QueueManager;
use MailFlow\Core\Router\Response;

class AdminController
{
    private DatabaseManager $database;
    private CacheManager $cache;
    private QueueManager $queue;

    public function __construct(
        DatabaseManager $database,
        CacheManager $cache,
        QueueManager $queue
    ) {
        $this->database = $database;
        $this->cache = $cache;
        $this->queue = $queue;
    }

    public function dashboard(): Response
    {
        $user = $_REQUEST['auth_user'];
        
        if ($user['role'] !== 'Administrator') {
            return new Response(['error' => 'Forbidden'], 403);
        }

        $stats = $this->cache->remember('admin_dashboard_stats', function () {
            return $this->getSystemStats();
        }, 300); // Cache for 5 minutes

        return new Response($stats);
    }

    public function auditLogs(): Response
    {
        $user = $_REQUEST['auth_user'];
        
        if ($user['role'] !== 'Administrator') {
            return new Response(['error' => 'Forbidden'], 403);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int) ($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        $action = $_GET['action'] ?? '';
        $userId = $_GET['user_id'] ?? '';

        $whereClause = '';
        $params = [];

        if (!empty($action)) {
            $whereClause .= ($whereClause ? ' AND ' : 'WHERE ') . 'action = ?';
            $params[] = $action;
        }

        if (!empty($userId)) {
            $whereClause .= ($whereClause ? ' AND ' : 'WHERE ') . 'user_id = ?';
            $params[] = $userId;
        }

        // Get audit logs
        $stmt = $this->database->connection()->prepare("
            SELECT al.*, u.email as user_email, u.name as user_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$whereClause}
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Get total count
        $countParams = array_slice($params, 0, -2);
        $countStmt = $this->database->connection()->prepare("
            SELECT COUNT(*) FROM audit_logs al {$whereClause}
        ");
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();

        return new Response([
            'data' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => ceil($total / $limit),
            ],
        ]);
    }

    public function systemStats(): Response
    {
        $user = $_REQUEST['auth_user'];
        
        if ($user['role'] !== 'Administrator') {
            return new Response(['error' => 'Forbidden'], 403);
        }

        $stats = $this->getSystemStats();
        return new Response($stats);
    }

    public function createBackup(): Response
    {
        $user = $_REQUEST['auth_user'];
        
        if ($user['role'] !== 'Administrator') {
            return new Response(['error' => 'Forbidden'], 403);
        }

        try {
            // Queue backup job
            $jobId = $this->queue->push('CreateBackupJob', [
                'user_id' => $user['id'],
                'type' => 'manual',
            ], 'backup');

            // Log activity
            $this->logActivity($user['id'], 'backup_initiated', 'Manual backup initiated');

            return new Response([
                'job_id' => $jobId,
                'message' => 'Backup job queued successfully',
            ]);

        } catch (\Exception $e) {
            return new Response(['error' => 'Failed to queue backup job'], 500);
        }
    }

    public function listBackups(): Response
    {
        $user = $_REQUEST['auth_user'];
        
        if ($user['role'] !== 'Administrator') {
            return new Response(['error' => 'Forbidden'], 403);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Get backups from database (assuming you have a backups table)
        $stmt = $this->database->connection()->prepare("
            SELECT * FROM backups 
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $backups = $stmt->fetchAll();

        // Get total count
        $stmt = $this->database->connection()->prepare("SELECT COUNT(*) FROM backups");
        $stmt->execute();
        $total = $stmt->fetchColumn();

        return new Response([
            'data' => $backups,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => ceil($total / $limit),
            ],
        ]);
    }

    private function getSystemStats(): array
    {
        // Get user stats
        $stmt = $this->database->connection()->prepare("
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as users_today
            FROM users
        ");
        $stmt->execute();
        $userStats = $stmt->fetch();

        // Get email stats
        $stmt = $this->database->connection()->prepare("
            SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as emails_today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as emails_week
            FROM emails WHERE is_deleted = 0
        ");
        $stmt->execute();
        $emailStats = $stmt->fetch();

        // Get storage stats
        $stmt = $this->database->connection()->prepare("
            SELECT COALESCE(SUM(size), 0) as total_storage FROM email_attachments
        ");
        $stmt->execute();
        $storageUsed = $stmt->fetchColumn();

        // Get queue stats
        $queueStats = [
            'pending' => $this->queue->size('default'),
            'email_queue' => $this->queue->size('email'),
        ];

        // Get system health
        $systemHealth = [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'storage' => $this->checkStorageHealth(),
        ];

        return [
            'users' => $userStats,
            'emails' => $emailStats,
            'storage' => [
                'used_bytes' => $storageUsed,
                'used_formatted' => $this->formatBytes($storageUsed),
            ],
            'queue' => $queueStats,
            'system_health' => $systemHealth,
            'uptime' => $this->getSystemUptime(),
            'memory_usage' => [
                'used' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'formatted' => [
                    'used' => $this->formatBytes(memory_get_usage(true)),
                    'peak' => $this->formatBytes(memory_get_peak_usage(true)),
                ],
            ],
        ];
    }

    private function checkDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            $this->database->connection()->query('SELECT 1');
            $responseTime = (microtime(true) - $start) * 1000;
            
            return [
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCacheHealth(): array
    {
        try {
            $start = microtime(true);
            $this->cache->put('health_check', 'ok', 10);
            $result = $this->cache->get('health_check');
            $responseTime = (microtime(true) - $start) * 1000;
            
            return [
                'status' => $result === 'ok' ? 'healthy' : 'unhealthy',
                'response_time_ms' => round($responseTime, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkStorageHealth(): array
    {
        try {
            $uploadPath = __DIR__ . '/../../../uploads';
            $freeSpace = disk_free_space($uploadPath);
            $totalSpace = disk_total_space($uploadPath);
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercent = ($usedSpace / $totalSpace) * 100;
            
            return [
                'status' => $usagePercent < 90 ? 'healthy' : 'warning',
                'free_space' => $this->formatBytes($freeSpace),
                'total_space' => $this->formatBytes($totalSpace),
                'usage_percent' => round($usagePercent, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getSystemUptime(): array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
        } else {
            $load = [0, 0, 0];
        }

        return [
            'load_average' => [
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2],
            ],
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function logActivity(int $userId, string $action, string $details): void
    {
        $stmt = $this->database->connection()->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
    }
}