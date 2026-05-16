<?php

namespace App\Http\Controllers;

use App\Models\StatusIncident;
use App\Models\WarrantCanary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function index(): View
    {
        $components = collect(config('status.components'));
        $incidents90 = StatusIncident::where('started_at', '>=', now()->subDays(90))
            ->orderByDesc('started_at')
            ->get();

        $components = $components->map(function (array $c) use ($incidents90) {
            $componentIncidents = $incidents90->where('component_id', $c['id']);

            $downtimeMinutes = $componentIncidents
                ->where('status', 'resolved')
                ->sum('duration_minutes');

            $totalMinutes = 90 * 24 * 60;
            $uptime = $totalMinutes > 0
                ? round(max(0, ($totalMinutes - $downtimeMinutes) / $totalMinutes * 100), 2)
                : 100.0;

            $hasOngoing = $componentIncidents->where('status', 'ongoing')->isNotEmpty();
            $hasCrit = $componentIncidents
                ->where('status', 'ongoing')
                ->where('kind', 'crit')
                ->isNotEmpty();

            $ongoingNote = $componentIncidents
                ->where('status', 'ongoing')
                ->first()?->title;

            $status = 'ok';
            if ($hasCrit) {
                $status = 'crit';
            } elseif ($hasOngoing) {
                $status = 'warn';
            }

            return array_merge($c, [
                'uptime' => $uptime,
                'status' => $status,
                'note' => $ongoingNote,
            ]);
        });

        $allOk = $components->every(fn ($c) => $c['status'] === 'ok');
        $overallUptime = $components->avg('uptime');

        $allRecentIncidents = $incidents90
            ->where('started_at', '>=', now()->subDays(30))
            ->sortByDesc('started_at')
            ->values();

        $recentIncidents = $allRecentIncidents->take(5);
        $hasMoreIncidents = $allRecentIncidents->count() > 5;

        $canaryHistory = WarrantCanary::orderByDesc('published_at')->take(5)->get();
        $currentCanary = $canaryHistory->firstWhere('is_current', true) ?? $canaryHistory->first();
        $hasMoreCanary = WarrantCanary::count() > 5;

        $canaryStale = $currentCanary ? $currentCanary->isStale() : true;

        $dayBars = $this->buildDayBars($incidents90, $components->pluck('id')->toArray());

        return view('pages.status.index', compact(
            'components',
            'allOk',
            'overallUptime',
            'recentIncidents',
            'hasMoreIncidents',
            'canaryHistory',
            'hasMoreCanary',
            'currentCanary',
            'canaryStale',
            'dayBars',
        ));
    }

    public function moreCanary(Request $request): JsonResponse
    {
        $offset = max(0, (int) $request->query('offset', 5));

        $items = WarrantCanary::orderByDesc('published_at')
            ->skip($offset)
            ->take(10)
            ->get();

        $total = WarrantCanary::count();

        return response()->json([
            'items' => $items->map(fn ($h) => [
                'date' => $h->published_at->translatedFormat('j M Y'),
                'signature' => $h->signature,
                'is_current' => $h->is_current,
            ]),
            'has_more' => ($offset + 10) < $total,
        ]);
    }

    public function moreIncidents(Request $request): JsonResponse
    {
        $offset = max(0, (int) $request->query('offset', 5));

        $incidents = StatusIncident::where('started_at', '>=', now()->subDays(30))
            ->orderByDesc('started_at')
            ->skip($offset)
            ->take(5)
            ->get();

        $total = StatusIncident::where('started_at', '>=', now()->subDays(30))->count();

        return response()->json([
            'incidents' => $incidents->map(fn ($inc) => [
                'id' => $inc->id,
                'title' => $inc->title,
                'kind' => $inc->kind,
                'status' => $inc->status,
                'body' => $inc->body,
                'date' => $inc->started_at->translatedFormat('j M Y'),
                'duration' => $inc->formattedDuration(),
            ]),
            'has_more' => ($offset + 5) < $total,
        ]);
    }

    /**
     * Build a 90-day bar array keyed by component id.
     * Each entry is an array of 90 strings: 'ok', 'warn', or 'crit'.
     */
    private function buildDayBars(Collection $incidents, array $componentIds): array
    {
        $bars = [];

        foreach ($componentIds as $id) {
            $bars[$id] = array_fill(0, 90, 'ok');
        }

        foreach ($incidents as $inc) {
            if (! isset($bars[$inc->component_id])) {
                continue;
            }

            $startDay = (int) $inc->started_at->diffInDays(now());
            $startDay = min($startDay, 89);
            $barIndex = 89 - $startDay;

            if ($barIndex >= 0 && $barIndex < 90) {
                $existing = $bars[$inc->component_id][$barIndex];
                if ($inc->kind === 'crit' || ($inc->kind === 'warn' && $existing === 'ok')) {
                    $bars[$inc->component_id][$barIndex] = $inc->kind;
                }
            }
        }

        return $bars;
    }
}
