<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A tiny "something changed" ping on the family's private channel. It carries no
 * data on purpose: clients answer it with an Inertia partial reload, so
 * permissions and visibility rules are always applied by the normal controllers.
 */
class FamilyDataChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public int $familyId,
        public string $resource,
        public ?int $actorId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('family.'.$this->familyId)];
    }

    public function broadcastAs(): string
    {
        return 'data.changed';
    }

    /** @return array{resource: string, actor: int|null} */
    public function broadcastWith(): array
    {
        return ['resource' => $this->resource, 'actor' => $this->actorId];
    }
}
