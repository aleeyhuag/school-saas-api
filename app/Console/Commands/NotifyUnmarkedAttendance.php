<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Models\Attendance;
use App\Notifications\AttendanceUnmarkedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class NotifyUnmarkedAttendance extends Command
{
    protected $signature = 'attendance:notify-unmarked {--date=}';
    protected $description = 'Notify principals when a current-term class has no whole-day attendance mark for a school day.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'), 'Africa/Lagos')->toDateString()
            : Carbon::now('Africa/Lagos')->toDateString();

        $notified = 0;

        School::query()->each(function (School $school) use ($date, &$notified) {
            $term = Term::where('school_id', $school->id)
                ->whereHas('academicSession', fn ($q) => $q->where('is_current', true))
                ->first();

            if (! $term) {
                return;
            }

            $classes = $school->schoolClasses()
                ->whereHas('students', fn ($q) => $q->where('status', 'active'))
                ->get();

            if ($classes->isEmpty()) {
                return;
            }

            $principals = User::where('school_id', $school->id)->role('principal')->get();

            foreach ($classes as $class) {
                $marked = Attendance::where('school_id', $school->id)
                    ->where('term_id', $term->id)
                    ->where('school_class_id', $class->id)
                    ->whereDate('date', $date)
                    ->whereNull('subject_id')
                    ->exists();

                if ($marked) {
                    continue;
                }

                foreach ($principals as $principal) {
                    $alreadySent = $principal->notifications()
                        ->where('type', AttendanceUnmarkedNotification::class)
                        ->whereDate('created_at', $date)
                        ->where('data->class_name', $class->full_name)
                        ->exists();

                    if ($alreadySent) {
                        continue;
                    }

                    $principal->notify(new AttendanceUnmarkedNotification($class->full_name, $date));
                    $notified++;
                }
            }
        });

        $this->info("Created {$notified} unmarked-attendance notification(s).");
        return self::SUCCESS;
    }
}
