<?php

namespace App\Http\Controllers\Diagnostic;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Production storage diagnostic for Super Admins.
 *
 * This endpoint deliberately returns operational facts rather than file
 * contents: disk roots, directory state, symlink state and a write/read/delete
 * probe for the public/private upload disks. It is protected by the
 * super_admin route middleware and is safe to use when diagnosing Render
 * persistent-disk issues.
 */
class StorageHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $publicRoot = storage_path('app/public');
        $privateRoot = storage_path('app/private');
        $linkPath = public_path('storage');
        $target = realpath($linkPath) ?: null;

        $disks = [
            'public' => $this->inspectDisk('public', $publicRoot),
            'private' => $this->inspectDisk('private', $privateRoot),
        ];

        return response()->json([
            'status' => collect($disks)->every(fn (array $disk) => $disk['ok']) ? 'ok' : 'degraded',
            'checked_at' => now()->toIso8601String(),
            'storage' => [
                'app_path' => storage_path('app'),
                'app_exists' => is_dir(storage_path('app')),
                'app_writable' => is_writable(storage_path('app')),
            ],
            'public_symlink' => [
                'path' => $linkPath,
                'exists' => file_exists($linkPath) || is_link($linkPath),
                'is_symlink' => is_link($linkPath),
                'target' => $target,
                'target_matches_public_disk' => $target === realpath($publicRoot),
            ],
            'disks' => $disks,
        ]);
    }

    protected function inspectDisk(string $diskName, string $root): array
    {
        $result = [
            'root' => $root,
            'exists' => is_dir($root),
            'writable' => is_dir($root) && is_writable($root),
            'probe' => null,
            'ok' => false,
        ];

        if (! $result['exists'] || ! $result['writable']) {
            return $result;
        }

        $relative = 'diagnostics/.storage-health-'.Str::uuid().'.txt';
        $payload = 'skulag-storage-health:'.now()->timestamp;

        try {
            $disk = Storage::disk($diskName);
            $write = $disk->put($relative, $payload);
            $read = $write ? $disk->get($relative) : null;
            $delete = $write ? $disk->delete($relative) : false;

            $result['probe'] = [
                'write' => $write,
                'read' => $read === $payload,
                'delete' => $delete,
            ];
            $result['ok'] = $write && $read === $payload && $delete;
        } catch (Throwable $e) {
            $result['probe'] = [
                'write' => false,
                'read' => false,
                'delete' => false,
                'error' => $e->getMessage(),
            ];
        }

        return $result;
    }
}
