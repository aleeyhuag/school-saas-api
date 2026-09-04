<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;

class School extends Model
{
    protected $fillable = [
        'name', 'slug', 'email', 'phone', 'address', 'logo_path', 'principal_signature_path',
        'is_active', 'school_group_id', 'deactivation_reason', 'payment_reference_code',
        'auto_generate_admission_numbers', 'admission_number_sequence',
        'self_enrollment_enabled', 'referred_by_partner_id', 'referral_commission_start_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_generate_admission_numbers' => 'boolean',
        'self_enrollment_enabled' => 'boolean',
        'referral_commission_start_at' => 'datetime',
    ];

    /**
     * Atomically claims the next admission number for this school and
     * persists the incremented counter in the same query — two
     * students being added at the same moment can never be handed the
     * same number. Format is a short letters-only prefix derived from
     * the school's name plus a zero-padded sequence, e.g. "GRE-0001".
     * Only called when the school has opted into auto-generation (see
     * StudentRequest) — schools with their own existing numbering
     * scheme keep typing admission numbers in manually, unaffected.
     */
    public function nextAdmissionNumber(): string
    {
        $this->increment('admission_number_sequence');

        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $this->name), 0, 3));
        $prefix = $prefix !== '' ? $prefix : 'STU';

        return sprintf('%s-%04d', $prefix, $this->admission_number_sequence);
    }

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
    protected $appends = ['logo_url', 'principal_signature_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->mediaUrl('media.logo', $this->logo_path);
    }

    /**
     * Same public disk/serving pattern as the logo — a school's own
     * institutional signature isn't sensitive personal data the way a
     * student's photo is, so there's no need for the private-disk
     * treatment those get.
     */
    public function getPrincipalSignatureUrlAttribute(): ?string
    {
        return $this->mediaUrl('media.signature', $this->principal_signature_path);
    }

    /**
     * Build media URLs from the current request host/scheme when available.
     * This matters on Render because APP_URL can lag behind a custom-domain
     * change. A stale APP_URL produces perfectly valid-looking URLs that
     * point at the wrong host (or HTTP), which shows up in the frontend as
     * a broken school logo. CLI/queue rendering falls back to Laravel's
     * configured APP_URL.
     */
    protected function mediaUrl(string $routeName, ?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $request = request();
        if ($request instanceof Request) {
            return $request->getSchemeAndHttpHost().route($routeName, ['path' => $path], false);
        }

        return route($routeName, ['path' => $path]);
    }

    public function schoolGroup(): BelongsTo
    {
        return $this->belongsTo(SchoolGroup::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function referralPartner(): BelongsTo
    {
        return $this->belongsTo(ReferralPartner::class, 'referred_by_partner_id');
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
