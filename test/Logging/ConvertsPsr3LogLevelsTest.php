<?php

namespace BeadTests\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Exceptions\Logging\LoggerException;
use Bead\Logging\ConvertsPsr3LogLevels;
use Bead\Testing\StaticXRay;
use BeadTests\Framework\TestCase;
use Psr\Log\LogLevel;
use Stringable;

final class ConvertsPsr3LogLevelsTest extends TestCase
{
    /** @var ConvertsPsr3LogLevels A test instance of the trait. */
    private mixed $instance;

    public function setUp(): void
    {
        $this->instance = new class
        {
            use ConvertsPsr3LogLevels;
        };
    }

    public function tearDown(): void
    {
        unset ($this->instance);
        parent::tearDown();
    }

    /**
     * Helper to create a Stringable instance that converts to a given string.
     *
     * @param string $string The string the Stringable converts to.
     *
     * @return Stringable The Stringable instance.
     */
    private static function createStringable(string $string): Stringable
    {
        return new class($string) implements Stringable
        {
            private string $string;
            
            public function __construct(string $string)
            {
                $this->string = $string;
            }
            
            public function __toString(): string
            {
                return $this->string;
            }
        };
    }

    /**
     * Test data for testConvertLogLevel.
     *
     * @return iterable The test data.
     */
    public function dataForTestConvertLogLevel(): iterable
    {
        yield "psr3EmergencyString" => [LogLevel::EMERGENCY, LoggerContract::EmergencyLevel,];
        yield "psr3AlertString" => [LogLevel::ALERT, LoggerContract::AlertLevel,];
        yield "psr3CriticalString" => [LogLevel::CRITICAL, LoggerContract::CriticalLevel,];
        yield "psr3ErrorString" => [LogLevel::ERROR, LoggerContract::ErrorLevel,];
        yield "psr3WarningString" => [LogLevel::WARNING, LoggerContract::WarningLevel,];
        yield "psr3NoticeString" => [LogLevel::NOTICE, LoggerContract::NoticeLevel,];
        yield "psr3InformationString" => [LogLevel::INFO, LoggerContract::InformationLevel,];
        yield "psr3DebugString" => [LogLevel::DEBUG, LoggerContract::DebugLevel,];

        yield "psr3EmergencyStringable" => [self::createStringable(LogLevel::EMERGENCY), LoggerContract::EmergencyLevel,];
        yield "psr3AlertStringable" => [self::createStringable(LogLevel::ALERT), LoggerContract::AlertLevel,];
        yield "psr3CriticalStringable" => [self::createStringable(LogLevel::CRITICAL), LoggerContract::CriticalLevel,];
        yield "psr3ErrorStringable" => [self::createStringable(LogLevel::ERROR), LoggerContract::ErrorLevel,];
        yield "psr3WarningStringable" => [self::createStringable(LogLevel::WARNING), LoggerContract::WarningLevel,];
        yield "psr3NoticeStringable" => [self::createStringable(LogLevel::NOTICE), LoggerContract::NoticeLevel,];
        yield "psr3InformationStringable" => [self::createStringable(LogLevel::INFO), LoggerContract::InformationLevel,];
        yield "psr3DebugStringable" => [self::createStringable(LogLevel::DEBUG), LoggerContract::DebugLevel,];
        
        yield "beadEmergencyInt" => [LoggerContract::EmergencyLevel, LoggerContract::EmergencyLevel,];
        yield "beadAlertInt" => [LoggerContract::AlertLevel, LoggerContract::AlertLevel,];
        yield "beadCriticalInt" => [LoggerContract::CriticalLevel, LoggerContract::CriticalLevel,];
        yield "beadErrorInt" => [LoggerContract::ErrorLevel, LoggerContract::ErrorLevel,];
        yield "beadWarningInt" => [LoggerContract::WarningLevel, LoggerContract::WarningLevel,];
        yield "beadNoticeInt" => [LoggerContract::NoticeLevel, LoggerContract::NoticeLevel,];
        yield "beadInformationInt" => [LoggerContract::InformationLevel, LoggerContract::InformationLevel,];
        yield "beadDebugInt" => [LoggerContract::DebugLevel, LoggerContract::DebugLevel,];
    }

    /**
     * Ensure all valid log levels can be converted.
     *
     * @dataProvider dataForTestConvertLogLevel
     *
     * @param int|string|Stringable $level The log level to test with.
     * @param int $expected The expecvted Bead log level.
     */
    public function testConvertLogLevel(int | string | Stringable $level, int $expected): void
    {
        $instance = new StaticXRay(get_class($this->instance));
        self::assertEquals($expected, $instance->convertLogLevel($level));
    }

    /** Ensure unrecognised log levels trigger an exception. */
    public function testConvertLogLevelThrows(): void
    {
        $instance = new StaticXRay(get_class($this->instance));
        self::expectException(LoggerException::class);
        self::expectExceptionMessage("Unrecognised log level foo.");
        $instance->convertLogLevel("foo");
    }
}
