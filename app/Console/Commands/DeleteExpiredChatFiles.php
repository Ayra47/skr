<?php

namespace App\Console\Commands;

use App\Models\ChatFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('chat:delete-expired-files')]
#[Description('Delete expired chat file uploads from disk')]
class DeleteExpiredChatFiles extends Command
{
    public function handle(): int
    {
        $expired = ChatFile::where('expires_at', '<=', now())->get();
        $deleted = 0;

        foreach ($expired as $file) {
            Storage::disk('local')->delete($file->storage_path);
            $file->delete();
            $deleted++;
        }

        $this->info("Deleted {$deleted} expired chat file(s).");

        return self::SUCCESS;
    }
}
