<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Add this trait to any model that belongs to a single school
 * (e.g. AcademicSession, Term, SchoolClass, Subject, Student, etc.)
 *
 * It automatically scopes every query to the logged-in user's school_id,
 * so a query for "all classes" only ever returns classes for the
 * current user's own school - never another school's data.
 */
trait BelongsToSchool
{
    protected static function bootBelongsToSchool(): void
    {
        // Automatically filter every query by the current user's school_id
        static::addGlobalScope('school', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            if (Auth::user()->school_id) {
                $builder->where(
                    $builder->getModel()->getTable().'.school_id',
                    Auth::user()->school_id
                );
            } else {
                // No school context (e.g. a Super Admin session, which
                // has no school_id at all) — match NOTHING rather than
                // silently returning every school's data unscoped.
                // Cross-tenant views (platform stats, the school list)
                // deliberately use raw DB::table() queries instead of
                // Eloquent models for exactly this reason — if code
                // ever reaches this branch, it should see an empty
                // result, not a leak.
                $builder->whereRaw('1 = 0');
            }
        });

        // Automatically set school_id when creating a new record
        static::creating(function (Model $model) {
            if (Auth::check() && Auth::user()->school_id && ! $model->school_id) {
                $model->school_id = Auth::user()->school_id;
            }
        });
    }
}
