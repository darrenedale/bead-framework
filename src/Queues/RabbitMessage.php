<?php

declare(strict_types=1);

namespace Bead\Queues;

class RabbitMessage extends Message
{
    private int $id;
    private ?int $channelId;

    public function __construct(int $id, ?int $channelId, string $payload)
    {
        parent::__construct($payload);
        $this->id = $id;
        $this->channelId = $channelId;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function channelId(): ?int
    {
        return $this->channelId;
    }
}
