<?php

namespace Bead\Contracts;

use Bead\Exceptions\Logging\LoggerClosedException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Interface for services that log messages.
 */
interface Logger extends LoggerInterface
{
    public const EmergencyLevel = 0;

    public const AlertLevel = 1;

    public const CriticalLevel  = 2;

    public const ErrorLevel     = 3;

    public const WarningLevel   = 4;

    public const NoticeLevel    = 5;

    public const InformationLevel      = 6;

    public const DebugLevel     = 7;

    /**
     * Fetch the current logging level.
     *
     * @return int The level.
     */
    public function level(): int;

    /**
     * Set the current logging level.
     *
     * @param int $level The new logging level.
     */
    public function setLevel(int $level): void;
}
