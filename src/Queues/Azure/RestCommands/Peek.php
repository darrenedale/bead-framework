<?php

declare(strict_types=1);

namespace Bead\Queues\Azure\RestCommands;

class Peek extends AbstractFetch
{
    public function method(): string
    {
        return "POST";
    }
}
