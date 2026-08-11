<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $studentName,
        protected string $termName,
        protected string $schoolName,
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->termName} results published — {$this->schoolName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->studentName}'s results for {$this->termName} have been published and are now available to view.")
            ->salutation('— '.$this->schoolName);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Results published',
            'body' => "{$this->studentName}'s {$this->termName} results are now available.",
        ];
    }
}
