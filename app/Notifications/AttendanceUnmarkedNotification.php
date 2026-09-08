<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AttendanceUnmarkedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $className,
        public string $date,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'attendance_unmarked',
            'title' => 'Attendance not marked',
            'body' => "Whole-day attendance has not been marked for {$this->className} on {$this->date}.",
            'class_name' => $this->className,
            'date' => $this->date,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
