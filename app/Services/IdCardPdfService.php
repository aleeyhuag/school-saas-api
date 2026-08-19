<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Stage 53 — builds ID card PDFs. Standard CR80 card dimensions
 * (85.6mm x 54mm — the same size as a bank card) for the single-card
 * output, and an A4 grid of the same card design for the print-sheet
 * output — schools without a dedicated card printer can print the
 * sheet on regular paper/cardstock and cut the cards out.
 *
 * One shared Blade partial (`id-cards._card`) is used by both output
 * shapes, so the two never visually drift apart from each other.
 *
 * Deliberately one fixed template — see this stage's own scoping
 * discussion: multiple selectable designs would multiply the design
 * and testing effort several times over for comparatively little
 * value at this stage. `buildForStudents()` takes a `$template`
 * parameter anyway (currently always 'default') so adding a second
 * template later is a matter of adding another Blade view and a
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
        ])->setPaper([0, 0, self::CARD_WIDTH_PT, self::CARD_HEIGHT_PT], 'landscape');
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    public function buildPrintSheet(Collection $students, string $template = 'default')
    {
        $cards = $students->map(fn (Student $student) => $this->cardData($student, $template));

        return Pdf::loadView('id-cards.print-sheet', [
            'cardChunks' => $cards->chunk(self::CARDS_PER_SHEET),
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Everything the Blade partial needs for one card, pre-computed
     * here rather than in the view — the view should just render, not
     * decide.
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
        // already has local access to. Reading straight off the
        // private disk is both simpler and doesn't depend on the app
        // being able to reach its own public URL from inside the
        // container.
        $photoDataUri = null;
        if ($student->photo_path && Storage::disk('private')->exists($student->photo_path)) {
            $photoDataUri = 'data:'.Storage::disk('private')->mimeType($student->photo_path)
                .';base64,'.base64_encode(Storage::disk('private')->get($student->photo_path));
        }

        return [
            'template' => $template,
            'student' => $student,
            'school' => $student->school,
            'class_name' => $student->schoolClass?->full_name ?? '—',
            'qr_data_uri' => $qrDataUri,
            'photo_data_uri' => $photoDataUri,
            'issued_on' => now()->format('M Y'),
        ];
    }
}
