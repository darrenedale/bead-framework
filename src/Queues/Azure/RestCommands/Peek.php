<?php

declare(strict_types=1);

namespace Bead\Queues\Azure\RestCommands;

use Bead\Queues\Azure\RestCommands\AbstractFetch;

class Peek extends AbstractFetch
{
    public function method(): string
    {
        return "POST";
    }
}
