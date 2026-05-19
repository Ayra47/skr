<?php

namespace App\Events;

use App\Models\Poll;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PollUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Poll $poll) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("poll.{$this->poll->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'poll.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $total = $this->poll->totalVotes();

        return [
            'poll_id' => $this->poll->id,
            'total_votes' => $total,
            'options' => $this->poll->options->map(fn ($opt) => [
                'id' => $opt->id,
                'votes_count' => $opt->votes_count,
                'percentage' => $opt->percentage($total),
            ])->all(),
        ];
    }
}
