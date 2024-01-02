<?php

declare(strict_types=1);

namespace Bead\Contracts;

interface Hashable
{
    public function hash(): string;
}
