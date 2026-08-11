<?php

namespace App\Models;

// Note: Laravel already generates app/Models/User.php for you.
// Don't create a new file — instead, update your EXISTING User.php
// to match this one. Key changes are marked with comments below.

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles; // <-- added: gives $user->assignRole(), $user->hasRole(), etc.

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles; // <-- added HasApiTokens + HasRoles

    protected $fillable = [
        'name',
        'email',
        'password',
        'school_id',  // <-- added
        'phone',      // <-- added
        'status',     // <-- added: pending | approved | disabled
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ---- Added relationship ----
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * For a PARENT account: the student(s) they're a guardian of.
     * Distinct from student() above, which is for a STUDENT's own
     * login account pointing to their own single Student record.
     */
    public function children(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians');
    }

    /**
     * For a PROPRIETOR account: every branch (School) this login can
     * switch into — every other role stays tied to exactly one
     * school via school_id above, only a Proprietor ever has more
     * than one row here. See BranchController.
     */
    public function accessibleSchools(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(School::class, 'proprietor_school_access')->withTimestamps();
    }

    /**
     * Overrides Laravel's default reset-password email, which links
     * to a named backend route that doesn't exist in this API-only
     * app — see App\Notifications\PasswordResetNotification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\PasswordResetNotification($token));
    }
}
