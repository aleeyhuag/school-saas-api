<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class School extends Model
{
    protected $fillable = [
        'name', 'slug', 'email', 'phone', 'address', 'logo_path', 'is_active', 'school_group_id',
        'deactivation_reason', 'payment_reference_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Full public URL for the school's logo, or null if none is set —
     * appended to every School JSON response automatically.
     */
    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        // Served through MediaController rather than a raw
        // Storage::disk('public')->url() — see its docblock for why
        // (relying on the public/storage symlink existing has been
        // unreliable on this project's Windows/XAMPP local setup).
        return $this->logo_path ? url('/api/media/logos/'.$this->logo_path) : null;
    }

    public function schoolGroup(): BelongsTo
    {
        return $this->belongsTo(SchoolGroup::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function academicSessions(): HasMany
    {
        return $this->hasMany(AcademicSession::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
