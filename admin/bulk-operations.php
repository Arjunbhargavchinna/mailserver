<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$user = getCurrentUser();
$success = '';
$error = '';

// Handle bulk operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'bulk_send') {
        $subject = trim($_POST['subject']);
        $body = $_POST['body'];
        $recipients = $_POST['recipients'] ?? [];
        $unit = $_POST['unit'] ?? '';
        
        if (empty($subject) || empty($body)) {
            $error = 'Subject and message are required';
        } elseif (empty($recipients) && empty($unit)) {
            $error = 'Please select recipients or a unit';
        } else {
            $recipientIds = [];
            
            if (!empty($unit)) {
                // Get users by unit/department
                $stmt = $pdo->prepare("SELECT id FROM users WHERE department = ? AND is_active = 1");
                $stmt->execute([$unit]);
                while ($row = $stmt->fetch()) {
                    $recipientIds[] = $row['id'];
                }
            } else {
                $recipientIds = $recipients;
            }
            
            $successCount = 0;
            $failCount = 0;
            
            foreach ($recipientIds as $recipientId) {
                $result = sendEmail($user['id'], $recipientId, $subject, $body);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
            
            $success = "Bulk email sent: {$successCount} successful, {$failCount} failed";
            logActivity($user['id'], 'bulk_email_sent', "Sent to {$successCount} recipients");
        }
    }
}

// Get all users grouped by department
$stmt = $pdo->prepare("SELECT * FROM users WHERE is_active = 1 ORDER BY department, name");
$stmt->execute();
$users = $stmt->fetchAll();

$departments = [];
foreach ($users as $u) {
    $dept = $u['department'] ?: 'No Department';
    if (!isset($departments[$dept])) {
        $departments[$dept] = [];
    }
    $departments[$dept][] = $u;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Operations - MailFlow Admin</title>
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
                <h1 class="text-xl font-semibold text-gray-800">Bulk Operations</h1>
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
                <!-- Bulk Email Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">Send Bulk Email</h2>
                        </div>
                        
                        <form method="POST" class="p-6">
                            <input type="hidden" name="action" value="bulk_send">
                            
                            <!-- Recipient Selection -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Recipients</label>
                                <div class="space-y-3">
                                    <div>
                                        <label class="flex items-center">
                                            <input type="radio" name="recipient_type" value="unit" class="mr-2" onchange="toggleRecipientType('unit')">
                                            <span class="text-sm text-gray-700">Send to entire unit/department</span>
                                        </label>
                                        <select name="unit" id="unitSelect" class="mt-2 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500" disabled>
                                            <option value="">Select Department</option>
                                            <?php foreach (array_keys($departments) as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?> (<?php echo count($departments[$dept]); ?> users)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="flex items-center">
                                            <input type="radio" name="recipient_type" value="individual" class="mr-2" onchange="toggleRecipientType('individual')">
                                            <span class="text-sm text-gray-700">Select individual users</span>
                                        </label>
                                        <div id="userSelect" class="mt-2 max-h-48 overflow-y-auto border border-gray-300 rounded-md p-3 bg-gray-50" style="display: none;">
                                            <?php foreach ($departments as $dept => $deptUsers): ?>
                                            <div class="mb-3">
                                                <h4 class="font-medium text-gray-800 mb-2"><?php echo htmlspecialchars($dept); ?></h4>
                                                <?php foreach ($deptUsers as $u): ?>
                                                <label class="flex items-center mb-1">
                                                    <input type="checkbox" name="recipients[]" value="<?php echo $u['id']; ?>" class="mr-2">
                                                    <span class="text-sm text-gray-700"><?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)</span>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                                <input type="text" id="subject" name="subject" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                       placeholder="Email subject">
                            </div>
                            
                            <div class="mb-6">
                                <label for="body" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                                <textarea id="body" name="body" rows="8" required
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 resize-none"
                                          placeholder="Write your message here..."></textarea>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <button type="submit" 
                                        class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-md font-medium transition duration-200 flex items-center">
                                    <span class="material-symbols-outlined mr-2 text-sm">send</span>
                                    Send Bulk Email
                                </button>
                                
                                <button type="button" onclick="previewEmail()" 
                                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md font-medium transition duration-200 flex items-center">
                                    <span class="material-symbols-outlined mr-2 text-sm">preview</span>
                                    Preview
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="space-y-6">
                    <!-- Templates -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Email Templates</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <button onclick="loadTemplate('welcome')" class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50 transition duration-200">
                                    <h4 class="font-medium text-gray-800">Welcome Message</h4>
                                    <p class="text-sm text-gray-600">New user welcome email</p>
                                </button>
                                
                                <button onclick="loadTemplate('announcement')" class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50 transition duration-200">
                                    <h4 class="font-medium text-gray-800">Announcement</h4>
                                    <p class="text-sm text-gray-600">General announcement template</p>
                                </button>
                                
                                <button onclick="loadTemplate('maintenance')" class="w-full text-left p-3 border border-gray-200 rounded-md hover:bg-gray-50 transition duration-200">
                                    <h4 class="font-medium text-gray-800">Maintenance Notice</h4>
                                    <p class="text-sm text-gray-600">System maintenance notification</p>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Department Statistics</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <?php foreach ($departments as $dept => $deptUsers): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700"><?php echo htmlspecialchars($dept); ?></span>
                                    <span class="text-sm font-medium text-gray-900"><?php echo count($deptUsers); ?> users</span>
                                </div>
                                <?php endforeach; ?>
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

        function toggleRecipientType(type) {
            const unitSelect = document.getElementById('unitSelect');
            const userSelect = document.getElementById('userSelect');
            
            if (type === 'unit') {
                unitSelect.disabled = false;
                userSelect.style.display = 'none';
                // Uncheck all individual users
                document.querySelectorAll('input[name="recipients[]"]').forEach(cb => cb.checked = false);
            } else {
                unitSelect.disabled = true;
                unitSelect.value = '';
                userSelect.style.display = 'block';
            }
        }

        function loadTemplate(templateType) {
            const templates = {
                welcome: {
                    subject: 'Welcome to MailFlow',
                    body: 'Dear Team Member,\n\nWelcome to our email system! You now have access to all communication tools and resources.\n\nBest regards,\nAdmin Team'
                },
                announcement: {
                    subject: 'Important Announcement',
                    body: 'Dear Team,\n\nWe have an important announcement to share with you.\n\n[Your announcement here]\n\nThank you,\nManagement'
                },
                maintenance: {
                    subject: 'Scheduled System Maintenance',
                    body: 'Dear Users,\n\nWe will be performing scheduled maintenance on [DATE] from [TIME] to [TIME].\n\nDuring this time, the system may be unavailable.\n\nThank you for your patience,\nIT Team'
                }
            };
            
            if (templates[templateType]) {
                document.getElementById('subject').value = templates[templateType].subject;
                document.getElementById('body').value = templates[templateType].body;
            }
        }

        function previewEmail() {
            const subject = document.getElementById('subject').value;
            const body = document.getElementById('body').value;
            
            if (!subject || !body) {
                alert('Please fill in subject and message first');
                return;
            }
            
            const previewWindow = window.open('', '_blank', 'width=600,height=400');
            previewWindow.document.write(`
                <html>
                <head><title>Email Preview</title></head>
                <body style="font-family: Arial, sans-serif; padding: 20px;">
                    <h3>Subject: ${subject}</h3>
                    <hr>
                    <div style="white-space: pre-wrap;">${body}</div>
                </body>
                </html>
            `);
        }
    </script>
</body>
</html>