<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Stage 53 — builds ID card PDFs. Standard CR80 card dimensions
 * (85.6mm x 54mm — the same size as a bank card) for the single-card
 * output, and an A4 grid of the same card design for the print-sheet
 * output — schools without a dedicated card printer can print the
 * sheet on regular paper/cardstock and cut the cards out.
 *
 * Every card has two sides — front (photo, name, class, session,
 * principal's signature) and back (code of conduct, emergency
 * contact, QR verification code) — via `id-cards._card-front` and
 * `id-cards._card-back`, shared by both output shapes so they can't
 * visually drift apart from each other.
 *
 * Deliberately one fixed template — see this stage's own scoping
 * discussion: multiple selectable designs would multiply the design
 * and testing effort several times over for comparatively little
 * value at this stage. `cardData()` takes a `$template` parameter
 * anyway (currently always 'default') so adding a second template
 * later is a matter of adding another pair of Blade views and a
 * branch here, not restructuring this service.
 */
class IdCardPdfService
{
    protected const CARD_WIDTH_PT = 242.65; // 85.6mm
    protected const CARD_HEIGHT_PT = 153.07; // 54mm
    protected const CARDS_PER_SHEET = 8; // 2 columns x 4 rows on A4, with margin for cutting

    public function buildSingle(int $studentId, string $template = 'default')
    {
        $student = Student::with('schoolClass', 'school')->findOrFail($studentId);

        return Pdf::loadView('id-cards.single', [
            'card' => $this->cardData($student, $template),
            // No second 'landscape' argument here — when setPaper()
            // is given an array size AND 'landscape', dompdf
            // unconditionally swaps width/height (confirmed against
            // dompdf's own source), regardless of whether the array
            // is already landscape-shaped. Ours already is
            // (width > height), so passing 'landscape' on top swapped
            // it into a narrow 153x242 page — the 242pt-wide card
            // content then overflowed past the right edge and got
            // clipped. This was the "cropped, aligned to left edge"
            // bug on both the single download and the bulk ZIP (which
            // calls this same method per student).
        ])->setPaper([0, 0, self::CARD_WIDTH_PT, self::CARD_HEIGHT_PT]);
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    public function buildPrintSheet(Collection $students, string $template = 'default')
    {
        $cards = $students->map(fn (Student $student) => $this->cardData($student, $template));

        return Pdf::loadView('id-cards.print-sheet', [
            // Fronts and backs are interleaved chunk-by-chunk (front
            // page, back page, front page, back page, ...) rather than
            // "all fronts then all backs" — printed double-sided sheet
            // by sheet, that ordering is what keeps each card's back
            // aligned with its own front when cut apart, rather than
            // needing to match up two separate stacks of pages by hand.
            'cardChunks' => $cards->chunk(self::CARDS_PER_SHEET),
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Everything the Blade partials need for one card (both sides),
     * pre-computed here rather than in the view — the view should
     * just render, not decide.
     */
    protected function cardData(Student $student, string $template): array
    {
        $verifyUrl = route('id-card.verify', ['token' => $student->qr_token]);

        // v6 of this package replaced the fluent Builder::create()
        // chain with constructor-based named arguments — confirmed
        // directly against the package's current README (matching
        // your installed 6.1.3), not the older fluent API I tried
        // twice before. new Builder(...) still needs an explicit
        // ->build() call to actually produce the renderable result.
        $qrDataUri = (new Builder(
            writer: new PngWriter(),
            data: $verifyUrl,
            size: 200,
            margin: 4,
        ))->build()->getDataUri();

        // Embedded directly as base64 rather than pointed at
        // Student::photo_url — that URL is meant for the browser to
        // fetch with the person's own session; having dompdf fetch it
        // over HTTP during PDF generation would mean the server making
        // a network round-trip back to itself just to read a file it
        // already has local access to. Reading straight off disk is
        // both simpler and doesn't depend on the app being able to
        // reach its own public URL from inside the container. Also
        // resized down first — see resizedImageDataUri()'s docblock.
        $photoDataUri = null;
        if ($student->photo_path && Storage::disk('private')->exists($student->photo_path)) {
            $photoDataUri = $this->resizedImageDataUri('private', $student->photo_path);
        }

        $signatureDataUri = null;
        if ($student->school->principal_signature_path && Storage::disk('public')->exists($student->school->principal_signature_path)) {
            $signatureDataUri = $this->resizedImageDataUri('public', $student->school->principal_signature_path, 400);
        }

        $currentSession = AcademicSession::where('school_id', $student->school_id)
            ->where('is_current', true)
            ->first();

        return [
            'template' => $template,
            'student' => $student,
            'school' => $student->school,
            'class_name' => $student->schoolClass?->full_name ?? '—',
            'session_name' => $currentSession?->name,
            'qr_data_uri' => $qrDataUri,
            'photo_data_uri' => $photoDataUri,
            'signature_data_uri' => $signatureDataUri,
            'issued_on' => now()->format('M Y'),
        ];
    }

    /**
     * A student photo only ever displays at ~52x62pt on this card
     * (well under 100x120px even at print-quality resolution), but an
     * uploaded phone photo can be a multi-megapixel image right up to
     * the 2MB upload cap. Embedding that full-resolution original
     * meant dompdf had to decode a large bitmap into memory during
     * every single PDF render — under load, on a constrained
     * container, that's a plausible reason a photo would render
     * "sometimes but not always" rather than consistently either way.
     * Resizing down to a fixed max dimension here (also used for the
     * principal's signature, at a slightly larger cap since it's
     * rendered wider) keeps memory use small and predictable, and
     * produces a smaller, faster-generating PDF as a side benefit.
     *
     * Returns null (rather than throwing and failing the whole card)
     * if GD can't decode the file for any reason — a missing photo or
     * signature shouldn't block someone's entire ID card batch.
     */
    protected function resizedImageDataUri(string $disk, string $path, int $maxDimension = 300): ?string
    {
        try {
            $source = imagecreatefromstring(Storage::disk($disk)->get($path));

            if ($source === false) {
                return null;
            }

            $originalWidth = imagesx($source);
            $originalHeight = imagesy($source);
            $scale = min(1, $maxDimension / max($originalWidth, $originalHeight));
            $targetWidth = max(1, (int) round($originalWidth * $scale));
            $targetHeight = max(1, (int) round($originalHeight * $scale));

            $resized = imagecreatetruecolor($targetWidth, $targetHeight);

            // Preserve transparency for signature PNGs — without this,
            // a transparent background would flatten to solid black
            // once copied onto the opaque canvas below.
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);

            imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $originalWidth, $originalHeight);

            ob_start();
            imagepng($resized, null, 6);
            $pngBytes = ob_get_clean();

            imagedestroy($source);
            imagedestroy($resized);

            return 'data:image/png;base64,'.base64_encode($pngBytes);
        } catch (\Throwable $e) {
            Log::warning('Could not resize an ID card image', ['disk' => $disk, 'path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
