<?php
require_once 'config.php';

function getEmails($userId, $folder = 'inbox', $limit = EMAILS_PER_PAGE, $offset = 0) {
    global $pdo;
    
    $whereClause = "WHERE (recipient_id = ? OR sender_id = ?)";
    $params = [$userId, $userId];
    
    switch ($folder) {
        case 'sent':
            $whereClause = "WHERE sender_id = ?";
            $params = [$userId];
            break;
        case 'drafts':
            $whereClause = "WHERE sender_id = ? AND is_draft = 1";
            $params = [$userId];
            break;
        case 'spam':
            $whereClause = "WHERE recipient_id = ? AND is_spam = 1";
            $params = [$userId];
            break;
        case 'trash':
            $whereClause = "WHERE (recipient_id = ? OR sender_id = ?) AND is_deleted = 1";
            $params = [$userId, $userId];
            break;
        default:
            $whereClause .= " AND is_deleted = 0 AND is_draft = 0 AND is_spam = 0";
    }
    
    $sql = "SELECT e.*, 
                   COALESCE(s.name, s.email) as sender_name,
                   COALESCE(r.name, r.email) as recipient_name
            FROM emails e
            LEFT JOIN users s ON e.sender_id = s.id
            LEFT JOIN users r ON e.recipient_id = r.id
            $whereClause
            ORDER BY e.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    // Convert limit and offset to integers to avoid SQL syntax errors
    $executeParams = $params;
    $executeParams[count($executeParams) - 2] = (int)$limit;
    $executeParams[count($executeParams) - 1] = (int)$offset;
    
    $stmt->execute($executeParams);
    return $stmt->fetchAll();
}

function getUnreadCount($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM emails WHERE recipient_id = ? AND is_read = 0 AND is_deleted = 0 AND is_spam = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function getDraftCount($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM emails WHERE sender_id = ? AND is_draft = 1");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function getSystemStats() {
    global $pdo;
    
    // Get active users (logged in within last 24 hours)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $activeUsers = $stmt->fetchColumn();
    
    // Get total users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1");
    $stmt->execute();
    $totalUsers = $stmt->fetchColumn();
    
    // Get mail queue count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'");
    $stmt->execute();
    $mailQueue = $stmt->fetchColumn();
    
    // Calculate storage usage (simplified)
    $stmt = $pdo->prepare("SELECT SUM(size) FROM email_attachments");
    $stmt->execute();
    $usedStorage = $stmt->fetchColumn() ?: 0;
    $maxStorage = 100 * 1024 * 1024 * 1024; // 100GB
    $storageUsage = round(($usedStorage / $maxStorage) * 100, 1);
    
    return [
        'server_status' => 'Online',
        'active_users' => $activeUsers,
        'total_users' => $totalUsers,
        'mail_queue' => $mailQueue,
        'storage_usage' => $storageUsage
    ];
}

function getAuditLogs($limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT al.*, u.email as user_email, u.name as user_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $logs = $stmt->fetchAll();
    
    // Add icon and color based on action
    foreach ($logs as &$log) {
        switch ($log['action']) {
            case 'login':
                $log['icon'] = 'login';
                $log['color'] = 'blue';
                break;
            case 'user_created':
                $log['icon'] = 'person_add';
                $log['color'] = 'green';
                break;
            case 'security_alert':
                $log['icon'] = 'warning';
                $log['color'] = 'yellow';
                break;
            case 'config_update':
                $log['icon'] = 'settings';
                $log['color'] = 'purple';
                break;
            default:
                $log['icon'] = 'info';
                $log['color'] = 'gray';
        }
    }
    
    return $logs;
}

function hasUnreadNotifications($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() > 0;
}

function formatEmailDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 86400) { // Less than 24 hours
        return date('g:i A', $timestamp);
    } elseif ($diff < 604800) { // Less than 7 days
        return date('M j', $timestamp);
    } else {
        return date('M j, Y', $timestamp);
    }
}

