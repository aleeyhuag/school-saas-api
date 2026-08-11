<?php

namespace App\Http\Controllers\Api\Media;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;

/**
 * Serves uploaded files (school logos, payment proof-of-payment
 * images) directly through Laravel instead of relying on the
 * public/storage symlink + the webserver serving it statically.
 *
 * Why this exists: `php artisan storage:link` creates a real
 * symlink on Linux/macOS, but on Windows (this project's local dev
 * setup is XAMPP on Windows) it needs Developer Mode enabled or an
 * elevated terminal — if that's missing, the command either fails
 * outright or silently produces a broken/empty link. When that
 * happens every logo_url / proof_url the API returns is a perfectly
 * correctly-built URL that 404s the moment a browser actually
 * requests it — which looks exactly like "the image is corrupted".
 * Streaming the file through a normal Laravel route sidesteps the
 * symlink (and any APP_URL/subpath mismatch with Apache's docroot)
 * entirely: if the API is reachable at all, these routes work.
 */
class MediaController extends Controller
{
    /**
     * Public — school logos are shown on the public landing page and
     * the login screen, before anyone is authenticated.
     *
     * $path is the exact value stored in schools.logo_path, e.g.
     * "school-logos/xyz.png" — restricted below to that one
     * directory so this can't be used to read arbitrary files off
     * the public disk.
     */
    public function logo(string $path)
    {
        return $this->stream($path, 'school-logos/');
    }

    /**
     * NOT public — a payment proof is a bank transfer screenshot,
     * arguably sensitive. Reached only via a signed URL (see
     * Payment::getProofUrlAttribute()), which expires and can't be
     * guessed or reused past its window, rather than a permanently
     * public path — closes off the same file being sitting at a
     * static, guessable /storage/payment-proofs/... URL forever.
     */
    public function paymentProof(Payment $payment)
    {
        if (! request()->hasValidSignature()) {
            abort(403, 'This link has expired.');
        }

        if (! $payment->proof_path) {
            abort(404);
        }

        return $this->stream($payment->proof_path, 'payment-proofs/');
    }

    private function stream(string $path, string $requiredPrefix)
    {
        // Guard against path traversal / reading outside the
        // intended folder — normalize and enforce the prefix rather
        // than trusting the stored value blindly.
        $normalized = str_replace('\\', '/', $path);

        if (! str_starts_with($normalized, $requiredPrefix) || str_contains($normalized, '..')) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($normalized)) {
            abort(404);
        }

        return $disk->response($normalized);
    }
}
