<?php

namespace Bead\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Exceptions\Logging\LoggerException;
use Psr\Log\LogLevel;

trait ConvertsPsr3LogLevels
{
    private static function convertLogLevel(int | string | Stringable $level): int
    {
        if (is_int($level)) {
            return $level;
        }

        return match((string) $level) {
            LogLevel::EMERGENCY => LoggerContract::EmergencyLevel,
            LogLevel::ALERT => LoggerContract::AlertLevel,
            LogLevel::CRITICAL => LoggerContract::CriticalLevel,
            LogLevel::ERROR => LoggerContract::ErrorLevel,
            LogLevel::WARNING => LoggerContract::WarningLevel,
            LogLevel::NOTICE => LoggerContract::NoticeLevel,
            LogLevel::INFO => LoggerContract::InformationLevel,
            LogLevel::DEBUG => LoggerContract::DebugLevel,
            default => throw new LoggerException("Unrecognised log level {$level}."),
        };
    }
}
