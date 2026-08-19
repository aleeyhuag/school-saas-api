<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Stage 53 — ID Cards.
 *
 * `photo_path` — no student photo field existed anywhere in the app
 * before this; an ID card without a photo isn't much of an ID card,
 * so this is a genuine prerequisite, not scope creep. Stored on the
 * PRIVATE disk (see MediaController::studentPhoto()) rather than the
 * public disk school logos use — a roster of children's photos is a
 * meaningfully more sensitive thing to leave permanently public than
 * a school's own logo.
 *
 * `qr_token` — a random, unique, non-guessable, non-sequential string.
 * This is deliberately NOT the student's id, admission_number, or any
 * other value that means something on its own — it's an opaque lookup
 * key. The QR code on a card encodes a URL built from this token; the
 * verification page it leads to looks the token up server-side and
 * shows only minimal, already-on-the-card information (name, photo,
 * class, school). Nothing about the student is recoverable from the
 * QR code itself without that server-side lookup — matching the
 * explicit "don't put private information in the QR" requirement this
 * stage was scoped with. The same token is what a future QR-scan
 * attendance/check-in feature would key off, without needing to
 * reissue any card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('guardian_phone');
            $table->string('qr_token', 40)->nullable()->unique()->after('photo_path');
        });

        // Backfill existing students — new ones get a token automatically
        // via Student::booted() (creating event). Looping in PHP rather
        // than a single SQL statement since each row needs its own
        // random value; fine at pilot scale (thousands of rows, one-time).
        DB::table('students')->whereNull('qr_token')->orderBy('id')->chunkById(500, function ($students) {
            foreach ($students as $student) {
                DB::table('students')->where('id', $student->id)->update([
                    'qr_token' => Str::random(40),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'qr_token']);
        });
    }
};
