<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TempPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $temporaryPassword,
        protected string $schoolName,
        protected bool $isReset = false,
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $action = $this->isReset ? 'Your password has been reset' : 'Your account is ready';

        return (new MailMessage)
            ->subject("{$action} — {$this->schoolName}")
            ->greeting("Hello {$notifiable->name},")
            ->line($this->isReset
                ? "Your password for {$this->schoolName} has been reset by your school admin."
                : "An account has been created for you on {$this->schoolName}'s portal.")
            ->line("Email: {$notifiable->email}")
            ->line("Temporary password: {$this->temporaryPassword}")
            ->line('Please log in and change this password as soon as possible.')
            ->salutation('— '.$this->schoolName);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->isReset ? 'Password reset' : 'Account created',
            'body' => $this->isReset
                ? 'Your password was reset. Check your email for the new temporary password.'
                : 'Your account is ready. Check your email for your login details.',
        ];
    }
}
