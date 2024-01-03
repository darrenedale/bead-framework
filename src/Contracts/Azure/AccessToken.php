<?php

namespace Bead\Contracts\Azure;

use DateTimeInterface;

abstract class AccessToken implements Authorisation
{
    abstract public function token(): string;

    abstract public function type(): string;

    abstract public function notBefore(): int;

    abstract public function notBeforeDateTime(): DateTimeInterface;

    abstract public function expiresOn(): int;

    abstract public function expiresOnDateTime(): DateTimeInterface;

    abstract public function resource(): string;

    public function hasExpired(): bool
    {
        $time = time();
        return $this->notBefore() >= $time || $this->expiresOn() <= $time;
    }
}
