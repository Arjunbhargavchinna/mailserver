<?php

declare(strict_types=1);

namespace MailFlow\Api\Controllers;

use MailFlow\Core\Database\DatabaseManager;
use MailFlow\Core\Search\SearchManager;
use MailFlow\Core\Queue\QueueManager;
use MailFlow\Core\Router\Response;

class EmailController
{
    private DatabaseManager $database;
    private SearchManager $search;
    private QueueManager $queue;

    public function __construct(
        DatabaseManager $database,
        SearchManager $search,
        QueueManager $queue
    ) {
        $this->database = $database;
        $this->search = $search;
        $this->queue = $queue;
    }

    public function index(): Response
    {
        $user = $_REQUEST['auth_user'];
        $folder = $_GET['folder'] ?? 'inbox';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $whereClause = $this->buildWhereClause($folder, $user['id']);
        
        // Get emails
        $stmt = $this->database->connection()->prepare("
            SELECT e.*, 
                   COALESCE(s.name, s.email) as sender_name,
                   COALESCE(r.name, r.email) as recipient_name,
                   s.email as sender_email,
                   r.email as recipient_email,
                   COUNT(ea.id) as attachment_count
            FROM emails e
            LEFT JOIN users s ON e.sender_id = s.id
            LEFT JOIN users r ON e.recipient_id = r.id
            LEFT JOIN email_attachments ea ON e.id = ea.email_id
            {$whereClause}
            GROUP BY e.id
            ORDER BY e.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params = $this->buildWhereParams($folder, $user['id']);
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt->execute($params);
        $emails = $stmt->fetchAll();

        // Get total count
        $countStmt = $this->database->connection()->prepare("
            SELECT COUNT(DISTINCT e.id) FROM emails e {$whereClause}
        ");
        $countStmt->execute($this->buildWhereParams($folder, $user['id']));
        $total = $countStmt->fetchColumn();

        return new Response([
            'data' => $emails,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => ceil($total / $limit),
            ],
        ]);
    }

    public function show(int $id): Response
    {
        $user = $_REQUEST['auth_user'];
        
        $stmt = $this->database->connection()->prepare("
            SELECT e.*, 
                   COALESCE(s.name, s.email) as sender_name,
                   COALESCE(r.name, r.email) as recipient_name,
                   s.email as sender_email,
                   r.email as recipient_email
            FROM emails e
            LEFT JOIN users s ON e.sender_id = s.id
            LEFT JOIN users r ON e.recipient_id = r.id
            WHERE e.id = ? AND (e.sender_id = ? OR e.recipient_id = ?)
        ");
        
        $stmt->execute([$id, $user['id'], $user['id']]);
        $email = $stmt->fetch();

        if (!$email) {
            return new Response(['error' => 'Email not found'], 404);
        }

        // Get attachments
        $stmt = $this->database->connection()->prepare("
            SELECT * FROM email_attachments WHERE email_id = ?
        ");
        $stmt->execute([$id]);
        $email['attachments'] = $stmt->fetchAll();

        // Mark as read if user is recipient
        if ($email['recipient_id'] == $user['id'] && !$email['is_read']) {
            $this->markAsRead($id);
            $email['is_read'] = 1;
        }

        return new Response($email);
    }

    public function store(): Response
    {
        $user = $_REQUEST['auth_user'];
        $input = json_decode(file_get_contents('php://input'), true);

        $recipientEmail = trim($input['recipient'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $body = $input['body'] ?? '';
        $isDraft = $input['is_draft'] ?? false;

        if (empty($recipientEmail) || empty($subject) || empty($body)) {
            return new Response(['error' => 'Recipient, subject, and body are required'], 400);
        }

        // Find recipient
        $stmt = $this->database->connection()->prepare("
            SELECT id FROM users WHERE email = ? AND is_active = 1
        ");
        $stmt->execute([$recipientEmail]);
        $recipient = $stmt->fetch();

        if (!$recipient) {
            return new Response(['error' => 'Recipient not found'], 404);
        }

        try {
            $this->database->beginTransaction();

            // Insert email
            $stmt = $this->database->connection()->prepare("
                INSERT INTO emails (sender_id, recipient_id, subject, body, is_draft, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user['id'], $recipient['id'], $subject, $body, $isDraft ? 1 : 0]);
            $emailId = $this->database->connection()->lastInsertId();

            // Handle attachments if any
            if (!empty($input['attachments'])) {
                foreach ($input['attachments'] as $attachment) {
                    $stmt = $this->database->connection()->prepare("
                        INSERT INTO email_attachments (email_id, filename, filepath, size, mime_type)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $emailId,
                        $attachment['filename'],
                        $attachment['filepath'],
                        $attachment['size'],
                        $attachment['mime_type'],
                    ]);
                }
            }

            if (!$isDraft) {
                // Queue email for sending
                $this->queue->push('SendEmailJob', [
                    'email_id' => $emailId,
                ], 'email');

                // Index for search
                $this->search->index('emails', (string) $emailId, [
                    'subject' => $subject,
                    'body' => $body,
                    'sender_email' => $user['email'],
                    'recipient_email' => $recipientEmail,
                    'created_at' => date('c'),
                ]);
            }

            $this->database->commit();

            return new Response([
                'id' => $emailId,
                'message' => $isDraft ? 'Draft saved' : 'Email sent',
            ], 201);

        } catch (\Exception $e) {
            $this->database->rollback();
            return new Response(['error' => 'Failed to send email'], 500);
        }
    }

    public function update(int $id): Response
    {
        $user = $_REQUEST['auth_user'];
        $input = json_decode(file_get_contents('php://input'), true);

        // Check if user owns the email
        $stmt = $this->database->connection()->prepare("
            SELECT * FROM emails WHERE id = ? AND sender_id = ?
        ");
        $stmt->execute([$id, $user['id']]);
        $email = $stmt->fetch();

        if (!$email) {
            return new Response(['error' => 'Email not found'], 404);
        }

        if (!$email['is_draft']) {
            return new Response(['error' => 'Cannot edit sent emails'], 400);
        }

        $subject = trim($input['subject'] ?? $email['subject']);
        $body = $input['body'] ?? $email['body'];

        $stmt = $this->database->connection()->prepare("
            UPDATE emails SET subject = ?, body = ?, updated_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$subject, $body, $id]);

        return new Response(['message' => 'Email updated']);
    }

    public function destroy(int $id): Response
    {
        $user = $_REQUEST['auth_user'];
        
        $stmt = $this->database->connection()->prepare("
            UPDATE emails SET is_deleted = 1 
            WHERE id = ? AND (sender_id = ? OR recipient_id = ?)
        ");
        $stmt->execute([$id, $user['id'], $user['id']]);

        if ($stmt->rowCount() === 0) {
            return new Response(['error' => 'Email not found'], 404);
        }

        return new Response(['message' => 'Email deleted']);
    }

    public function star(int $id): Response
    {
        return $this->toggleStar($id, true);
    }

    public function unstar(int $id): Response
    {
        return $this->toggleStar($id, false);
    }

    public function markAsRead(int $id): Response
    {
        $user = $_REQUEST['auth_user'];
        
        $stmt = $this->database->connection()->prepare("
            UPDATE emails SET is_read = 1, read_at = NOW() 
            WHERE id = ? AND recipient_id = ?
        ");
        $stmt->execute([$id, $user['id']]);

        if ($stmt->rowCount() === 0) {
            return new Response(['error' => 'Email not found'], 404);
        }

        return new Response(['message' => 'Email marked as read']);
    }

    public function markAsUnread(int $id): Response
    {
        $user = $_REQUEST['auth_user'];
        
        $stmt = $this->database->connection()->prepare("
            UPDATE emails SET is_read = 0, read_at = NULL 
            WHERE id = ? AND recipient_id = ?
        ");
        $stmt->execute([$id, $user['id']]);

        if ($stmt->rowCount() === 0) {
            return new Response(['error' => 'Email not found'], 404);
        }

        return new Response(['message' => 'Email marked as unread']);
    }

    private function toggleStar(int $id, bool $starred): Response
    {
        $user = $_REQUEST['auth_user'];
        
        $stmt = $this->database->connection()->prepare("
            UPDATE emails SET is_starred = ? 
            WHERE id = ? AND (sender_id = ? OR recipient_id = ?)
        ");
        $stmt->execute([$starred ? 1 : 0, $id, $user['id'], $user['id']]);

        if ($stmt->rowCount() === 0) {
            return new Response(['error' => 'Email not found'], 404);
        }

        return new Response(['message' => $starred ? 'Email starred' : 'Email unstarred']);
    }

    private function buildWhereClause(string $folder, int $userId): string
    {
        return match ($folder) {
            'sent' => "WHERE e.sender_id = ?",
            'drafts' => "WHERE e.sender_id = ? AND e.is_draft = 1",
            'spam' => "WHERE e.recipient_id = ? AND e.is_spam = 1",
            'trash' => "WHERE (e.recipient_id = ? OR e.sender_id = ?) AND e.is_deleted = 1",
            'starred' => "WHERE (e.recipient_id = ? OR e.sender_id = ?) AND e.is_starred = 1 AND e.is_deleted = 0",
            default => "WHERE (e.recipient_id = ? OR e.sender_id = ?) AND e.is_deleted = 0 AND e.is_draft = 0 AND e.is_spam = 0",
        };
    }

    private function buildWhereParams(string $folder, int $userId): array
    {
        return match ($folder) {
            'sent', 'drafts', 'spam' => [$userId],
            'trash', 'starred', 'inbox' => [$userId, $userId],
            default => [$userId, $userId],
        };
    }
}