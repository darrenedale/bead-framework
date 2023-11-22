<?php

declare(strict_types=1);

namespace BeadTests\Environment\Sources;

use Bead\Environment\Sources\File;
use Bead\Exceptions\Environment\Exception as EnvironmentException;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;

final class FileTest extends TestCase
{
    /** @var File The test file - a valid .env file. */
    private File $envFile;

    /** Set up the test fixture with a valid test file. */
    public function setUp(): void
    {
        $this->envFile = new File(__DIR__ . "/files/test-01.env");
    }

    /** Ensure has() provides the expected results for a valid test file. */
    public function testHas()
    {
        self::assertFalse($this->envFile->has('key'));
        self::assertFalse($this->envFile->has('key_0'));
        self::assertTrue($this->envFile->has('key_1'));
        self::assertTrue($this->envFile->has('key_2'));
        self::assertTrue($this->envFile->has('key_3'));
        self::assertTrue($this->envFile->has('key_4'));
        self::assertTrue($this->envFile->has('key_5'));
        self::assertTrue($this->envFile->has('key_6'));
        self::assertTrue($this->envFile->has('key_7'));
        self::assertTrue($this->envFile->has('key_8'));
        self::assertTrue($this->envFile->has('key_9'));
        self::assertTrue($this->envFile->has('key_a'));
        self::assertTrue($this->envFile->has('key_b'));
        self::assertTrue($this->envFile->has('key_c'));
        self::assertFalse($this->envFile->has('key_10'));
    }

    /** Ensure get() provides the expected results for a valid test file. */
    public function testGet()
    {
        self::assertEquals("", $this->envFile->get('key'));
        self::assertEquals("", $this->envFile->get('key_0'));
        self::assertEquals("value_1", $this->envFile->get('key_1'));
        self::assertEquals("value_2", $this->envFile->get('key_2'));
        self::assertEquals("value_3", $this->envFile->get('key_3'));
        self::assertEquals("value_4", $this->envFile->get('key_4'));
        self::assertEquals("value_5", $this->envFile->get('key_5'));
        self::assertEquals("value_6", $this->envFile->get('key_6'));
        self::assertEquals(" value_7 ", $this->envFile->get('key_7'));
        self::assertEquals(" value_8 ", $this->envFile->get('key_8'));
        self::assertEquals("", $this->envFile->get('key_9'));
        self::assertEquals("", $this->envFile->get('key_a'));
        self::assertEquals("", $this->envFile->get('key_b'));
        self::assertEquals("a value with an = in it", $this->envFile->get('key_c'));
        self::assertEquals("", $this->envFile->get('key_10'));
    }

    /** Ensure we can fetch the filename. */
    public function testFileName()
    {
        self::assertEquals(__DIR__ . "/files/test-01.env", $this->envFile->fileName());
    }

    /** Ensure an unreadable file throws the expected exception. */
    public function testParseThrowsWithUnreadableFile(): void
    {
        $envFile = new XRay(new File(__DIR__ . "/files/does-not-exist.env"));
        self::expectException(EnvironmentException::class);
        self::expectExceptionMessageMatches("/^Failed to read env file '" . preg_quote($envFile->fileName(), "/") . "': /");
        $envFile->parse();
    }

    /** Ensure a file with a non-empty, non-comment line with no assignment throws the expected exception. */
    public function testParseThrowsWithInvalidLine(): void
    {
        $envFile = new XRay(new File(__DIR__ . "/files/test-invalid-line.env"));
        self::expectException(EnvironmentException::class);
        self::expectExceptionMessage("Invalid declaration at line 5 in '{$envFile->fileName()}'.");
        $envFile->parse();
    }

    /** Ensure a file with an invalid variable name throws the expected exception. */
    public function testParseThrowsWithInvalidName(): void
    {
        $envFile = new XRay(new File(__DIR__ . "/files/test-invalid-name.env"));
        self::expectException(EnvironmentException::class);
        self::expectExceptionMessage("Invalid varaible name '2_key' at line 5 in '{$envFile->fileName()}'.");
        $envFile->parse();
    }

    /** Ensure a file with an invalid variable name throws the expected exception. */
    public function testParseThrowsWithDuplicateName(): void
    {
        $envFile = new XRay(new File(__DIR__ . "/files/test-duplicate-name.env"));
        self::expectException(EnvironmentException::class);
        self::expectExceptionMessage("Varaible name 'key_1' at line 5 has been defined previously in '{$envFile->fileName()}'.");
        $envFile->parse();
    }
}
