<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeetingEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $gameId,
        public int $votedOutPlayerId,
        public string $votedOutPlayerName
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('game.'.$this->gameId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'meeting.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'voted_out' => [
                'id' => $this->votedOutPlayerId,
                'name' => $this->votedOutPlayerName,
            ],
        ];
    }
}
