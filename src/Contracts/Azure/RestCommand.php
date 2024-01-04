<?php

declare(strict_types=1);

namespace Bead\Contracts\Azure;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

interface RestCommand
{
    /** @return array<string,string> */
    public function headers(): array;

    public function uri(): string;

    public function method(): string;

    public function body(): string|StreamInterface;

    public function parseResponse(ResponseInterface $response): mixed;
}
