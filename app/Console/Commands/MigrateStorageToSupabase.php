<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-time cutover command for the image persistence hotfix. Copies
 * every file currently on Render's local disk (storage/app/public,
 * storage/app/private) up to the new Supabase-backed 'public'/'private'
 * disks, preserving the exact same relative paths -- so every existing
 * `logo_path` / `principal_signature_path` / `photo_path` value already
 * in the database keeps working unchanged, no DB migration needed.
 *
 * Safe to run more than once: existing files on the destination are
 * skipped if their size already matches (not deleted, not re-uploaded).
 * Does NOT delete anything from the local disk -- that's a deliberate,
 * separate, manual step for later, once you've confirmed the migration
 * is solid and things have been stable for a while.
 */
class MigrateStorageToSupabase extends Command
{
    protected $signature = 'storage:migrate-to-supabase
        {--dry-run : List what would be copied without actually copying anything}';

    protected $description = 'Copy existing local uploads (logos, signatures, student photos, payment proofs) to Supabase Storage';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Dry run -- nothing will actually be copied.');
        }

        $failures = 0;

        $failures += $this->migrateDisk('legacy_public', 'public', $dryRun);
        $this->newLine();
        $failures += $this->migrateDisk('legacy_private', 'private', $dryRun);

        $this->newLine();
        if ($failures > 0) {
            $this->error("Migration completed with $failures failed file(s). Local files were NOT deleted. Fix the failures and re-run the command.");
            return self::FAILURE;
        }

        $this->info('Done. Local files were NOT deleted -- verify things '
            .'look right (open a few logos/signatures/photos in the app) '
            .'before considering cleanup of the old local files.');

        return self::SUCCESS;
    }

    protected function migrateDisk(string $fromDiskName, string $toDiskName, bool $dryRun): int
    {
        $source = Storage::disk($fromDiskName);
        $dest = Storage::disk($toDiskName);

        $files = $source->allFiles();
        $total = count($files);

        $this->info("[$fromDiskName -> $toDiskName] $total file(s) found locally.");

        if ($total === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $copied = 0;
        $skipped = 0;
        $failed = [];

        foreach ($files as $path) {
            $stream = null;

            try {
                $sourceSize = $source->size($path);
                $destinationExists = $dest->exists($path);

                if ($destinationExists && $dest->size($path) === $sourceSize) {
                    if (! $dryRun && $this->sameContents($source, $dest, $path)) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    if ($dryRun) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }
                }

                if (! $dryRun) {
                    $stream = $source->readStream($path);

                    if (! is_resource($stream)) {
                        throw new \RuntimeException('Could not open a read stream for this file.');
                    }

                    if (! $dest->put($path, $stream)) {
                        throw new \RuntimeException('Destination storage rejected the upload.');
                    }

                    if (! $dest->exists($path) || $dest->size($path) !== $sourceSize) {
                        throw new \RuntimeException('Upload verification failed: destination size does not match source.');
                    }

                    if (! $this->sameContents($source, $dest, $path)) {
                        throw new \RuntimeException('Upload verification failed: SHA-256 content hash does not match.');
                    }
                }

                $copied++;
            } catch (\Throwable $e) {
                $failed[] = "$path — {$e->getMessage()}";
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $verb = $dryRun ? 'would copy' : 'copied';
        $this->line("  $verb: $copied, already present (skipped): $skipped, failed: ".count($failed));

        foreach ($failed as $failure) {
            $this->error("  FAILED: $failure");
        }

        return count($failed);
    }

    protected function hashStream($stream): string
    {
        $context = hash_init('sha256');

        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) {
                throw new \RuntimeException('Could not read stream during content verification.');
            }
            if ($chunk !== '') {
                hash_update($context, $chunk);
            }
        }

        return hash_final($context);
    }

    protected function sameContents($source, $dest, string $path): bool
    {
        $sourceStream = $source->readStream($path);
        $destStream = $dest->readStream($path);

        if (! is_resource($sourceStream) || ! is_resource($destStream)) {
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }
            if (is_resource($destStream)) {
                fclose($destStream);
            }

            throw new \RuntimeException('Could not open streams for content verification.');
        }

        try {
            return $this->hashStream($sourceStream) === $this->hashStream($destStream);
        } finally {
            fclose($sourceStream);
            fclose($destStream);
        }
    }
}
