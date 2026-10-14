<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BodyReported implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $gameId,
        public int $bodyId,
        public string $bodyName,
        public int $reporterId,
        public string $reporterName
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('game.'.$this->gameId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'body.reported';
    }

    public function broadcastWith(): array
    {
        return [
            'body' => [
                'id' => $this->bodyId,
                'name' => $this->bodyName,
            ],

            'reporter' => [
                'id' => $this->reporterId,
                'name' => $this->reporterName,
            ],
        ];
    }
}
