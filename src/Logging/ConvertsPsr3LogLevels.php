<?php

namespace Bead\Logging;

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
            LogLevel::EMERGENCY => self::EmergencyLevel,
            LogLevel::ALERT => self::AlertLevel,
            LogLevel::CRITICAL => self::CriticalLevel,
            LogLevel::ERROR => self::ErrorLevel,
            LogLevel::WARNING => self::WarningLevel,
            LogLevel::NOTICE => self::NoticeLevel,
            LogLevel::INFO => self::InformationLevel,
            LogLevel::DEBUG => self::DebugLevel,
            default => throw new LoggerException("Unrecognised log level {$level}."),
        };
    }
}
