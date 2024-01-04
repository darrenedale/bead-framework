<?php

declare(strict_types=1);

namespace Bead\Queues\Azure\RestCommands;

trait HasNoBody
{
    public function body(): string
    {
        return "";
    }
}
