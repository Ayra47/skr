<?php

namespace App\Console\Commands;

use App\Models\CommunityFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('communities:cleanup-file-blobs {--limit=500 : Maximum files to process}')]
#[Description('Delete soft-deleted community file blobs and mark blob_deleted_at')]
class CleanupCommunityFileBlobs extends Command
{
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;

        CommunityFile::withTrashed()
            ->whereNotNull('deleted_at')
            ->whereNull('blob_deleted_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (CommunityFile $file) use (&$processed): void {
                $storageKey = $file->storage_key ?: $file->path;

                if (filled($storageKey)) {
                    Storage::disk('local')->delete($storageKey);
                }

                $file->forceFill(['blob_deleted_at' => now()])->save();
                $processed++;
            });

        $this->info("Cleaned {$processed} community file blob(s).");

        return self::SUCCESS;
    }
}
