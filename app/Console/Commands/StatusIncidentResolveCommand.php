<?php

namespace App\Console\Commands;

use App\Models\StatusIncident;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('status:incident:resolve
    {id? : Incident ID to resolve (omit to pick from list)}
    {--duration= : Duration in minutes (auto-computed from started_at if omitted)}
    {--body=     : Postmortem / resolution note to append}
')]
#[Description('Mark an ongoing incident as resolved')]
class StatusIncidentResolveCommand extends Command
{
    public function handle(): int
    {
        $ongoing = StatusIncident::where('status', 'ongoing')
            ->orderByDesc('started_at')
            ->get();

        if ($ongoing->isEmpty()) {
            $this->info('Нет активных инцидентов.');

            return self::SUCCESS;
        }

        $id = $this->argument('id');

        if (! $id) {
            $choices = $ongoing->mapWithKeys(fn ($i) => [
                $i->id => "#{$i->id}  [{$i->component_id}]  {$i->title}",
            ])->toArray();

            $selected = $this->choice('Выберите инцидент для закрытия', $choices);
            $id = (int) explode(' ', ltrim($selected, '#'))[0];
        }

        $incident = StatusIncident::where('id', $id)
            ->where('status', 'ongoing')
            ->first();

        if (! $incident) {
            $this->error("Активный инцидент #{$id} не найден.");

            return self::FAILURE;
        }

        $duration = $this->option('duration')
            ? (int) $this->option('duration')
            : (int) $incident->started_at->diffInMinutes(now());

        $body = $this->option('body');
        if (! $body) {
            $body = $this->ask('Postmortem / комментарий (Enter чтобы пропустить)');
        }

        $incident->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'duration_minutes' => $duration,
            'body' => $body ?: $incident->body,
        ]);

        $this->info("Инцидент #{$incident->id} закрыт.");
        $this->line("  Длительность: {$incident->formattedDuration()}");

        return self::SUCCESS;
    }
}
