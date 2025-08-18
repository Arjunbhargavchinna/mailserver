<?php

declare(strict_types=1);

namespace MailFlow\Api\Controllers;

use MailFlow\Core\Database\DatabaseManager;
use MailFlow\Core\Security\SecurityManager;
use MailFlow\Core\Router\Response;

class UserController
{
    private DatabaseManager $database;
    private SecurityManager $security;

    public function __construct(DatabaseManager $database, SecurityManager $security)
    {
        $this->database = $database;
        $this->security = $security;
    }

    public function index(): Response
    {
        $user = $_REQUEST['auth_user'];
        
        // Only admins can list all users
        if ($user['role'] !== 'Administrator') {
            return new Response(['error' => 'Forbidden'], 403);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';

        $whereClause = '';
        $params = [];

        if (!empty($search)) {
            $whereClause = "WHERE name LIKE ? OR email LIKE ?";
            $params = ["%{$search}%", "%{$search}%"];
        }

        // Get users
        $stmt = $this->database->connection()->prepare("
            SELECT id, email, name, role, is_active, created_at, last_login
            FROM users 
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        // Get total count
        $countParams = array_slice($params, 0, -2);
        $countStmt = $this->database->connection()->prepare("
            SELECT COUNT(*) FROM users {$whereClause}
        ");
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();

        return new Response([
            'data' => $users,
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
        
        // Users can only view their own profile, admins can view any
        if ($user['role'] !== 'Administrator' && $user['id'] !== $id) {
            return new Response(['error' => 'Forbidden'], 403);
        }

        $stmt = $this->database->connection()->prepare("
            SELECT id, email, name, role, is_active, created_at, last_login
            FROM users WHERE id = ?
        ");
        $stmt->execute([$id]);
        $userData = $stmt->fetch();

        if (!$userData) {
            return new Response(['error' => 'User not found'], 404);
        }

        return new Response($userData);
    }

    public function store(): Response
    {
        $user = $_REQUEST['auth_user'];
        
        // Only admins can create users
        if ($user['role'] !== 'Administrator') {
            return new Response(['error' => 'Forbidden'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $email = trim($input['email'] ?? '');
        $name = trim($input['name'] ?? '');
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'User';

        // Validation
        if (empty($email) || empty($name) || empty($password)) {
            return new Response(['error' => 'Email, name, and password are required'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new Response(['error' => 'Invalid email format'], 400);
        }

        if (strlen($password) < 8) {
            return new Response(['error' => 'Password must be at least 8 characters'], 400);
        }

        if (!in_array($role, ['User', 'Manager', 'Administrator'])) {
            return new Response(['error' => 'Invalid role'], 400);
        }

        // Check if email already exists
        $stmt = $this->database->connection()->prepare("
            SELECT id FROM users WHERE email = ?
        ");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return new Response(['error' => 'Email already exists'], 409);
        }

        try {
            $hashedPassword = $this->security->hashPassword($password);
            
            $stmt = $this->database->connection()->prepare("
                INSERT INTO users (email, name, password, role, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$email, $name, $hashedPassword, $role]);
            $userId = $this->database->connection()->lastInsertId();

            // Log activity
            $this->logActivity($user['id'], 'user_created', "Created user: {$email}");

            return new Response([
                'id' => $userId,
                'message' => 'User created successfully',
            ], 201);

        } catch (\Exception $e) {
            return new Response(['error' => 'Failed to create user'], 500);
        }
    }

    public function update(int $id): Response
    {
        $user = $_REQUEST['auth_user'];
        
        // Users can only update their own profile, admins can update any
        if ($user['role'] !== 'Administrator' && $user['id'] !== $id) {
            return new Response(['error' => 'Forbidden'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        // Get current user data
        $stmt = $this->database->connection()->prepare("
            SELECT * FROM users WHERE id = ?
        ");
        $stmt->execute([$id]);
        $userData = $stmt->fetch();

        if (!$userData) {
            return new Response(['error' => 'User not found'], 404);
        }

        $updates = [];
        $params = [];

        // Update name
        if (isset($input['name']) && !empty(trim($input['name']))) {
            $updates[] = 'name = ?';
            $params[] = trim($input['name']);
        }

        // Update password
        if (isset($input['password']) && !empty($input['password'])) {
            if (strlen($input['password']) < 8) {
                return new Response(['error' => 'Password must be at least 8 characters'], 400);
            }
            
            $updates[] = 'password = ?';
            $params[] = $this->security->hashPassword($input['password']);
        }

        // Update role (admin only)
        if (isset($input['role']) && $user['role'] === 'Administrator') {
            if (!in_array($input['role'], ['User', 'Manager', 'Administrator'])) {
                return new Response(['error' => 'Invalid role'], 400);
            }
            
            $updates[] = 'role = ?';
            $params[] = $input['role'];
        }

        // Update active status (admin only)
        if (isset($input['is_active']) && $user['role'] === 'Administrator') {
            $updates[] = 'is_active = ?';
            $params[] = $input['is_active'] ? 1 : 0;
        }

        if (empty($updates)) {
            return new Response(['error' => 'No valid fields to update'], 400);
        }

        try {
            $updates[] = 'updated_at = NOW()';
            $params[] = $id;
            
            $stmt = $this->database->connection()->prepare("
                UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?
            ");
            $stmt->execute($params);

            // Log activity
            $this->logActivity($user['id'], 'user_updated', "Updated user: {$userData['email']}");

            return new Response(['message' => 'User updated successfully']);

        } catch (\Exception $e) {
            return new Response(['error' => 'Failed to update user'], 500);
        }
    }

    public function destroy(int $id): Response
    {
        $user = $_REQUEST['auth_user'];
        
        // Only admins can delete users
        if ($user['role'] !== 'Administrator') {
            return new Response(['error' => 'Forbidden'], 403);
        }

        // Cannot delete self
        if ($user['id'] === $id) {
            return new Response(['error' => 'Cannot delete your own account'], 400);
        }

        // Get user data
        $stmt = $this->database->connection()->prepare("
            SELECT email FROM users WHERE id = ?
        ");
        $stmt->execute([$id]);
        $userData = $stmt->fetch();

        if (!$userData) {
            return new Response(['error' => 'User not found'], 404);
        }

        try {
            // Soft delete - deactivate instead of hard delete
            $stmt = $this->database->connection()->prepare("
                UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$id]);

            // Log activity
            $this->logActivity($user['id'], 'user_deactivated', "Deactivated user: {$userData['email']}");

            return new Response(['message' => 'User deactivated successfully']);

        } catch (\Exception $e) {
            return new Response(['error' => 'Failed to deactivate user'], 500);
        }
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