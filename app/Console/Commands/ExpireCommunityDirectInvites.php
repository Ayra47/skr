<?php

namespace App\Console\Commands;

use App\Models\CommunityDirectInvite;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('communities:expire-direct-invites')]
#[Description('Expire pending direct community invitations')]
class ExpireCommunityDirectInvites extends Command
{
    public function handle(): int
    {
        $expired = CommunityDirectInvite::query()
            ->where('status', CommunityDirectInvite::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => CommunityDirectInvite::STATUS_EXPIRED,
                'responded_at' => now(),
                'updated_at' => now(),
            ]);

        $this->info("Expired {$expired} direct invite(s).");

        return self::SUCCESS;
    }
}
