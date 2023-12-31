<?php

declare(strict_types=1);

namespace Bead\Contracts\Queues;

interface Message
{
    public function payload(): string;

    public function property(string $key): mixed;
}
