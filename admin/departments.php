<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$user = getCurrentUser();
$success = '';
$error = '';

// Handle department operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_department') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            $error = 'Department name is required';
        } else {
            $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
            if ($stmt->execute([$name, $description])) {
                $success = 'Department created successfully';
                logActivity($user['id'], 'department_created', "Created department: $name");
            } else {
                $error = 'Failed to create department';
            }
        }
    } elseif ($action === 'assign_users') {
        $departmentId = $_POST['department_id'];
        $userIds = $_POST['user_ids'] ?? [];
        
        if (!empty($userIds)) {
            $stmt = $pdo->prepare("UPDATE users SET department_id = ? WHERE id = ?");
            $count = 0;
            foreach ($userIds as $userId) {
                if ($stmt->execute([$departmentId, $userId])) {
                    $count++;
                }
            }
            $success = "Assigned $count users to department";
        }
    }
}

// Get all departments
$stmt = $pdo->prepare("SELECT d.*, COUNT(u.id) as user_count FROM departments d LEFT JOIN users u ON d.id = u.department_id GROUP BY d.id ORDER BY d.name");
$stmt->execute();
$departments = $stmt->fetchAll();

// Get users without department
$stmt = $pdo->prepare("SELECT * FROM users WHERE department_id IS NULL AND is_active = 1 ORDER BY name");
$stmt->execute();
$unassignedUsers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management - MailFlow Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url(https://fonts.googleapis.com/css2?family=Lato&display=swap);
        @import url(https://fonts.googleapis.com/css2?family=Open+Sans&display=swap);
        @import url(https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined);
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200 shadow-sm">
            <div class="flex items-center justify-between h-16 px-6">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center text-primary-600 hover:text-primary-700">
                        <span class="material-symbols-outlined mr-2">arrow_back</span>
                        <span class="font-semibold">Back to Admin</span>
                    </a>
                </div>
                <h1 class="text-xl font-semibold text-gray-800">Department Management</h1>
                <div class="w-32"></div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="max-w-6xl mx-auto py-8 px-6">
            <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Create Department -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">Create New Department</h2>
                        </div>
                        
                        <form method="POST" class="p-6">
                            <input type="hidden" name="action" value="create_department">
                            
                            <div class="mb-4">
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Department Name</label>
                                <input type="text" id="name" name="name" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
                            </div>
                            
                            <div class="mb-6">
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea id="description" name="description" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"></textarea>
                            </div>
                            
                            <button type="submit" 
                                    class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-md font-medium transition duration-200">
                                Create Department
                            </button>
                        </form>
                    </div>

                    <!-- Departments List -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">All Departments</h2>
                        </div>
                        
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($departments as $dept): ?>
                            <div class="px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($dept['name']); ?></h3>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($dept['description']); ?></p>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo $dept['user_count']; ?> users</p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button onclick="manageDepartment(<?php echo $dept['id']; ?>)" 
                                                class="text-primary-600 hover:text-primary-700 text-sm">
                                            Manage
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Unassigned Users -->
                <div>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Unassigned Users</h3>
                        </div>
                        
                        <div class="p-6">
                            <?php if (empty($unassignedUsers)): ?>
                            <p class="text-sm text-gray-500">All users are assigned to departments</p>
                            <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_users">
                                
                                <div class="mb-4">
                                    <label for="department_id" class="block text-sm font-medium text-gray-700 mb-2">Assign to Department</label>
                                    <select name="department_id" id="department_id" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <?php foreach ($unassignedUsers as $u): ?>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="user_ids[]" value="<?php echo $u['id']; ?>" class="mr-2">
                                        <span class="text-sm text-gray-700"><?php echo htmlspecialchars($u['name']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="submit" 
                                        class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md font-medium transition duration-200">
                                    Assign Selected Users
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f3f1ff',
                            500: '#7341ff',
                            600: '#631bff',
                            700: '#611bf8'
                        }
                    }
                }
            }
        };

        function manageDepartment(deptId) {
            // Implementation for managing department users
            alert('Department management feature coming soon');
        }
    </script>
</body>
</html>