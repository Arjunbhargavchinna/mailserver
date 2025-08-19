<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$user = getCurrentUser();
$success = '';
$error = '';

// Handle mail server configuration
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'test_connection') {
        $host = $_POST['smtp_host'];
        $port = $_POST['smtp_port'];
        $username = $_POST['smtp_username'];
        $password = $_POST['smtp_password'];
        
        // Test SMTP connection
        $result = testSMTPConnection($host, $port, $username, $password);
        if ($result['success']) {
            $success = 'SMTP connection successful!';
        } else {
            $error = 'SMTP connection failed: ' . $result['message'];
        }
    } elseif ($action === 'save_config') {
        $settings = [
            'smtp_host' => $_POST['smtp_host'],
            'smtp_port' => $_POST['smtp_port'],
            'smtp_username' => $_POST['smtp_username'],
            'smtp_password' => $_POST['smtp_password'],
            'smtp_encryption' => $_POST['smtp_encryption'],
            'mail_from_name' => $_POST['mail_from_name'],
            'mail_from_address' => $_POST['mail_from_address']
        ];
        
        try {
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$key, $value]);
            }
            
            $success = 'Mail server configuration saved successfully';
            logActivity($user['id'], 'mail_config_update', 'Mail server settings updated');
            
        } catch (Exception $e) {
            $error = 'Failed to save configuration: ' . $e->getMessage();
        }
    }
}

// Get current mail server settings
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%' OR setting_key LIKE 'mail_%'");
$stmt->execute();
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get mail queue statistics
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM email_queue GROUP BY status");
$stmt->execute();
$queueStats = [];
while ($row = $stmt->fetch()) {
    $queueStats[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Server Configuration - MailFlow Admin</title>
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
                <h1 class="text-xl font-semibold text-gray-800">Mail Server Configuration</h1>
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
                <!-- SMTP Configuration -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">SMTP Server Settings</h2>
                        </div>
                        
                        <form method="POST" class="p-6">
                            <input type="hidden" name="action" value="save_config">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-2">SMTP Host</label>
                                    <input type="text" id="smtp_host" name="smtp_host" 
                                           value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                           placeholder="smtp.gmail.com">
                                </div>
                                
                                <div>
                                    <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-2">SMTP Port</label>
                                    <input type="number" id="smtp_port" name="smtp_port" 
                                           value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                    <input type="text" id="smtp_username" name="smtp_username" 
                                           value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
                                </div>
                                
                                <div>
                                    <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                                    <input type="password" id="smtp_password" name="smtp_password" 
                                           value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 mb-2">Encryption</label>
                                <select id="smtp_encryption" name="smtp_encryption" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="tls" <?php echo ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                                </select>
                            </div>
                            
                            <hr class="mb-6">
                            
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Default Sender Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="mail_from_name" class="block text-sm font-medium text-gray-700 mb-2">From Name</label>
                                    <input type="text" id="mail_from_name" name="mail_from_name" 
                                           value="<?php echo htmlspecialchars($settings['mail_from_name'] ?? 'MailFlow System'); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
                                </div>
                                
                                <div>
                                    <label for="mail_from_address" class="block text-sm font-medium text-gray-700 mb-2">From Email</label>
                                    <input type="email" id="mail_from_address" name="mail_from_address" 
                                           value="<?php echo htmlspecialchars($settings['mail_from_address'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-4">
                                <button type="submit" 
                                        class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-md font-medium transition duration-200">
                                    Save Configuration
                                </button>
                                
                                <button type="button" onclick="testConnection()" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md font-medium transition duration-200">
                                    Test Connection
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Mail Server Status & Queue -->
                <div class="space-y-6">
                    <!-- Server Status -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Server Status</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">SMTP Status</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                                        Connected
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Queue Status</span>
                                    <span class="text-sm font-medium text-gray-900">Processing</span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Last Test</span>
                                    <span class="text-sm text-gray-500">2 minutes ago</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mail Queue Statistics -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Mail Queue</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Pending</span>
                                    <span class="text-sm font-medium text-yellow-600"><?php echo $queueStats['pending'] ?? 0; ?></span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Processing</span>
                                    <span class="text-sm font-medium text-blue-600"><?php echo $queueStats['processing'] ?? 0; ?></span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Sent</span>
                                    <span class="text-sm font-medium text-green-600"><?php echo $queueStats['sent'] ?? 0; ?></span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">Failed</span>
                                    <span class="text-sm font-medium text-red-600"><?php echo $queueStats['failed'] ?? 0; ?></span>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <button onclick="processQueue()" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md font-medium transition duration-200">
                                    Process Queue
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Setup Guides -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Setup Guides</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <button onclick="showGmailSetup()" class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50 transition duration-200">
                                    <h4 class="font-medium text-gray-800">Gmail SMTP</h4>
                                    <p class="text-sm text-gray-600">Configure Gmail SMTP</p>
                                </button>
                                
                                <button onclick="showOutlookSetup()" class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50 transition duration-200">
                                    <h4 class="font-medium text-gray-800">Outlook SMTP</h4>
                                    <p class="text-sm text-gray-600">Configure Outlook SMTP</p>
                                </button>
                                
                                <button onclick="showCustomSetup()" class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50 transition duration-200">
                                    <h4 class="font-medium text-gray-800">Custom Server</h4>
                                    <p class="text-sm text-gray-600">Custom SMTP setup</p>
                                </button>
                            </div>
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

        function testConnection() {
            const form = document.querySelector('form');
            const formData = new FormData(form);
            formData.set('action', 'test_connection');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                location.reload();
            });
        }

        function processQueue() {
            if (confirm('Process all pending emails in the queue?')) {
                // Implementation would go here
                alert('Queue processing started');
            }
        }

        function showGmailSetup() {
            alert('Gmail SMTP Setup:\n\nHost: smtp.gmail.com\nPort: 587\nEncryption: TLS\n\nNote: Use App Password for authentication');
        }

        function showOutlookSetup() {
            alert('Outlook SMTP Setup:\n\nHost: smtp-mail.outlook.com\nPort: 587\nEncryption: TLS');
        }

        function showCustomSetup() {
            alert('Custom SMTP Setup:\n\nContact your hosting provider for SMTP settings.\nCommon ports: 25, 465 (SSL), 587 (TLS)');
        }
    </script>
</body>
</html>