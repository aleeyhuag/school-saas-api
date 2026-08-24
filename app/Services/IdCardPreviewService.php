<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Student;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Str;

/**
 * Browser ID-card data source.
 *
 * The browser is now the only ID-card renderer. This service deliberately
 * returns normal image URLs (and an SVG QR data URI) instead of embedding
 * images into a PDF, so the exact same HTML/CSS that the user sees is what
 * the browser prints.
 */
class IdCardPreviewService
{
    public function forStudent(Student $student): array
    {
        $school = $student->school;
        $currentSession = AcademicSession::where('school_id', $student->school_id)
            ->where('is_current', true)
            ->first();

        $verifyUrl = route('id-card.verify', ['token' => $student->qr_token]);
        $qrDataUri = $this->qrDataUri($verifyUrl);

        return [
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'admission_number' => $student->admission_number,
                'date_of_birth' => $student->date_of_birth?->format('d M Y'),
                'guardian_name' => $student->guardian_name,
                'guardian_phone' => $student->guardian_phone,
                'photo_url' => $student->photo_url,
            ],
            'school' => [
                'name' => $school->name,
                'address' => trim((string) $school->address),
                'phone' => trim((string) $school->phone),
                'logo_url' => $school->logo_url,
                'principal_signature_url' => $school->principal_signature_url,
            ],
            'class_name' => $student->schoolClass?->full_name ?? '—',
            'session_name' => $currentSession?->name,
            'qr_data_uri' => $qrDataUri,
            'issued_on' => now()->format('M Y'),
            'address_short' => Str::limit(trim((string) $school->address), 70, '…'),
            'contact_short' => Str::limit(trim((string) ($school->phone ?: $school->email)), 30, '…'),
        ];
    }

    /**
     * Render the QR matrix directly into a tiny SVG string. This deliberately
     * avoids GD, Imagick, SimpleXML and XMLWriter: the old PDF pipeline failed
     * in production environments when one of those native extensions was
     * missing. The SVG is plain text and the browser renders it natively.
     */
    protected function qrDataUri(string $data): string
    {
        $matrix = Encoder::encode($data, ErrorCorrectionLevel::M())->getMatrix();
        $size = $matrix->getWidth();
        $margin = 4;
        $viewSize = $size + ($margin * 2);
        $path = '';

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix->get($x, $y)) {
                    $path .= 'M'.($x + $margin).' '.($y + $margin).'h1v1h-1z';
                }
            }
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" shape-rendering="crispEdges"><rect width="100%%" height="100%%" fill="#fff"/><path d="%2$s" fill="#000"/></svg>',
            $viewSize,
            $path,
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
