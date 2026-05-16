<?php

namespace App\Console\Commands;

use App\Models\StatusIncident;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('status:incident:open
    {--component= : Component ID (feed, chat, comm, keys, push, media)}
    {--kind=warn  : Severity: info, warn, crit}
    {--title=     : Short incident title}
    {--body=      : Detailed description}
')]
#[Description('Open a new ongoing incident for a service component')]
class StatusIncidentOpenCommand extends Command
{
    private const COMPONENTS = ['feed', 'chat', 'comm', 'keys', 'push', 'media'];

    private const KINDS = ['info', 'warn', 'crit'];

    public function handle(): int
    {
        $component = $this->option('component') ?? $this->choice(
            'Компонент',
            self::COMPONENTS,
        );

        if (! in_array($component, self::COMPONENTS)) {
            $this->error("Неизвестный компонент: {$component}");

            return self::FAILURE;
        }

        $kind = $this->option('kind');
        if (! in_array($kind, self::KINDS)) {
            $kind = $this->choice('Severity', self::KINDS, 1);
        }

        $title = $this->option('title') ?? $this->ask('Заголовок инцидента');
        if (! $title) {
            $this->error('Заголовок обязателен.');

            return self::FAILURE;
        }

        $body = $this->option('body') ?? $this->ask('Описание (Enter чтобы пропустить)');

        $incident = StatusIncident::create([
            'component_id' => $component,
            'kind' => $kind,
            'status' => 'ongoing',
            'title' => $title,
            'body' => $body ?: null,
            'started_at' => now(),
        ]);

        $this->info("Инцидент #{$incident->id} открыт.");
        $this->line("  Компонент : {$component}");
        $this->line("  Severity  : {$kind}");
        $this->line("  Заголовок : {$title}");

        return self::SUCCESS;
    }
}
