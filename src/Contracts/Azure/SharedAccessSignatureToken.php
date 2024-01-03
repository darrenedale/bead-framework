<?php

declare(strict_types=1);

namespace Bead\Contracts\Azure;

abstract class SharedAccessSignatureToken implements Authorisation
{
    abstract public function service(): string;

    abstract public function keyName(): string;

    abstract public function key(): string;

    abstract public function expiry(): int;

    public function hasExpired(): bool
    {
        return time() >= $this->expiry();
    }
}
