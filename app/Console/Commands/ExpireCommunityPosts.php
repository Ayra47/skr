<?php

namespace App\Console\Commands;

use App\Models\CommunityPost;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('communities:expire-posts {--chunk=100 : Records per chunk}')]
#[Description('Soft-delete expired community posts and their projections')]
class ExpireCommunityPosts extends Command
{
    public function handle(): int
    {
        $expired = 0;
        $chunk = max(1, (int) $this->option('chunk'));

        CommunityPost::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById($chunk, function ($posts) use (&$expired): void {
                foreach ($posts as $post) {
                    $post->delete();
                    $expired++;
                }
            });

        $this->info("Expired {$expired} community post(s).");

        return self::SUCCESS;
    }
}
