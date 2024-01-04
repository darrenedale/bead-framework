<?php

declare(strict_types=1);

namespace Bead\Queues\Azure\RestCommands;

trait DoesntHaveBody
{
    public function body(): string
    {
        return "";
    }
}
