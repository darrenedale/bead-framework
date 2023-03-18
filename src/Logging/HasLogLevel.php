<?php

namespace Bead\Logging;

use Bead\Contracts\Logger as LoggerContract;

/**
 * Trait for default implementation of the level() and setLevel() methods of the Logger contract.
 */
trait HasLogLevel
{
    private int $level = LoggerContract::InformationLevel;

    /**
     * Fetch the current log level.
     *
     * Messages above the current level should not be logged.
     *
     * @return int The level.
     */
    public function level(): int
    {
        return $this->level;
    }

    /**
     * Set the log level.
     *
     * Messages above the current level should not be logged. The level is not validated - the caller is responsible for
     * ensuring a sensible level is passed.
     *
     * @param int $level The log level.
     */
    public function setLevel(int $level): void
    {
        $this->level = $level;
    }
}
