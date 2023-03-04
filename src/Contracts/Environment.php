<?php

declare(strict_types=1);

namespace Bead\Contracts;

interface Environment
{
    public function has(string $key): bool;

    public function get(string $key): string;
}
