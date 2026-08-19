<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Student extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'user_id', 'school_class_id', 'admission_number',
        'first_name', 'last_name', 'date_of_birth', 'gender',
        'guardian_name', 'guardian_phone', 'status', 'photo_path',
    ];

    protected $casts = [
        'status' => 'string',
        'date_of_birth' => 'date',
    ];

    protected $appends = ['photo_url'];

    protected static function booted(): void
    {
        static::creating(function (Student $student) {
            // Every student gets an opaque QR/verification token the
            // moment they're created — see the migration's docblock
            // for why this is a random token rather than the id or
            // admission_number. Generated here (not lazily on first
            // access) so it's never null for a student created after
            // this stage shipped, and so ID card generation never has
            // to special-case "token doesn't exist yet".
            if (! $student->qr_token) {
                $student->qr_token = Str::random(40);
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * Parent/guardian LOGIN ACCOUNTS linked to this student — distinct
     * from the free-text guardian_name/guardian_phone fields, which
     * are just contact info and don't grant any dashboard access.
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_guardians');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(StudentPromotion::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * A 24-hour signed URL, not the 30-minute window MediaController
     * uses for payment proofs — a photo needs to render in ordinary
     * list/roster views without every page load re-signing it, and a
     * photo isn't a financial document, so a longer practical window
     * is the right tradeoff here. Still meaningfully more private than
     * the school logo's permanent public URL: an old link stops
     * working after a day and the path isn't guessable.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return URL::temporarySignedRoute(
            'media.student-photo',
            now()->addHours(24),
            ['student' => $this->id]
        );
    }
}
