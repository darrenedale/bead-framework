<?php

namespace Bead\Logging;

use Bead\Contracts\Logger as LoggerContract;

trait HasLogLevel
{
    private int $level = LoggerContract::InformationLevel;

    public function level(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }
}
