<?php

namespace App\Notifications;

use App\Models\Monitor;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorStatusChanged extends Notification
{
    public function __construct(
        private readonly Monitor $monitor,
        private readonly string  $newStatus,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $isUp   = $this->newStatus === 'up';
        $emoji  = $isUp ? '✅' : '🔴';
        $action = $isUp ? 'is back UP' : 'went DOWN';

        return (new MailMessage)
            ->subject("{$emoji} Monitor Alert: {$this->monitor->url} {$action}")
            ->greeting('Uptime Monitor Alert')
            ->line("A monitored site has changed status.")
            ->line("**URL:** {$this->monitor->url}")
            ->line("**New Status:** " . strtoupper($this->newStatus))
            ->line("**Detected At:** " . now()->toDateTimeString())
            ->action('View All Monitors', url('/api/monitors'))
            ->line($isUp
                ? 'Your site has recovered and is responding normally.'
                : 'Your site appears to be unreachable. Please investigate.');
    }
}
