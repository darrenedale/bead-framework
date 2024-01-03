<?php

declare(strict_types=1);

namespace Bead\Contracts\Azure;

interface Authorisation
{
    public function hasExpired(): bool;

    /** @return array<string,string> */
    public function headers(): array;
}
