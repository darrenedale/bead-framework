<?php

declare(strict_types=1);

namespace Bead\Queues;

class RabbitMessage extends Message
{
    private int $id;

    public function __construct(int $id, string $payload)
    {
        parent::__construct($payload);
        $this->id = $id;
    }

    public function id(): int
    {
        return $this->id;
    }
}
