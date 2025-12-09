<?php

namespace BeadTests\Web;

use Bead\Exceptions\Web\UploadedFileException;
use Bead\Web\UploadedFile;
use BeadTests\Framework\TestCase;
use LogicException;

use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

class UploadedFileTest extends TestCase
{
    /** Ensure we can create a file. */
    public function testCreate1(): void
    {
        $file = UploadedFile::create("upload", "text/plain", "/tmp/uploaded-file.txt", 42, UPLOAD_ERR_OK);
        self::assertSame("upload", $file->name());
        self::assertSame("text/plain", $file->mediaType());
        self::assertSame("/tmp/uploaded-file.txt", $file->path());
        self::assertSame(42, $file->reportedSize());
        self::assertSame(UPLOAD_ERR_OK, $file->error());
    }

    /** Ensure the default error code is as expected. */
    public function testCreate2(): void
    {
        $file = UploadedFile::create("upload", "text/plain", "/tmp/uploaded-file.txt", 42);
        self::assertSame(UPLOAD_ERR_OK, $file->error());
    }

    /** Ensure we can create a file from a $_FILES superglobal entry. */
    public function testFromFilesArray1(): void
    {
        $file = UploadedFile::fromFilesArray([
            "name" => "upload",
            "type" => "text/plain",
            "tmp_name" => "/tmp/uploaded-file.txt",
            "size" => 42,
            "error" => UPLOAD_ERR_OK,
        ]);
        self::assertSame("upload", $file->name());
        self::assertSame("text/plain", $file->mediaType());
        self::assertSame("/tmp/uploaded-file.txt", $file->path());
        self::assertSame(42, $file->reportedSize());
        self::assertSame(UPLOAD_ERR_OK, $file->error());
    }

    /** Ensure isValid() correctly reports valid files. */
    public function testIsValid1(): void
    {
        $file = UploadedFile::create("upload", "text/plain", "/tmp/uploaded-file.txt", 42, UPLOAD_ERR_OK);
        self::assertTrue($file->isValid());
    }

    /** Provides upload error codes that indicate failure. */
    public static function providerInvalidErrorCodes(): iterable
    {
        yield [UPLOAD_ERR_CANT_WRITE];
        yield [UPLOAD_ERR_EXTENSION];
        yield [UPLOAD_ERR_FORM_SIZE];
        yield [UPLOAD_ERR_INI_SIZE];
        yield [UPLOAD_ERR_NO_FILE];
        yield [UPLOAD_ERR_NO_TMP_DIR];
        yield [UPLOAD_ERR_PARTIAL];
    }

    /**
     * Ensure isValid() correctly reports files with non-success error codes.
     *
     * @dataProvider providerInvalidErrorCodes
     */
    public function testIsValid2(int $errorCode): void
    {
        $file = UploadedFile::create("upload", "text/plain", "/tmp/uploaded-file.txt", 42, $errorCode);
        self::assertFalse($file->isValid());
    }

    /** Ensure a file that has been moved is reported as invalid. */
    public function testIsValid3(): void
    {
        $file = UploadedFile::create("upload", "text/plain", "/tmp/uploaded-file.txt", 42);
        $this->mockFunction("move_uploaded_file", true);
        $file->moveTo("/tmp/moved-uploaded-file.txt");
        self::assertFalse($file->isValid());
    }

    /** Ensure a file can be moved and we get the expected SplFileInfo object. */
    public function testMoveTo1(): void
    {
        $this->mockFunction("move_uploaded_file", static function (string $tempPath, string $destination): bool {
            TestCase::assertSame("/tmp/uploaded-file.txt", $tempPath);
            TestCase::assertSame("/tmp/moved-uploaded-file.txt", $destination);
            return true;
        });

        $file = UploadedFile::create("upload", "text/plain", "/tmp/uploaded-file.txt", 42);
        $actual = $file->moveTo("/tmp/moved-uploaded-file.txt");
        self::assertSame("/tmp/moved-uploaded-file.txt", $actual->getPathname());
    }

    /** Ensure we get the expected exception when attempting to move an invalid file. */
    public function testMoveTo2(): void
    {
        $file = UploadedFile::create("upload", "text/plain", "/tmp/uploaded-file.txt", 42, UPLOAD_ERR_CANT_WRITE);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The uploaded file \"upload\" is not valid and cannot be moved");
        $actual = $file->moveTo("/tmp/moved-uploaded-file.txt");
    }

    /** Ensure we get the expected exception when attempting to move a file fails. */
    public function testMoveTo3(): void
    {
        $this->mockFunction("move_uploaded_file", static function (string $tempPath, string $destination): bool {
            TestCase::assertSame("/tmp/uploaded-file.txt", $tempPath);
            TestCase::assertSame("/tmp/moved-uploaded-file.txt", $destination);
            return false;
        });

        $file = UploadedFile::create("upload", "text/plain", "/tmp/uploaded-file.txt", 42, UPLOAD_ERR_OK);
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage("The file \"upload\" could not be moved to \"/tmp/moved-uploaded-file.txt\"");
        $actual = $file->moveTo("/tmp/moved-uploaded-file.txt");
    }
}
