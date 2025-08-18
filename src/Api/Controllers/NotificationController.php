<?php

declare(strict_types=1);

namespace MailFlow\Api\Controllers;

use MailFlow\Core\Database\DatabaseManager;
use MailFlow\Core\Router\Response;

class NotificationController
{
    private DatabaseManager $database;

    public function __construct(DatabaseManager $database)
    {
        $this->database = $database;
    }

    public function index(): Response
    {
        $user = $_REQUEST['auth_user'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $unreadOnly = $_GET['unread_only'] ?? false;

        $whereClause = 'WHERE user_id = ?';
        $params = [$user['id']];

        if ($unreadOnly) {
            $whereClause .= ' AND is_read = 0';
        }

        // Get notifications
        $stmt = $this->database->connection()->prepare("
            SELECT * FROM notifications 
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $notifications = $stmt->fetchAll();

        // Get total count
        $countParams = array_slice($params, 0, -2);
        $countStmt = $this->database->connection()->prepare("
            SELECT COUNT(*) FROM notifications {$whereClause}
        ");
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();

        // Get unread count
        $unreadStmt = $this->database->connection()->prepare("
            SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0
        ");
        $unreadStmt->execute([$user['id']]);
        $unreadCount = $unreadStmt->fetchColumn();

        return new Response([
            'data' => $notifications,
            'unread_count' => $unreadCount,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => ceil($total / $limit),
            ],
        ]);
    }

    public function markAsRead(int $id): Response
    {
        $user = $_REQUEST['auth_user'];
        
        $stmt = $this->database->connection()->prepare("
            UPDATE notifications SET is_read = 1, read_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$id, $user['id']]);

        if ($stmt->rowCount() === 0) {
            return new Response(['error' => 'Notification not found'], 404);
        }

        return new Response(['message' => 'Notification marked as read']);
    }

    public function destroy(int $id): Response
    {
        $user = $_REQUEST['auth_user'];
        
        $stmt = $this->database->connection()->prepare("
            DELETE FROM notifications WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$id, $user['id']]);

        if ($stmt->rowCount() === 0) {
            return new Response(['error' => 'Notification not found'], 404);
        }

        return new Response(['message' => 'Notification deleted']);
    }
}