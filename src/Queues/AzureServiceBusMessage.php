<?php

declare(strict_types=1);

namespace Bead\Queues;

class AzureServiceBusMessage extends Message
{
    private string $id;

    private string $lockToken;

    public function __construct(string $id, string $lockToken, string $payload)
    {
        parent::__construct($payload);
        $this->id = $id;
        $this->lockToken = $lockToken;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function lockToken(): string
    {
        return $this->lockToken;
    }
}
