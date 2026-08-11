<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingNotification extends Notification
{
    use Queueable;

    public function __construct(protected int $daysLeft, protected string $schoolName) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $when = $this->daysLeft <= 0 ? 'today' : "in {$this->daysLeft} day".($this->daysLeft === 1 ? '' : 's');

        return (new MailMessage)
            ->subject("Your {$this->schoolName} trial ends {$when}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your free trial for {$this->schoolName} ends {$when}. Subscribe to keep access — nothing about your data changes, you just won't be able to log in until you do.")
            ->line('Go to Settings > Billing to choose a plan.')
            ->salutation('— '.$this->schoolName);
    }

    public function toArray($notifiable): array
    {
        $when = $this->daysLeft <= 0 ? 'today' : "in {$this->daysLeft} day".($this->daysLeft === 1 ? '' : 's');

        return [
            'title' => 'Trial ending '.$when,
            'body' => 'Subscribe from Settings > Billing to avoid losing access.',
        ];
    }
}
