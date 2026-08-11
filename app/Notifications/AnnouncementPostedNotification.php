<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail-only, deliberately no 'database' channel — the announcements
 * feed itself (Announcement + AnnouncementRead) is already the
 * in-app record; adding a duplicate row per recipient into the
 * generic notifications table would just be two sources of truth
 * that could drift apart.
 */
class AnnouncementPostedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $title,
        protected string $body,
        protected string $postedByName,
        protected string $schoolName,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New announcement: {$this->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->postedByName} posted a new announcement on {$this->schoolName}'s portal:")
            ->line("**{$this->title}**")
            ->line($this->body)
            ->salutation('— '.$this->schoolName);
    }
}
