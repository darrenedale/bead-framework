<?php

declare(strict_types=1);

namespace Bead\Queues\Azure\RestCommands;

class Receive extends AbstractFetch
{
    public function method(): string
    {
        return "DELETE";
    }
}
