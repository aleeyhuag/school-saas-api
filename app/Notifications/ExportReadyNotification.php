<?php

namespace App\Notifications;

use App\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(protected Export $export) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your export is ready — '.$this->label())
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$this->label()} export has finished and is ready to download.")
            ->line('The download link is valid for 30 minutes from when you open it — if it expires, just request the export again from the app.')
            ->action('Open Skulag', rtrim(config('app.frontend_url'), '/'));
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Export ready',
            'body' => "Your {$this->label()} export has finished and is ready to download.",
            'export_id' => $this->export->id,
        ];
    }

    protected function label(): string
    {
        return match ($this->export->type) {
            'school_backup' => 'school backup',
            'report_card_class_bulk' => 'class report card bundle',
            default => str($this->export->type)->replace('_', ' ')->toString(),
        };
    }
}
