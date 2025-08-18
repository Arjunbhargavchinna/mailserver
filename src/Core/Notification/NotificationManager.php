<?php

declare(strict_types=1);

namespace MailFlow\Core\Notification;

use MailFlow\Core\Config\ConfigManager;
use MailFlow\Core\Database\DatabaseManager;
use MailFlow\Core\Queue\QueueManager;
use MailFlow\Core\Exception\NotificationException;

class NotificationManager
{
    private ConfigManager $config;
    private DatabaseManager $database;
    private QueueManager $queue;
    private array $channels = [];

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function setDatabase(DatabaseManager $database): void
    {
        $this->database = $database;
    }

    public function setQueue(QueueManager $queue): void
    {
        $this->queue = $queue;
    }

    public function send(NotificationInterface $notification, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            $channels = $notification->via($recipient);
            
            foreach ($channels as $channel) {
                $this->sendViaChannel($channel, $notification, $recipient);
            }
        }
    }

    public function sendNow(NotificationInterface $notification, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            $channels = $notification->via($recipient);
            
            foreach ($channels as $channel) {
                $this->getChannel($channel)->send($notification, $recipient);
            }
        }
    }

    public function route(string $channel, mixed $route): PendingNotification
    {
        return new PendingNotification($this, $channel, $route);
    }

    private function sendViaChannel(string $channel, NotificationInterface $notification, mixed $recipient): void
    {
        if ($this->shouldQueue($notification, $channel)) {
            $this->queue->push(SendQueuedNotification::class, [
                'notification' => serialize($notification),
                'recipient' => serialize($recipient),
                'channel' => $channel,
            ]);
        } else {
            $this->getChannel($channel)->send($notification, $recipient);
        }
    }

    private function getChannel(string $name): NotificationChannelInterface
    {
        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        $channel = match ($name) {
            'mail' => new MailChannel($this->config),
            'database' => new DatabaseChannel($this->database),
            'slack' => new SlackChannel($this->config),
            'sms' => new SmsChannel($this->config),
            'push' => new PushChannel($this->config),
            'webhook' => new WebhookChannel($this->config),
            default => throw new NotificationException("Unsupported notification channel: {$name}")
        };

        $this->channels[$name] = $channel;
        return $channel;
    }

    private function shouldQueue(NotificationInterface $notification, string $channel): bool
    {
        if (method_exists($notification, 'shouldQueue')) {
            return $notification->shouldQueue($channel);
        }

        return $this->config->get("notifications.channels.{$channel}.queue", false);
    }
}

interface NotificationInterface
{
    public function via(mixed $notifiable): array;
    public function toMail(mixed $notifiable): MailMessage;
    public function toDatabase(mixed $notifiable): array;
    public function toSlack(mixed $notifiable): SlackMessage;
    public function toSms(mixed $notifiable): SmsMessage;
}

interface NotificationChannelInterface
{
    public function send(NotificationInterface $notification, mixed $notifiable): void;
}

class PendingNotification
{
    private NotificationManager $manager;
    private string $channel;
    private mixed $route;

    public function __construct(NotificationManager $manager, string $channel, mixed $route)
    {
        $this->manager = $manager;
        $this->channel = $channel;
        $this->route = $route;
    }

    public function notify(NotificationInterface $notification): void
    {
        $this->manager->send($notification, [$this->route]);
    }
}

class MailChannel implements NotificationChannelInterface
{
    private ConfigManager $config;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function send(NotificationInterface $notification, mixed $notifiable): void
    {
        if (!method_exists($notification, 'toMail')) {
            return;
        }

        $message = $notification->toMail($notifiable);
        
        // Send email using configured mailer
        // Implementation depends on your mail system
    }
}

class DatabaseChannel implements NotificationChannelInterface
{
    private DatabaseManager $database;

    public function __construct(DatabaseManager $database)
    {
        $this->database = $database;
    }

    public function send(NotificationInterface $notification, mixed $notifiable): void
    {
        if (!method_exists($notification, 'toDatabase')) {
            return;
        }

        $data = $notification->toDatabase($notifiable);
        
        $stmt = $this->database->connection()->prepare("
            INSERT INTO notifications (user_id, type, title, message, data, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $notifiable->id ?? null,
            get_class($notification),
            $data['title'] ?? '',
            $data['message'] ?? '',
            json_encode($data),
        ]);
    }
}

class SlackChannel implements NotificationChannelInterface
{
    private ConfigManager $config;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function send(NotificationInterface $notification, mixed $notifiable): void
    {
        if (!method_exists($notification, 'toSlack')) {
            return;
        }

        $message = $notification->toSlack($notifiable);
        
        // Send to Slack webhook
        $webhookUrl = $this->config->get('notifications.slack.webhook_url');
        
        if ($webhookUrl) {
            $payload = json_encode($message->toArray());
            
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ]);
            
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

class SmsChannel implements NotificationChannelInterface
{
    private ConfigManager $config;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function send(NotificationInterface $notification, mixed $notifiable): void
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);
        
        // Send SMS using configured provider (Twilio, etc.)
        // Implementation depends on your SMS provider
    }
}

class PushChannel implements NotificationChannelInterface
{
    private ConfigManager $config;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function send(NotificationInterface $notification, mixed $notifiable): void
    {
        if (!method_exists($notification, 'toPush')) {
            return;
        }

        $message = $notification->toPush($notifiable);
        
        // Send push notification using configured service
        // Implementation depends on your push notification service
    }
}

class WebhookChannel implements NotificationChannelInterface
{
    private ConfigManager $config;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function send(NotificationInterface $notification, mixed $notifiable): void
    {
        if (!method_exists($notification, 'toWebhook')) {
            return;
        }

        $message = $notification->toWebhook($notifiable);
        
        // Send webhook
        $webhookUrl = $notifiable->webhook_url ?? $this->config->get('notifications.webhook.default_url');
        
        if ($webhookUrl) {
            $payload = json_encode($message->toArray());
            
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ]);
            
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

class MailMessage
{
    public string $subject = '';
    public string $greeting = '';
    public array $lines = [];
    public string $actionText = '';
    public string $actionUrl = '';
    public string $outroLines = '';

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function greeting(string $greeting): self
    {
        $this->greeting = $greeting;
        return $this;
    }

    public function line(string $line): self
    {
        $this->lines[] = $line;
        return $this;
    }

    public function action(string $text, string $url): self
    {
        $this->actionText = $text;
        $this->actionUrl = $url;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'greeting' => $this->greeting,
            'lines' => $this->lines,
            'action_text' => $this->actionText,
            'action_url' => $this->actionUrl,
            'outro_lines' => $this->outroLines,
        ];
    }
}

class SlackMessage
{
    public string $text = '';
    public string $channel = '';
    public string $username = '';
    public string $icon = '';
    public array $attachments = [];

    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function channel(string $channel): self
    {
        $this->channel = $channel;
        return $this;
    }

    public function username(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function attachment(array $attachment): self
    {
        $this->attachments[] = $attachment;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'channel' => $this->channel,
            'username' => $this->username,
            'icon_emoji' => $this->icon,
            'attachments' => $this->attachments,
        ];
    }
}

class SmsMessage
{
    public string $content = '';

    public function content(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
        ];
    }
}

class SendQueuedNotification
{
    public function handle(array $data): void
    {
        $notification = unserialize($data['notification']);
        $recipient = unserialize($data['recipient']);
        $channel = $data['channel'];
        
        // Get notification manager and send
        // This would be injected in a real implementation
    }
}

class NotificationException extends \Exception
{
}