function timeAgo($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

function sendEmail($senderId, $recipientId, $subject, $body, $attachments = []) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Insert email
        $stmt = $pdo->prepare("
            INSERT INTO emails (sender_id, recipient_id, subject, body, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$senderId, $recipientId, $subject, $body]);
        $emailId = $pdo->lastInsertId();
        
        // Handle attachments
        foreach ($attachments as $attachment) {
            $stmt = $pdo->prepare("
                INSERT INTO email_attachments (email_id, filename, filepath, size)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $emailId,
                $attachment['filename'],
                $attachment['filepath'],
                $attachment['size']
            ]);
        }
        
        // Add to email queue for processing
        $stmt = $pdo->prepare("
            INSERT INTO email_queue (email_id, status, created_at)
            VALUES (?, 'pending', NOW())
        ");
        $stmt->execute([$emailId]);
        
        $pdo->commit();
        return ['success' => true, 'email_id' => $emailId];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function markAsRead($emailId, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE emails 
        SET is_read = 1, read_at = NOW() 
        WHERE id = ? AND (recipient_id = ? OR sender_id = ?)
    ");
    return $stmt->execute([$emailId, $userId, $userId]);
}

function toggleStar($emailId, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE emails 
        SET is_starred = NOT is_starred 
        WHERE id = ? AND (recipient_id = ? OR sender_id = ?)
    ");
    return $stmt->execute([$emailId, $userId, $userId]);
}

function testSMTPConnection($host, $port, $username, $password) {
    try {
        $socket = fsockopen($host, $port, $errno, $errstr, 30);
        if (!$socket) {
            return ['success' => false, 'message' => "Cannot connect to $host:$port - $errstr"];
        }
        
        fclose($socket);
        return ['success' => true, 'message' => 'Connection successful'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function forwardEmail($originalEmailId, $senderId, $recipientId, $additionalMessage = '') {
    global $pdo;
    
    try {
        // Get original email
        $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = ?");
        $stmt->execute([$originalEmailId]);
        $originalEmail = $stmt->fetch();
        
        if (!$originalEmail) {
            return ['success' => false, 'message' => 'Original email not found'];
        }
        
        // Create forwarded email
        $subject = 'Fwd: ' . $originalEmail['subject'];
        $body = $additionalMessage . "\n\n--- Forwarded Message ---\n" . 
                "From: " . $originalEmail['sender_id'] . "\n" .
                "Subject: " . $originalEmail['subject'] . "\n" .
                "Date: " . $originalEmail['created_at'] . "\n\n" .
                $originalEmail['body'];
        
        $result = sendEmail($senderId, $recipientId, $subject, $body);
        
        if ($result['success']) {
            // Copy attachments
            $stmt = $pdo->prepare("SELECT * FROM email_attachments WHERE email_id = ?");
            $stmt->execute([$originalEmailId]);
            $attachments = $stmt->fetchAll();
            
            foreach ($attachments as $attachment) {
                $stmt = $pdo->prepare("
                    INSERT INTO email_attachments (email_id, filename, filepath, size, mime_type)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $result['email_id'],
                    $attachment['filename'],
                    $attachment['filepath'],
                    $attachment['size'],
                    $attachment['mime_type']
                ]);
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function replyToEmail($originalEmailId, $senderId, $subject, $body) {
    global $pdo;
    
    try {
        // Get original email
        $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = ?");
        $stmt->execute([$originalEmailId]);
        $originalEmail = $stmt->fetch();
        
        if (!$originalEmail) {
            return ['success' => false, 'message' => 'Original email not found'];
        }
        
        // Determine recipient (reply to sender)
        $recipientId = $originalEmail['sender_id'];
        
        // Add "Re:" prefix if not already present
        if (!str_starts_with($subject, 'Re:')) {
            $subject = 'Re: ' . $subject;
        }
        
        return sendEmail($senderId, $recipientId, $subject, $body);
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function searchEmails($userId, $query, $limit = EMAILS_PER_PAGE) {
    global $pdo;
    
    $searchTerm = "%$query%";
    
    $stmt = $pdo->prepare("
        SELECT e.*, 
               COALESCE(s.name, s.email) as sender_name,
               COALESCE(r.name, r.email) as recipient_name
        FROM emails e
        LEFT JOIN users s ON e.sender_id = s.id
        LEFT JOIN users r ON e.recipient_id = r.id
        WHERE (e.recipient_id = ? OR e.sender_id = ?)
        AND (e.subject LIKE ? OR e.body LIKE ? OR s.name LIKE ? OR s.email LIKE ?)
        AND e.is_deleted = 0
        ORDER BY e.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$userId, $userId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
    return $stmt->fetchAll();
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function getDepartments() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getUsersByDepartment($department) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE department = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$department]);
    return $stmt->fetchAll();
}
?>