<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * This is an API-only Laravel app with a separate React frontend, so
 * we can't use Laravel's default ResetPassword notification — it
 * builds a link via a named backend route ('password.reset') that
 * doesn't exist here. Instead this points straight at the SPA's
 * reset-password page, with the token and email as query params.
 */
class PasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $resetUrl = "{$frontendUrl}/reset-password?token={$this->token}&email=".urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Reset your password')
            ->greeting("Hello {$notifiable->name},")
            ->line('You requested a password reset. Click below to choose a new password.')
            ->action('Reset Password', $resetUrl)
            ->line('This link expires in 60 minutes. If you didn\'t request this, you can ignore this email.');
    }
}
