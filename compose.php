<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAuth();

$user = getCurrentUser();
$success = '';
$error = '';
$replyTo = $_GET['reply'] ?? null;
$forwardId = $_GET['forward'] ?? null;
$originalEmail = null;

// Handle reply or forward
if ($replyTo || $forwardId) {
    $emailId = $replyTo ?: $forwardId;
    $stmt = $pdo->prepare("
        SELECT e.*, 
               COALESCE(s.name, s.email) as sender_name,
               s.email as sender_email
        FROM emails e
        LEFT JOIN users s ON e.sender_id = s.id
        WHERE e.id = ? AND (e.recipient_id = ? OR e.sender_id = ?)
    ");
    $stmt->execute([$emailId, $user['id'], $user['id']]);
    $originalEmail = $stmt->fetch();
}

if ($_POST) {
    $recipientEmail = trim($_POST['recipient']);
    $subject = trim($_POST['subject']);
    $body = $_POST['body'];
    $isDraft = isset($_POST['save_draft']);
    
    if (empty($recipientEmail) || empty($subject) || empty($body)) {
        $error = 'Please fill in all required fields';
    } else {
        // Find recipient
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$recipientEmail]);
        $recipient = $stmt->fetch();
        
        if (!$recipient) {
            $error = 'Recipient not found';
        } else {
            $result = sendEmail($user['id'], $recipient['id'], $subject, $body);
            if ($result['success']) {
                if (isset($_POST['reply_to'])) {
                    $success = 'Reply sent successfully!';
                } elseif (isset($_POST['forward_id'])) {
                    $success = 'Email forwarded successfully!';
                } else {
                    $success = 'Email sent successfully!';
                }
                // Clear form
                $_POST = [];
                $originalEmail = null;
            } else {
                $error = 'Failed to send email: ' . $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Email - MailFlow</title>
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
                        <span class="font-semibold">Back to Inbox</span>
                    </a>
                </div>
                <h1 class="text-xl font-semibold text-gray-800">Compose Email</h1>
                <div class="w-32"></div> <!-- Spacer for centering -->
            </div>
        </header>

        <!-- Main Content -->
        <div class="max-w-4xl mx-auto py-8 px-6">
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

            <form method="POST" class="bg-white rounded-lg shadow-sm border border-gray-200">
                <!-- Email Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="space-y-4">
                        <div>
                            <label for="recipient" class="block text-sm font-medium text-gray-700 mb-2">To</label>
                            <input type="email" id="recipient" name="recipient" required
                                   value="<?php 
                                   if (isset($_POST['recipient'])) {
                                       echo htmlspecialchars($_POST['recipient']);
                                   } elseif ($replyTo && $originalEmail) {
                                       echo htmlspecialchars($originalEmail['sender_email']);
                                   }
                                   ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="recipient@example.com">
                        </div>
                        
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                            <input type="text" id="subject" name="subject" required
                                   value="<?php 
                                   if (isset($_POST['subject'])) {
                                       echo htmlspecialchars($_POST['subject']);
                                   } elseif ($replyTo && $originalEmail) {
                                       echo 'Re: ' . htmlspecialchars($originalEmail['subject']);
                                   } elseif ($forwardId && $originalEmail) {
                                       echo 'Fwd: ' . htmlspecialchars($originalEmail['subject']);
                                   }
                                   ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Email subject">
                        </div>
                    </div>
                </div>

                <!-- Email Body -->
                <div class="p-6">
                    <label for="body" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                    <textarea id="body" name="body" rows="12" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 resize-none"
                              placeholder="Write your message here..."><?php 
                              if (isset($_POST['body'])) {
                                  echo htmlspecialchars($_POST['body']);
                              } elseif ($replyTo && $originalEmail) {
                                  echo "\n\n--- Original Message ---\n";
                                  echo "From: " . htmlspecialchars($originalEmail['sender_name']) . "\n";
                                  echo "Date: " . date('M j, Y \a\t g:i A', strtotime($originalEmail['created_at'])) . "\n";
                                  echo "Subject: " . htmlspecialchars($originalEmail['subject']) . "\n\n";
                                  echo htmlspecialchars($originalEmail['body']);
                              } elseif ($forwardId && $originalEmail) {
                                  echo "\n\n--- Forwarded Message ---\n";
                                  echo "From: " . htmlspecialchars($originalEmail['sender_name']) . "\n";
                                  echo "Date: " . date('M j, Y \a\t g:i A', strtotime($originalEmail['created_at'])) . "\n";
                                  echo "Subject: " . htmlspecialchars($originalEmail['subject']) . "\n\n";
                                  echo htmlspecialchars($originalEmail['body']);
                              }
                              ?></textarea>
                </div>

                <?php if ($replyTo): ?>
                <input type="hidden" name="reply_to" value="<?php echo $replyTo; ?>">
                <?php elseif ($forwardId): ?>
                <input type="hidden" name="forward_id" value="<?php echo $forwardId; ?>">
                <?php endif; ?>

                <!-- Toolbar -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <button type="submit" 
                                    class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-md font-medium transition duration-200 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-sm">send</span>
                                <?php 
                                if ($replyTo) echo 'Send Reply';
                                elseif ($forwardId) echo 'Forward';
                                else echo 'Send';
                                ?>
                            </button>
                            
                            <button type="submit" name="save_draft" value="1"
                                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md font-medium transition duration-200 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-sm">save</span>
                                Save Draft
                            </button>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <button type="button" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition duration-200">
                                <span class="material-symbols-outlined">attach_file</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
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

        // Auto-save draft functionality
        let autoSaveTimer;
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, textarea');

        function autoSave() {
            const formData = new FormData(form);
            formData.append('save_draft', '1');
            formData.append('auto_save', '1');
            
            fetch('ajax/auto_save.php', {
                method: 'POST',
                body: formData
            });
        }

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(autoSave, 5000); // Auto-save after 5 seconds of inactivity
            });
        });
    </script>
</body>
</html>