<?php

namespace App\Console\Commands;

use App\Models\WarrantCanary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('status:canary')]
#[Description('Publish a new warrant canary entry (marks previous as not current)')]
class StatusCanaryCommand extends Command
{
    public function handle(): int
    {
        $canary = WarrantCanary::makeCurrent();

        $this->info("Warrant canary published: {$canary->signature}");
        $this->line("Published at: {$canary->published_at->toDateTimeString()}");

        return self::SUCCESS;
    }
}
