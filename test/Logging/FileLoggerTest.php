<?php

namespace BeadTests\Logging;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Exceptions\Logging\FileLoggerException;
use Bead\Logging\FileLogger;
use BeadTests\Framework\TestCase;
use SplFileObject;

final class FileLoggerTest extends TestCase
{
    private const TestLogFileName = "FileLoggerTest.log";

    private const TestAltLogFileName = "FileLoggerAlt.log";

    private const TestMessage = "Test message: %1";

    private const TestContext = ["test-context",];

    private FileLogger $logger;

    private static function logFilePathName(string $fileName = self::TestLogFileName): string
    {
        return self::tempDir() . "/{$fileName}";
    }

    public function setUp(): void
    {
        @mkdir($this->tempDir(), recursive: true);
        $this->logger = new FileLogger(self::logFilePathName());
    }

    public function tearDown(): void
    {
        unset ($this->logger);
        @unlink(self::logFilePathName());
        @unlink(self::logFilePathName(self::TestAltLogFileName));
        @rmdir($this->tempDir());
        parent::tearDown();
    }

    /** Expect a given string to be written to the log file. */
    private function expectLogFileWrite(string $data): void
    {
        $this->mockMethod(SplFileObject::class, "fwrite", fn(string $str) => TestCase::assertEquals($data, $str));
    }

    /** Expect no string to be written to the log file. */
    private function expectNoLogFileWrite(): void
    {
        $this->mockMethod(SplFileObject::class, "fwrite", fn(string $str) => TestCase::fail("Log message should not have been written."));
    }

    /** Ensure log() writes the expected message to the file. */
    public function testLog(): void
    {
        $this->expectLogFileWrite(str_replace("%1", self::TestContext[0], self::TestMessage) . "\n");
        $this->logger->log(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext);
    }

    /** Ensure log() ignores messages above the current log level. */
    public function testLogIgnores(): void
    {
        $this->logger->setLevel(LoggerContract::InformationLevel);
        $this->expectNoLogFileWrite();
        $this->logger->log(LoggerContract::DebugLevel, self::TestMessage, self::TestContext);
        self::assertTrue(true);
    }

    /** Ensure the constructor can open a file in append mode. */
    public function testConstructorAppends(): void
    {
        file_put_contents(self::logFilePathName(self::TestAltLogFileName), "pre-existing content\n");
        $logger = new FileLogger(self::logFilePathName(self::TestAltLogFileName), FileLogger::FlagAppend);
        $logger->log(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext);
        unset($logger);
        self::assertEquals(
            "pre-existing content\n" . str_replace("%1", self::TestContext[0], self::TestMessage) . "\n",
            file_get_contents(self::logFilePathName(self::TestAltLogFileName))
        );
    }

    /** Ensure the constructor can open a file in overwrite mode. */
    public function testConstructorOverwrites(): void
    {
        file_put_contents(self::logFilePathName(self::TestAltLogFileName), "pre-existing content\n");
        $logger = new FileLogger(self::logFilePathName(self::TestAltLogFileName), FileLogger::FlagOverwrite);
        $logger->log(LoggerContract::EmergencyLevel, self::TestMessage, self::TestContext);
        unset($logger);
        self::assertEquals(
            str_replace("%1", self::TestContext[0], self::TestMessage) . "\n",
            file_get_contents(self::logFilePathName(self::TestAltLogFileName))
        );
    }

    /** Ensure the constructor throws when writing to an exixting file with neither the append nor overwrite flag. */
    public function testConstructorWontOverwrite(): void
    {
        file_put_contents(self::logFilePathName(self::TestAltLogFileName), "pre-existing content\n");
        self::expectException(FileLoggerException::class);
        self::expectExceptionMessageMatches("#^Failed to open log file " . self::logFilePathName(self::TestAltLogFileName) . " for writing: .*\$#");
        $logger = new FileLogger(self::logFilePathName(self::TestAltLogFileName));
    }

    /** Ensure the logger returns the correct file path. */
    public function testFileName()
    {
        self::assertEquals(self::logFilePathName(), $this->logger->fileName());
    }
}
