<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes temporary uploads (from /uploadfile) older than a threshold so the
 * private temp dir doesn't grow unbounded. Scheduled daily; safe to run by hand.
 */
class PruneTempUploads extends Command
{
    protected $signature = 'temp:prune {--hours=48 : Delete temp files older than this many hours}';
    protected $description = 'Delete stale files from storage/app/private/temp (uploaded via /uploadfile)';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $disk = Storage::disk('local');
        $cutoff = now()->subHours($hours)->getTimestamp();

        if (! $disk->exists('temp')) {
            $this->info('No temp directory — nothing to prune.');
            return self::SUCCESS;
        }

        $deleted = 0; $bytes = 0;
        foreach ($disk->files('temp') as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $bytes += $disk->size($path);
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->info("Pruned {$deleted} temp file(s) older than {$hours}h (" . round($bytes / 1048576, 1) . " MB freed).");
        return self::SUCCESS;
    }
}
