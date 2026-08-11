<?php

namespace App\Http\Controllers\Api\Announcements;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Notifications\AnnouncementPostedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    /**
     * A user's feed — everything Announcement::scopeVisibleTo() says
     * they're allowed to see, newest first, with each item's read
     * status for this user attached.
     */
    public function index()
    {
        $user = Auth::user();

        $readIds = AnnouncementRead::where('user_id', $user->id)->pluck('announcement_id')->flip();

        $announcements = Announcement::visibleTo($user)
            ->with(['postedBy', 'schoolClass'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'audience' => $a->audience,
                'school_class' => $a->schoolClass ? "{$a->schoolClass->name} {$a->schoolClass->arm}" : null,
                'posted_by' => $a->postedBy->name,
                'created_at' => $a->created_at,
                'is_read' => $readIds->has($a->id),
            ]);

        return response()->json([
            'unread_count' => $announcements->where('is_read', false)->count(),
            'announcements' => $announcements->values(),
        ]);
    }

    /**
     * What audience options THIS user is allowed to post with, and
     * (for a class teacher) which class they'd be posting to — the
     * frontend uses this to build the right form instead of hardcoding
     * role assumptions that would drift out of sync with this method.
     */
    public function composeOptions()
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['proprietor', 'principal', 'exam_officer'])) {
            return response()->json(['audiences' => ['whole_school', 'staff_only']]);
        }

        if ($user->hasRole('bursar')) {
            return response()->json(['audiences' => ['whole_school']]);
        }

        if ($user->hasRole('teacher')) {
            $classAssignment = TeacherAssignment::where('user_id', $user->id)
                ->where('is_class_teacher', true)
                ->with('schoolClass')
                ->first();

            if (! $classAssignment) {
                return response()->json(['audiences' => [], 'message' => 'You need a Class Teacher assignment to post announcements.']);
            }

            return response()->json([
                'audiences' => ['class'],
                'school_class_id' => $classAssignment->school_class_id,
                'school_class_name' => "{$classAssignment->schoolClass->name} {$classAssignment->schoolClass->arm}",
            ]);
        }

        return response()->json(['audiences' => []]);
    }

    public function store()
    {
        $user = Auth::user();
        $validated = request()->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', 'in:whole_school,staff_only,class'],
            'include_parents' => ['sometimes', 'boolean'],
            'notify_by_email' => ['sometimes', 'boolean'],
        ]);

        $announcementData = [
            'school_id' => $user->school_id,
            'posted_by' => $user->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
        ];

        if ($user->hasAnyRole(['proprietor', 'principal', 'exam_officer'])) {
            if (! in_array($validated['audience'], ['whole_school', 'staff_only'], true)) {
                throw ValidationException::withMessages(['audience' => ['Choose Whole School or Staff Only.']]);
            }
            $announcementData['audience'] = $validated['audience'];
            $announcementData['notify_by_email'] = $validated['notify_by_email'] ?? true;
        } elseif ($user->hasRole('bursar')) {
            // Bursar posts are always school-wide — fee matters
            // concern parents directly, there's no narrower option.
            $announcementData['audience'] = 'whole_school';
            $announcementData['notify_by_email'] = $validated['notify_by_email'] ?? true;
        } elseif ($user->hasRole('teacher')) {
            $classAssignment = TeacherAssignment::where('user_id', $user->id)
                ->where('is_class_teacher', true)
                ->first();

            if (! $classAssignment) {
                throw ValidationException::withMessages(['audience' => ['You need a Class Teacher assignment to post announcements.']]);
            }

            $announcementData['audience'] = 'class';
            $announcementData['school_class_id'] = $classAssignment->school_class_id;
            $announcementData['include_parents'] = $validated['include_parents'] ?? true;
            // Class-level posts are frequent, day-to-day updates —
            // default to in-app only, not an inbox ping every time,
            // unless the teacher explicitly opts in.
            $announcementData['notify_by_email'] = $validated['notify_by_email'] ?? false;
        } else {
            throw ValidationException::withMessages(['audience' => ['You are not able to post announcements.']]);
        }

        $announcement = Announcement::create($announcementData);

        if ($announcement->notify_by_email) {
            $this->notifyRecipients($announcement, $user);
        }

        return response()->json($announcement->load('postedBy', 'schoolClass'), 201);
    }

    public function markRead(Announcement $announcement)
    {
        $user = Auth::user();

        AnnouncementRead::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()]
        );

        return response()->json(['message' => 'Marked as read.']);
    }

    /**
     * Emails everyone the announcement is visible to — reuses the
     * exact same Announcement::visibleTo() scope the feed itself is
     * built from, so the email list and the feed can never disagree
     * about who's in the audience.
     */
    protected function notifyRecipients(Announcement $announcement, User $poster): void
    {
        User::where('school_id', $poster->school_id)
            ->where('id', '!=', $poster->id)
            ->get()
            ->filter(fn ($candidate) => Announcement::where('id', $announcement->id)->visibleTo($candidate)->exists())
            ->each(fn ($recipient) => $recipient->notify(new AnnouncementPostedNotification(
                $announcement->title, $announcement->body, $poster->name, $poster->school->name
            )));
    }
}
