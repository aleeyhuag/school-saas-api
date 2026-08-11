<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $reason, protected string $schoolName) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("We couldn't confirm your payment — {$this->schoolName}")
            ->greeting("Hello {$notifiable->name},")
            ->line('We were unable to confirm your recent bank transfer:')
            ->line($this->reason)
            ->line('Please double-check the details and submit it again from Settings > Billing.')
            ->salutation('— '.$this->schoolName);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Payment could not be confirmed',
            'body' => $this->reason,
        ];
    }
}
