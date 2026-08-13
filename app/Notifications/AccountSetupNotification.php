<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSetupNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $token, protected string $schoolName) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $url = $frontendUrl.'/reset-password?token='.urlencode($this->token).'&email='.urlencode($notifiable->email).'&setup=1';

        return (new MailMessage)
            ->subject("Set up your EduVentor account — {$this->schoolName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your EduVentor account for {$this->schoolName} has been created.")
            ->line('Use the secure button below to choose your own password. You do not need to use a temporary password.')
            ->action('Set My Password', $url)
            ->line('This link expires in 60 minutes and can only be used to set your password.')
            ->salutation('— EduVentor');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Account setup',
            'body' => 'Your account is ready. Check your email for the secure password setup link.',
        ];
    }
}
