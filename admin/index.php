<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$user = getCurrentUser();
$systemStats = getSystemStats();
$auditLogs = getAuditLogs(10);

// Get recent activity stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM emails WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->execute();
$emailsToday = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$newUsersWeek = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MailFlow</title>
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
                    <a href="../index.php" class="flex items-center text-primary-600 hover:text-primary-700">
                        <span class="material-symbols-outlined mr-2">arrow_back</span>
                        <span class="font-semibold">Back to Dashboard</span>
                    </a>
                </div>
                <h1 class="text-xl font-semibold text-gray-800">Admin Dashboard</h1>
                <div class="w-32"></div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto py-8 px-6">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <span class="material-symbols-outlined">group</span>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Users</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $systemStats['total_users']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <span class="material-symbols-outlined">person_check</span>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Active Users</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $systemStats['active_users']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <span class="material-symbols-outlined">email</span>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Emails Today</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $emailsToday; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <span class="material-symbols-outlined">storage</span>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Storage Used</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $systemStats['storage_usage']; ?>%</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- System Status -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">System Status</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-700">Server Status</span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                                    Online
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-700">Mail Queue</span>
                                <span class="text-sm font-medium text-gray-900"><?php echo $systemStats['mail_queue']; ?> pending</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-700">Database</span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Connected
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800">Quick Actions</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4">
                            <a href="users.php" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                                <span class="material-symbols-outlined text-primary-600 mb-2">group</span>
                                <span class="text-sm font-medium text-gray-700">Manage Users</span>
                            </a>
                            <a href="bulk-operations.php" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                                <span class="material-symbols-outlined text-primary-600 mb-2">send</span>
                                <span class="text-sm font-medium text-gray-700">Bulk Operations</span>
                            </a>
                            <a href="mail-server.php" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                                <span class="material-symbols-outlined text-primary-600 mb-2">mail</span>
                                <span class="text-sm font-medium text-gray-700">Mail Server</span>
                            </a>
                            <a href="security.php" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                                <span class="material-symbols-outlined text-primary-600 mb-2">security</span>
                                <span class="text-sm font-medium text-gray-700">Security</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audit Log -->
            <div class="mt-8 bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Activity</h2>
                    <a href="audit.php" class="text-sm text-primary-600 hover:text-primary-700">View All</a>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($auditLogs as $log): ?>
                    <div class="px-6 py-4">
                        <div class="flex items-start">
                            <span class="material-symbols-outlined p-2 bg-<?php echo $log['color']; ?>-100 text-<?php echo $log['color']; ?>-600 rounded-full text-sm mr-3"><?php echo $log['icon']; ?></span>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['action']); ?></p>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($log['details']); ?></p>
                                <div class="flex items-center mt-1 text-xs text-gray-500">
                                    <span><?php echo timeAgo($log['created_at']); ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo htmlspecialchars($log['user_email']); ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo htmlspecialchars($log['ip_address']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
    </script>
</body>
</html>