<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(protected bool $isActive, protected string $schoolName) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting("Hello {$notifiable->name},");

        return $this->isActive
            ? $mail->subject("{$this->schoolName} has been reactivated")
                ->line("{$this->schoolName} has been reactivated on the platform. Everyone can log in as normal again.")
            : $mail->subject("{$this->schoolName} has been deactivated")
                ->line("{$this->schoolName} has been deactivated on the platform. No one will be able to log in until it's reactivated.")
                ->line('Contact the platform administrator if this is unexpected.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->isActive ? 'School reactivated' : 'School deactivated',
            'body' => $this->isActive
                ? "{$this->schoolName} is active again."
                : "{$this->schoolName} has been deactivated.",
        ];
    }
}
