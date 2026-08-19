<?php

namespace App\Http\Controllers;

use App\Models\Student;

/**
 * Stage 53 — the page a QR code on an ID card actually leads to.
 * Deliberately public (no login) — the whole point is that anyone
 * holding the physical card (a security guard, a parent, a teacher
 * without their own account) can scan it and get a straight answer.
 *
 * Deliberately minimal in what it shows: name, photo, class, school,
 * and whether the card is currently valid. No admission number (that
 * has other lookup value and doesn't need to be public), no guardian
 * info, no contact details, no date of birth — nothing beyond what's
 * already printed on the physical card itself. The QR code encodes
 * only the opaque token, never any of this data directly — see the
 * migration and Student::qr_token for why.
 */
class VerifyController extends Controller
{
    public function show(string $token)
    {
        $student = Student::with('schoolClass', 'school')
            ->where('qr_token', $token)
            ->first();

        if (! $student) {
            return view('verify.invalid');
        }

        return view('verify.show', [
            'student' => $student,
            'school' => $student->school,
            'className' => $student->schoolClass?->full_name ?? '—',
            'isActive' => $student->status === 'active',
        ]);
    }
}
