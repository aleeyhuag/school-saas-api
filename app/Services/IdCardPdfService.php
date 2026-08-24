<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the Skulag default student ID card.
 *
 * The default template is intentionally based on the approved Greenfield
 * Academy reference: a fixed CR80 front/back card, premium green/gold
 * treatment, large student photo, structured student facts, principal
 * signature, school contact strip, conduct pledge, emergency contact and
 * QR verification on the back.
 *
 * The template is data-driven rather than a background image so every school
 * can use the same professional layout with its own logo, name, contacts and
 * students. Blood group is deliberately not part of this template because
 * Skulag does not collect it for the ID-card workflow.
 */
class IdCardPdfService
{
    protected const CARD_WIDTH_PT = 242.65; // 85.6mm / CR80
    protected const CARD_HEIGHT_PT = 153.07; // 54mm / CR80
    protected const CARDS_PER_SHEET = 8; // 2 x 4 on A4

    public function buildSingle(int $studentId, string $template = 'default')
    {
        $student = Student::with('schoolClass', 'school')->findOrFail($studentId);

        return Pdf::loadView('id-cards.single', [
            'card' => $this->cardData($student, $template),
        ])
            // Do not pass 'landscape' here. The array is already landscape
            // shaped; DomPDF swaps it again when a second orientation is given.
            ->setPaper([0, 0, self::CARD_WIDTH_PT, self::CARD_HEIGHT_PT]);
    }

    /**
     * @param Collection<int, Student> $students
     */
    public function buildPrintSheet(Collection $students, string $template = 'default')
    {
        $cards = $students->map(fn (Student $student) => $this->cardData($student, $template));

        return Pdf::loadView('id-cards.print-sheet', [
            'cardChunks' => $cards->chunk(self::CARDS_PER_SHEET),
        ])->setPaper('a4', 'portrait');
    }

    protected function cardData(Student $student, string $template): array
    {
        $verifyUrl = route('id-card.verify', ['token' => $student->qr_token]);

        $qrDataUri = (new Builder(
            writer: new PngWriter(),
            data: $verifyUrl,
            size: 260,
            margin: 2,
        ))->build()->getDataUri();

        $photoDataUri = $this->imageDataUriFromDisks(
            'student photo',
            $student->photo_path ?? null,
            ['private', 'public'],
            700,
            57 / 59,
        );

        $logoDataUri = $this->imageDataUriFromDisks(
            'school logo',
            $student->school->logo_path ?? null,
            ['public'],
            450,
        );

        $signatureDataUri = $this->imageDataUriFromDisks(
            'principal signature',
            $student->school->principal_signature_path ?? null,
            ['public'],
            500,
        );

        $currentSession = AcademicSession::where('school_id', $student->school_id)
            ->where('is_current', true)
            ->first();

        return [
            'template' => $template,
            'student' => $student,
            'school' => $student->school,
            'school_name_display' => Str::limit(trim((string) $student->school->name), 42, '…'),
            'student_name_display' => Str::limit(trim((string) $student->full_name), 30, '…'),
            'class_name' => $student->schoolClass?->full_name ?? '—',
            'session_name' => $currentSession?->name,
            'qr_data_uri' => $qrDataUri,
            'photo_data_uri' => $photoDataUri,
            'logo_data_uri' => $logoDataUri,
            'signature_data_uri' => $signatureDataUri,
            'issued_on' => now()->format('M Y'),
            'address_short' => Str::limit(trim((string) $student->school->address), 70, '…'),
            'contact_short' => Str::limit(trim((string) ($student->school->phone ?: $student->school->email)), 30, '…'),
        ];
    }

    /**
     * Reads the image locally so DomPDF never has to make a production HTTP
     * request back to the application. Student photos support both private
     * and legacy public storage; signatures/logos remain public.
     *
     * The original bytes are used as a fallback. When GD is available the
     * image is resized and normalized to PNG first, which makes large phone
     * photographs much more reliable inside repeated/bulk PDF renders.
     */
    protected function imageDataUriFromDisks(string $label, ?string $path, array $diskNames, int $maxDimension, ?float $targetRatio = null): ?string
    {
        if (! $path) {
            return null;
        }

        foreach ($diskNames as $diskName) {
            try {
                $disk = Storage::disk($diskName);

                if (! $disk->exists($path)) {
                    continue;
                }

                $bytes = $disk->get($path);
                if ($bytes === '' || $bytes === null) {
                    continue;
                }

                $resized = $this->resizeImageBytes($bytes, $maxDimension, $label, $targetRatio);
                if ($resized) {
                    return $resized;
                }

                $mime = $this->detectImageMime($bytes, $path);
                return 'data:'.$mime.';base64,'.base64_encode($bytes);
            } catch (\Throwable $e) {
                Log::warning('Could not read ID card image', [
                    'label' => $label,
                    'disk' => $diskName,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    protected function resizeImageBytes(string $bytes, int $maxDimension, string $label, ?float $targetRatio = null): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        try {
            $originalWidth = imagesx($source);
            $originalHeight = imagesy($source);

            $cropX = 0;
            $cropY = 0;
            $cropWidth = $originalWidth;
            $cropHeight = $originalHeight;

            // Student photos are cropped to the same portrait ratio as the
            // card's photo window. This avoids relying on DomPDF's support
            // for CSS object-fit and guarantees the face/photo fills the box.
            if ($targetRatio && $targetRatio > 0) {
                $sourceRatio = $originalWidth / max(1, $originalHeight);
                if ($sourceRatio > $targetRatio) {
                    $cropWidth = (int) round($originalHeight * $targetRatio);
                    $cropX = max(0, (int) floor(($originalWidth - $cropWidth) / 2));
                } elseif ($sourceRatio < $targetRatio) {
                    $cropHeight = (int) round($originalWidth / $targetRatio);
                    $cropY = max(0, (int) floor(($originalHeight - $cropHeight) / 2));
                }
            }

            $scale = min(1, $maxDimension / max($cropWidth, $cropHeight));
            $targetWidth = max(1, (int) round($cropWidth * $scale));
            $targetHeight = max(1, (int) round($cropHeight * $scale));

            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
            imagecopyresampled(
                $resized,
                $source,
                0,
                0,
                $cropX,
                $cropY,
                $targetWidth,
                $targetHeight,
                $cropWidth,
                $cropHeight,
            );

            ob_start();
            imagepng($resized, null, 6);
            $pngBytes = ob_get_clean();

            imagedestroy($source);
            imagedestroy($resized);

            return $pngBytes ? 'data:image/png;base64,'.base64_encode($pngBytes) : null;
        } catch (\Throwable $e) {
            Log::warning('Could not resize an ID card image', [
                'label' => $label,
                'error' => $e->getMessage(),
            ]);

            imagedestroy($source);
            return null;
        }
    }

    protected function detectImageMime(string $bytes, string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_buffer($finfo, $bytes) : false;
            if ($finfo) {
                finfo_close($finfo);
            }
            if (is_string($mime) && str_starts_with($mime, 'image/')) {
                return $mime;
            }
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}
