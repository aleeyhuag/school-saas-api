<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every Proprietor created going forward gets a
     * proprietor_school_access row automatically (see
     * SchoolRegistrationService::register()) — but that doesn't help
     * anyone who registered before this feature existed. Without this
     * backfill, an existing Proprietor's branch switcher would show
     * ZERO branches, not even their own current one. Raw query
     * builder throughout (not Eloquent) — this is a one-time data fix,
     * no model events or scoping concerns should be involved.
     */
    public function up(): void
    {
        $proprietorUserIds = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'proprietor')
            ->whereNotNull('users.school_id')
            ->pluck('users.school_id', 'users.id');

        $now = now();
        $rows = $proprietorUserIds->map(fn ($schoolId, $userId) => [
            'user_id' => $userId,
            'school_id' => $schoolId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('proprietor_school_access')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // Not reversible in a meaningful way — leave existing access
        // rows in place rather than guessing which ones this seeded.
    }
};
