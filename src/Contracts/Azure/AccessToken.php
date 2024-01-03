<?php

namespace Bead\Contracts\Azure;

use DateTimeInterface;

interface AccessToken
{
    public function token(): string;

    public function type(): string;

    public function notBefore(): int;

    public function notBeforeDateTime(): DateTimeInterface;
    public function expiresOn(): int;

    public function expiresOnDateTime(): DateTimeInterface;

    public function resource(): string;

    /** @return array<string,string> */
    public function headers(): array;
}
