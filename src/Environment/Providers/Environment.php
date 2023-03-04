<?php

declare(strict_types=1);

namespace Bead\Environment\Providers;

use Bead\Contracts\Environment as EnvironmentContract;

class Environment implements EnvironmentContract
{
    public function has(string $key): bool
    {
        return '' !== $this->get($key);
    }

    public function get(string $key): string
    {
        return (string) getenv($key);
    }
}
