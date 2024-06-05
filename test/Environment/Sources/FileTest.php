<?php

declare(strict_types=1);

namespace BeadTests\Environment\Sources;

use Bead\Environment\Sources\File;
use Bead\Exceptions\EnvironmentException;
use BeadTests\Framework\TestCase;

use function preg_quote;

final class FileTest extends TestCase
{
    /** @var File The test file - a valid .env file. */
    private File $envFile;

    /** Set up the test fixture with a valid test file. */
    public function setUp(): void
    {
        $this->envFile = new File(__DIR__ . "/files/test-01.env");
    }

    public function tearDown(): void
    {
        unset($this->envFile);
        parent::tearDown();
    }

    /** Ensure has() provides the expected results for a valid test file. */
    public function testHas1(): void
    {
        self::assertFalse($this->envFile->has("key"));
        self::assertFalse($this->envFile->has("key_0"));
        self::assertTrue($this->envFile->has("key_1"));
        self::assertTrue($this->envFile->has("key_2"));
        self::assertTrue($this->envFile->has("key_3"));
        self::assertTrue($this->envFile->has("key_4"));
        self::assertTrue($this->envFile->has("key_5"));
        self::assertTrue($this->envFile->has("key_6"));
        self::assertTrue($this->envFile->has("key_7"));
        self::assertTrue($this->envFile->has("key_8"));
        self::assertTrue($this->envFile->has("key_9"));
        self::assertTrue($this->envFile->has("key_a"));
        self::assertTrue($this->envFile->has("key_b"));
        self::assertTrue($this->envFile->has("key_c"));
        self::assertFalse($this->envFile->has("key_10"));
    }

    /** Ensure get() provides the expected results for a valid test file. */
    public function testGet1(): void
    {
        self::assertEquals("", $this->envFile->get("key"));
        self::assertEquals("", $this->envFile->get("key_0"));
        self::assertEquals("value_1", $this->envFile->get("key_1"));
        self::assertEquals("value_2", $this->envFile->get("key_2"));
        self::assertEquals("value_3", $this->envFile->get("key_3"));
        self::assertEquals("value_4", $this->envFile->get("key_4"));
        self::assertEquals("value_5", $this->envFile->get("key_5"));
        self::assertEquals("value_6", $this->envFile->get("key_6"));
        self::assertEquals(" value_7 ", $this->envFile->get("key_7"));
        self::assertEquals(" value_8 ", $this->envFile->get("key_8"));
        self::assertEquals("", $this->envFile->get("key_9"));
        self::assertEquals("", $this->envFile->get("key_a"));
        self::assertEquals("", $this->envFile->get("key_b"));
        self::assertEquals("a value with an = in it", $this->envFile->get("key_c"));
        self::assertEquals("", $this->envFile->get("key_10"));
    }

    /** Ensure we can fetch the filename. */
    public function testFileName1(): void
    {
        self::assertEquals(__DIR__ . "/files/test-01.env", $this->envFile->fileName());
    }

    /** Ensure an unreadable file throws the expected exception. */
    public function testConstructor1(): void
    {
        self::expectException(EnvironmentException::class);
        self::expectExceptionMessageMatches("/^Failed to read env file '" . preg_quote(__DIR__ . "/files/does-not-exist.env", "/") . "': /");
        new File(__DIR__ . "/files/does-not-exist.env");
    }

    /** Ensure a file with a non-empty, non-comment line with no assignment throws the expected exception. */
    public function testConstructor2(): void
    {
        self::expectException(EnvironmentException::class);
        self::expectExceptionMessage("Invalid declaration at line 5 in '" . __DIR__ . "/files/test-invalid-line.env'.");
        new File(__DIR__ . "/files/test-invalid-line.env");
    }

    /** Ensure a file with an invalid variable name throws the expected exception. */
    public function testConstructor3(): void
    {
        self::expectException(EnvironmentException::class);
        self::expectExceptionMessage("Invalid variable name '2_key' at line 5 in '" . __DIR__ . "/files/test-invalid-name.env'.");
        new File(__DIR__ . "/files/test-invalid-name.env");
    }

    /** Ensure a file with an invalid variable name throws the expected exception. */
    public function testConstructor4(): void
    {
        self::expectException(EnvironmentException::class);
        self::expectExceptionMessage("Variable name 'key_1' at line 5 has been defined previously in '" . __DIR__ . "/files/test-duplicate-name.env" . "'.");
        new File(__DIR__ . "/files/test-duplicate-name.env");
    }

    /** Ensure names() returns the expected variable names. */
    public function testNames1(): void
    {
        self::assertEquals(
            [
                "key_1",
                "key_2",
                "key_3",
                "key_4",
                "key_5",
                "key_6",
                "key_7",
                "key_8",
                "key_9",
                "key_a",
                "key_b",
                "key_c",
            ],
            $this->envFile->names()
        );
    }

    /** Ensure all() returns the expected variables. */
    public function testAll1(): void
    {
        self::assertEquals(
            [
                "key_1" => "value_1",
                "key_2" => "value_2",
                "key_3" => "value_3",
                "key_4" => "value_4",
                "key_5" => "value_5",
                "key_6" => "value_6",
                "key_7" => " value_7 ",
                "key_8" => " value_8 ",
                "key_9" => "",
                "key_a" => "",
                "key_b" => "",
                "key_c" => "a value with an = in it",
            ],
            $this->envFile->all()
        );
    }
}
