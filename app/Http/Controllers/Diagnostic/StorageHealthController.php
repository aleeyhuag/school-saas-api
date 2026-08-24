<?php

namespace App\Http\Controllers\Diagnostic;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * Stage 55 hotfix — read-only production diagnostic for the "images
 * disappear" bug report.
 *
 * Static code review found two things that are DEFINITELY fixed by
 * this hotfix (a stale public/storage directory shadowing the real
 * symlink, and entrypoint.sh not being self-healing about it) but
 * could not find a code-level reason logos/signatures would still go
 * missing a few hours after upload, since they're already served
 * through MediaController reading disks directly rather than the
 * symlink. The next most likely explanation is that Render's
 * persistent disk mount either isn't actually attached the way
 * render.yaml describes, or the running container was never
 * redeployed after that config was fixed in an earlier round --
 * render.yaml changes to an EXISTING disk's mountPath do not always
 * auto-apply; Render can require confirming the change in the
 * dashboard. This route reports hard evidence instead of asking you
 * to guess.
 *
 * Deliberately super_admin-gated (see routes/api-platform.php) rather
 * than public -- an earlier "diagnostic" route in this project's
 * history was committed and then reverted, most plausibly for being
 * left open. Safe to keep permanently since it exposes no secrets,
 * only filesystem facts, and requires platform-owner auth.
 */
class StorageHealthController extends Controller
{
    public function __invoke()
    {
        $publicDisk = Storage::disk('public');
        $privateDisk = Storage::disk('private');

        $publicRoot = storage_path('app/public');
        $privateRoot = storage_path('app/private');
        $webLink = public_path('storage');

        // Write-and-read a small canary file on each disk right now.
        // If this route is hit again in a few hours and the canary
        // from THIS run is gone, that is direct proof of the
        // ephemeral-filesystem/disk-mount problem rather than
        // anything application-code related.
        $canaryName = 'diagnostic-canary.txt';
        $canaryBody = 'written_at='.now()->toIso8601String();

        try {
            $publicDisk->put('diagnostics/'.$canaryName, $canaryBody);
        } catch (\Throwable $e) {
            // ignore -- reported via exists() check below
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),

            'symlink' => [
                'public/storage_path' => $webLink,
                'exists' => file_exists($webLink),
                'is_symlink' => is_link($webLink),
                'resolves_to' => is_link($webLink) ? readlink($webLink) : null,
                'points_at_correct_target' => is_link($webLink)
                    && realpath($webLink) === realpath($publicRoot),
            ],

            'disks' => [
                'public' => [
                    'root' => $publicRoot,
                    'writable' => is_writable($publicRoot),
                    'canary_written_this_request' => $publicDisk->exists('diagnostics/'.$canaryName),
                    'school_logos_count' => count($publicDisk->files('school-logos')),
                    'school_signatures_count' => count($publicDisk->files('school-signatures')),
                    'newest_file_mtime' => $this->newestMtime($publicDisk, ['school-logos', 'school-signatures']),
                ],
                'private' => [
                    'root' => $privateRoot,
                    'writable' => is_writable($privateRoot),
                    'student_photos_count' => count($privateDisk->files('student-photos')),
                    'newest_file_mtime' => $this->newestMtime($privateDisk, ['student-photos']),
                ],
            ],

            'mount_hint' => [
                'note' => 'If public.writable or private.writable is false, or the file '
                    .'counts above are unexpectedly 0 despite schools having uploaded '
                    .'logos/photos, the persistent disk is most likely not actually '
                    .'mounted at /var/www/html/storage/app on the live Render service. '
                    .'Check Render dashboard -> service -> Disks, confirm the mount path '
                    .'matches render.yaml exactly, and redeploy if it was only just fixed.',
            ],

            'how_to_use' => 'Call this endpoint now, then again in a few hours. If '
                .'disks.public.canary_written_this_request flips from true to false '
                .'(or the file counts drop), files are being wiped by the platform, not '
                .'the application. If counts and canary stay stable but the frontend '
                .'still shows broken images, the problem is in how the URL is being '
                .'requested/cached, not storage.',
        ]);
    }

    protected function newestMtime($disk, array $directories): ?string
    {
        $latest = null;

        foreach ($directories as $dir) {
            foreach ($disk->files($dir) as $file) {
                $mtime = $disk->lastModified($file);
                if ($latest === null || $mtime > $latest) {
                    $latest = $mtime;
                }
            }
        }

        return $latest ? date('c', $latest) : null;
    }
}
