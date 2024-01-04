<?php

declare(strict_types=1);

namespace Bead\Queues\Azure\RestCommands;

trait HasNoHeaders
{
    public function headers(): array
    {
        return [];
    }
}
