<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeeReminderNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{name: string, class: string, balance: float}>  $owingChildren
     */
    public function __construct(
        protected array $owingChildren,
        protected string $schoolName,
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Fee balance reminder — {$this->schoolName}")
            ->greeting("Dear {$notifiable->name},")
            ->line('This is a reminder that the following fee balance(s) are outstanding:');

        foreach ($this->owingChildren as $child) {
            $mail->line("- {$child['name']} ({$child['class']}): ₦".number_format($child['balance']).' outstanding');
        }

        return $mail
            ->line('Please make payment at your earliest convenience. Thank you.')
            ->salutation('— '.$this->schoolName);
    }

    public function toArray($notifiable): array
    {
        $total = array_sum(array_column($this->owingChildren, 'balance'));

        return [
            'title' => 'Fee balance reminder',
            'body' => 'Outstanding balance of ₦'.number_format($total).' across '.count($this->owingChildren).' child(ren).',
        ];
    }
}
