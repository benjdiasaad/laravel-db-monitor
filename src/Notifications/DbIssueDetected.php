<?php

namespace BenjdiaSaad\DbMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use BenjdiaSaad\DbMonitor\Models\DbFinding;

class DbIssueDetected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly DbFinding $finding) {}

    public function via(object $notifiable): array
    {
        return config('db-monitor.notification_channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $emoji = $this->finding->severity === 'critical' ? '🔴' : '🟡';

        return (new MailMessage)
            ->subject("{$emoji} DB Monitor Alert: {$this->humanType()}")
            ->greeting('Database Issue Detected')
            ->line($this->finding->message)
            ->line("**Path:** `{$this->finding->request_path}`")
            ->line("**Time:** {$this->finding->created_at->toDateTimeString()}")
            ->line('This alert was sent by laravel-db-monitor.');
    }

    private function humanType(): string
    {
        return match ($this->finding->type) {
            'slow_query' => 'Slow Query',
            'n_plus_one' => 'N+1 Query',
            'missing_index' => 'Missing Index',
            default => $this->finding->type,
        };
    }
}