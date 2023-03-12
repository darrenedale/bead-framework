<?php

namespace BeadTests\Exceptions\Environment;

use Bead\Exceptions\Environment\EnvironmentFileParseException;
use BeadTests\Framework\TestCase;

class EnvironmentFileParseExceptionTest extends TestCase
{
    /** Ensure the name of the environment file can be fetched. */
    public function testGetEnvironmentFileName()
    {
        $exception = new EnvironmentFileParseException("test/environment/file.env", 42);
        self::assertEquals("test/environment/file.env", $exception->getEnvironmentFileName());
    }

    /** Ensure the line number in the environment file can be fetched. */
    public function testGetEnvironmentFileLineNumber()
    {
        $exception = new EnvironmentFileParseException("test/environment/file.env", 42);
        self::assertEquals(42, $exception->getEnvironmentFileLineNumber());
    }

    /** Ensure the constructor passes on the message, code and previous to the base class. */
    public function testConstructor()
    {
        $previous = new Exception();
        $exception = new EnvironmentFileParseException("test/environment/file.env", 42, "Test EnvironmentFileParseException message.", 12, $previous);
        self::assertEquals("Test EnvironmentFileParseException message.", $exception->getMessage());
        self::assertEquals(12, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());

    }
}
