<?php

declare(strict_types=1);

namespace Bead\Queues;

class BeanstalkMessage extends Message
{
    private string $id;

    public function __construct(string $id, string $payload)
    {
        parent::__construct($payload);
        $this->id = $id;
    }

    public function id(): string
    {
        return $this->id;
    }
}
