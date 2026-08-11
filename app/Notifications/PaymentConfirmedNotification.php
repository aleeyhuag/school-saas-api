<?php

namespace App\Notifications;

use App\Models\Plan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Plan $plan, protected string $schoolName) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment confirmed — {$this->schoolName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your payment for the {$this->plan->name} plan has been confirmed. {$this->schoolName} is fully active.")
            ->line('Thank you!')
            ->salutation('— '.$this->schoolName);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Payment confirmed',
            'body' => "Your {$this->plan->name} plan payment was confirmed — you're all set.",
        ];
    }
}
