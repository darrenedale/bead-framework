<?php

declare(strict_types=1);

namespace BeadTests;

use Bead\AppLog;
use Bead\Exceptions\UploadedFileException;
use BeadTests\Framework\TestCase;
use Bead\UploadedFile;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use SplFileInfo;

use function uopz_get_mock;
use function uopz_set_mock;
use function uopz_unset_mock;
use function uopz_get_return;
use function uopz_set_return;
use function uopz_unset_return;

class UploadedFileTest extends TestCase
{
    public const TempFileName = "/tmp/uploaded-file.txt";
    public const DestinationFileName = "/var/www/uploads/uploaded-file.txt";
    public const TempFileSize = 2048;

    private array $m_callCounts = [];

    public static final function tempFileContents(): string
    {
        static $content = null;

        if (!isset($content)) {
            $content = random_bytes(self::TempFileSize);
        }

        return $content;
    }

    public function setUp(): void
    {
        $this->m_callCounts = [];

        // ensure these classes are loaded by the autoloader before we mock the filesystem functions
        new UploadedFileException();
        new SplFileInfo("");
    }

    public function tearDown(): void
    {
        $this->m_callCounts = [];
        $this->removeFilesystemFunctionMocks();
    }

    private static function createFileMap(array $details): array
    {
        return [
            "name" => $details["name"] ?? self::TempFileName,
            "type" => $details["type"] ?? "application/octet-stream",
            "size" => $details["size"] ?? 0,
            "tmp_name" => $details["tmp_name"] ?? self::TempFileName,
            "error" => $details["error"] ?? 0,
            "full_path" => $details["full_path"] ?? "",
        ];
    }

    private static function createUploadedFile(array $file): UploadedFile
    {
        static $class = null;
        static $constructor = null;

        if (!isset($class)) {
            $class = new ReflectionClass(UploadedFile::class);
            $constructor = $class->getConstructor();
            $constructor->setAccessible(true);
        }

        /** @var UploadedFile $instance */
        $instance = $class->newInstanceWithoutConstructor();
        $constructor->invoke($instance, $file);
        return $instance;
    }

    private function mockFilesystemFunctions(): void
    {
        $callCounts =& $this->m_callCounts;

        uopz_set_return("file_exists", function(string $file) use (&$callCounts): bool
        {
            if ($file === UploadedFileTest::TempFileName) {
                $callCounts["file_exists"] = ($callCounts["file_exists"] ?? 0) + 1;
                return true;
            }

            UploadedFileTest::fail("file_exists() called with unexpected file name '{$file}'.");
        }, true);

        uopz_set_return("is_file", function(string $file) use (&$callCounts): bool
        {
            if ($file === UploadedFileTest::TempFileName) {
                $callCounts["is_file"] = ($callCounts["is_file"] ?? 0) + 1;
                return true;
            }

            UploadedFileTest::fail("is_file() called with unexpected file name '{$file}'.");
        }, true);

        uopz_set_return("is_readable", function(string $file) use (&$callCounts): bool
        {
            if ($file === UploadedFileTest::TempFileName) {
                $callCounts["is_readable"] = ($callCounts["is_readable"] ?? 0) + 1;
                return true;
            }

            UploadedFileTest::fail("is_readable() called with unexpected file name '{$file}'.");
        }, true);

        uopz_set_return("file_get_contents", function(string $file) use (&$callCounts): string
        {
            if ($file === UploadedFileTest::TempFileName) {
                $callCounts["file_get_contents"] = ($callCounts["file_get_contents"] ?? 0) + 1;
                return UploadedFileTest::tempFileContents();
            }

            UploadedFileTest::fail("file_get_contents() called with unexpected file name '{$file}'.");
        }, true);

        uopz_set_return("move_uploaded_file",  function(string $file, string $destination) use (&$callCounts): bool
        {
            if (UploadedFileTest::TempFileName === $file && UploadedFileTest::DestinationFileName === $destination) {
                $callCounts["move_uploaded_file"] = ($callCounts["move_uploaded_file"] ?? 0) + 1;
                return true;
            }

            UploadedFileTest::fail("move_uploaded_file() called with unexpected temp file name and destination file name combination.");
        }, true);

        uopz_set_return("unlink",  function(string $file) use (&$callCounts): bool
        {
            if ($file === UploadedFileTest::TempFileName) {
                $callCounts["unlink"] = ($callCounts["unlink"] ?? 0) + 1;
                return true;
            }

            UploadedFileTest::fail("unlink() called with unexpected file name.");
        }, true);
    }

    private function removeFilesystemFunctionMocks(): void
    {
        foreach (["file_exists", "file_get_contents", "is_readable", "is_file",] as $functionName) {
            if (uopz_get_return($functionName)) {
                uopz_unset_return($functionName);
            }
        }

        if (uopz_get_mock(SplFileInfo::class)) {
            uopz_unset_mock(SplFileInfo::class);
        }
    }

    /**
     * Ensure allUploadedFiles() returnes the expected set of UploadedFile instances.
     */
    public function testAllUploadedFiles(): void
    {
        $_FILES = [
            [
                "name" => "file-1.txt",
                "type" => "application/octet-stream",
                "size" => 1024,
                "tmp_name" => "/tmp/file-1.txt",
                "error" => 0,
                "full_path" => "/home/user/file1.txt",
            ],
            [
                "name" => "file-2.json",
                "type" => "application/json",
                "size" => 123,
                "tmp_name" => "/tmp/file-2.json",
                "error" => 0,
                "full_path" => "/home/user/my-json.json",
            ],
        ];

        $uploadedFiles = UploadedFile::allUploadedFiles();
        self::assertCount(2, $uploadedFiles);

        foreach ($_FILES as $file) {
            foreach ($uploadedFiles as $uploadedFile) {
                if ($file["name"] === $uploadedFile->name() &&
                    $file["type"] === $uploadedFile->mediaType() &&
                    $file["size"] === $uploadedFile->reportedSize() &&
                    $file["tmp_name"] === $uploadedFile->tempFile() &&
                    $file["error"] === $uploadedFile->errorCode() &&
                    $file["full_path"] === $uploadedFile->clientPath()) {
                    continue 2;
                }
            }

            self::fail("Uploaded file {$_FILE["name"]} not found.");
        }
    }

    /**
     * Test data for testName()
     *
     * @return iterable The test data.
     */
    public function dataForTestName(): iterable
    {
        yield from [
            "typical" => ["foo.txt",],
            "extremeEmpty" => ["",],
        ];
    }

    /**
     * @dataProvider dataForTestName
     * @param string $name The name for the UploadedFile
     */
    public function testName(string $name): void
    {
        $file = self::createUploadedFile(self::createFileMap(["name" => $name,]));
        self::assertSame($name, $file->name());
    }

    /**
     * Test data for testTempFile()
     * @return iterable The test data.
     */
    public function dataForTestTempFile(): iterable
    {
        yield from [
            "typical" => ["/tmp/uploaded-file.txt",],
            "extremeEmpty" => ["",],
        ];
    }

    /**
     * @dataProvider dataForTestTempFile
     * @param string $tempFile The temp file for the UploadedFile
     */
    public function testTempFile(string $tempFile): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => $tempFile,]));
        self::assertSame($tempFile, $file->tempFile());
    }

    /**
     * Test data for testReportedSize()
     * @return iterable The test data.
     */
    public function dataForTestReportedSize(): iterable
    {
        yield from [
            "typical" => [1234,],
            "extremeZero" => [0,],
            "extremeNegative" => [-1234,],
        ];
    }

    /**
     * @dataProvider dataForTestReportedSize
     * @param int $size The size for the UploadedFile
     */
    public function testReportedSize(int $size): void
    {
        $file = self::createUploadedFile(self::createFileMap(["size" => $size,]));
        self::assertSame($size, $file->reportedSize());
    }

    /**
     * Ensure reportedSize() throws when the file has been discarded.
     */
    public function testReportedSizeThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["size" => self::TempFileSize,]));
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->reportedSize();
    }

    /**
     * Test data for testMediaType
     * @return iterable The test data.
     */
    public function dataForTestMediaType(): iterable
    {
        yield from [
            "typical" => ["application/octet-stream",],
            "extremeEmpty" => ["",],
        ];
    }

    /**
     * @dataProvider dataForTestMediaType
     * @param string $type The media type for the UploadedFile
     */
    public function testMediaType(string $type): void
    {
        $file = self::createUploadedFile(self::createFileMap(["type" => $type,]));
        self::assertSame($type, $file->mediaType());
    }

    /**
     * Ensure mediaType() throws when the file has been discarded.
     */
    public function testMediaTypeThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["type" => "text/plain",]));
        $this->mockFilesystemFunctions();
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->mediaType();
    }

    /**
     * Test data for testClientPath()
     *
     * @return iterable The test data.
     */
    public function dataForTestClientPath(): iterable
    {
        yield from [
            "typical" => ["/home/someuser/file.txt",],
            "typicalEmpty" => ["",],
        ];
    }

    /**
     * @dataProvider dataForTestClientPath
     * @param string $path The client path for the UploadedFile
     */
    public function testClientPath(string $path): void
    {
        $file = self::createUploadedFile(self::createFileMap(["full_path" => $path,]));
        self::assertSame($path, $file->clientPath());
    }

    /**
     * Ensure getClientFileName() throws when the file has been discarded.
     */
    public function testClientPathThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["full_path" => '/home/someuser/file.txt',]));
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->clientPath();
    }

    /**
     * Test data for testErrorCode() and testGetErrorCode().
     * @return iterable The test data.
     */
    public function dataForTestErrorCode(): iterable
    {
        yield from [
            "typical" => [0,],
            "typicalNonZero" => [1,],
            "typicalUploadErrFormSize" => [UPLOAD_ERR_FORM_SIZE,],
            "typicalUploadErrPartial" => [UPLOAD_ERR_PARTIAL,],
            "typicalUploadErrNoFile" => [UPLOAD_ERR_NO_FILE,],
            "typicalUploadErrNoTmpDir" => [UPLOAD_ERR_NO_TMP_DIR,],
            "typicalUploadErrCantWrite" => [UPLOAD_ERR_CANT_WRITE,],
            "typicalUploadErrExtension" => [UPLOAD_ERR_EXTENSION,],
            "extremeNegative" => [PHP_INT_MIN,],
            "extremePositive" => [PHP_INT_MAX,],
        ];
    }

    /**
     * @dataProvider dataForTestErrorCode
     * @param int $code The error code for the UploadedFile
     */
    public function testErrorCode(int $code): void
    {
        $file = self::createUploadedFile(self::createFileMap(["error" => $code,]));
        self::assertSame($code, $file->errorCode());
    }

    /**
     * Ensure errorCode() throws when the file has been discarded.
     */
    public function testErrorCodeThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["error" => UPLOAD_ERR_INI_SIZE,]));
        $this->mockFilesystemFunctions();
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->errorCode();
    }

    /**
     * Ensure actualSize() returns the correct size when the file content hasn't been loaded.
     */
    public function testActualSizeFromFile(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => __DIR__ . "/files/uploadedfiletest-file01.txt",]));
        self::assertEquals(34, $file->actualSize());
        self::assertEquals(0, $this->m_callCounts["file_get_contents"] ?? 0);
    }

    /**
     * Ensure actualSize() returns the correct size when the file content has been loaded.
     */
    public function testActualSizeFromLoadedContent(): void
    {
        // test using length of content read from temp file
        $this->mockFilesystemFunctions();
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName,]));
        $file->data();
        self::assertEquals(self::TempFileSize, $file->actualSize());
        self::assertEquals(1, $this->m_callCounts["file_get_contents"]);
        self::assertEquals(self::tempFileContents(), $file->data());
    }

    public function testActualSizeThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName,]));
        self::expectException(UploadedFileException::class);
        $file->actualSize();
    }

    /**
     * Ensure we can successfully fetch the uploaded file data.
     */
    public function testData(): void
    {
        // test successful read
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName,]));
        $this->mockFilesystemFunctions();
        self::assertEquals(self::tempFileContents(), $file->data());
        self::assertEquals(1, $this->m_callCounts["file_get_contents"]);
    }

    /**
     * Ensure an unreadable file causes data() to throw.
     */
    public function testDataWithUnreadableFile(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        self::expectException(UploadedFileException::class);
        uopz_set_return("file_get_contents", false);
        $file->data();
    }

    /**
     * Ensure a successful upload is reported as such.
     */
    public function testWasSuccessful(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        self::assertTrue($file->wasSuccessful());
    }

    /**
     * Ensure an unsuccessful upload is reported as such.
     */
    public function testWasSuccessfulWithErrorCode(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName, "error" => UPLOAD_ERR_INI_SIZE,]));
        self::assertFalse($file->wasSuccessful());
    }

    /**
     * Ensure wasSuccessful() throws when discarded.
     */
    public function testWasSuccessfulThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName,]));
        $this->mockFilesystemFunctions();
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->wasSuccessful();
    }

    /**
     * Ensure discard() invalidates uploaded file.
     */
    public function testDiscard(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        $this->mockFilesystemFunctions();
        self::assertTrue($file->wasSuccessful());
        $file->discard();
        self::assertEquals(1, $this->m_callCounts["unlink"]);
        self::expectException(UploadedFileException::class);
        $file->tempFile();
    }

    /**
     * Ensure discard() throws on subsequent calls.
     */
    public function testDiscardThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        $this->mockFilesystemFunctions();
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->discard();
    }

    /**
     * Ensure moveTo() invalidates uploaded file.
     */
    public function testMoveTo(): void
    {
        // test move invalidates uploaded file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        $this->mockFilesystemFunctions();
        self::assertTrue($file->wasSuccessful());
        $file->moveTo(self::DestinationFileName);
        self::assertEquals(1, $this->m_callCounts["move_uploaded_file"]);
        self::expectException(UploadedFileException::class);
        $file->tempFile();
    }

    /**
     * Ensure moveTo() throws when discarded.
     */
    public function testMoveToThrowsWithDiscarded(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName,]));
        $this->mockFilesystemFunctions();
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->moveTo(self::DestinationFileName);
    }

    /**
     * Ensure moveTo() throws when discarded.
     */
    public function testMoveToThrowsOnFailure(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName,]));
        self::expectException(UploadedFileException::class);
        uopz_set_return("move_uploaded_file",  false);
        $file->moveTo(self::DestinationFileName);
    }

    /**
     * Ensure we can fetch a valid stream.
     */
    public function testGetStream(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => __DIR__ . "/files/uploadedfiletest-file01.txt",]));
        $stream = $file->getStream();
        self::assertInstanceOf(StreamInterface::class, $stream);
        self::assertEquals("This is the uploaded file content.", $stream->getContents());
    }

    /**
     * Ensure we can fetch a valid stream.
     */
    public function testGetStreamThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName,]));
        $this->mockFilesystemFunctions();
        $file->discard();
        self::removeFilesystemFunctionMocks();
        self::expectException(UploadedFileException::class);
        $file->getStream();
    }

    /**
     * @dataProvider dataForTestReportedSize
     * @param int $size The size for the UploadedFile
     */
    public function testGetSize(int $size): void
    {
        $file = self::createUploadedFile(self::createFileMap(["size" => $size,]));
        self::assertSame($size, $file->getSize());
    }

    /**
     * Ensure reportedSize() throws when the file has been discarded.
     */
    public function testGetSizeThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["size" => self::TempFileSize,]));
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->getSize();
    }

    /**
     * @dataProvider dataForTestErrorCode
     * @param int $code The error code for the UploadedFile
     */
    public function testGetError(int $code): void
    {
        $file = self::createUploadedFile(self::createFileMap(["error" => $code,]));
        self::assertSame($code, $file->getError());
    }

    /**
     * Ensure getError() throws when the file has been discarded.
     */
    public function testGetErrorThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["error" => UPLOAD_ERR_CANT_WRITE,]));
        $this->mockFilesystemFunctions();
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->getError();
    }

    /**
     * @dataProvider dataForTestClientPath
     * @param string $path The client path for the UploadedFile
     */
    public function testGetClientFileName(string $path): void
    {
        $file = self::createUploadedFile(self::createFileMap(["full_path" => $path,]));
        self::assertSame($path, $file->getClientFilename());
    }

    /**
     * Ensure getClientFileName() throws when the file has been discarded.
     */
    public function testGetClientFileNameThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["full_path" => '/home/someuser/file.txt',]));
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->getClientFilename();
    }

    /**
     * @dataProvider dataForTestMediaType
     * @param string $type The media type for the UploadedFile
     */
    public function testGetClientMediaType(string $type): void
    {
        $file = self::createUploadedFile(self::createFileMap(["type" => $type,]));
        self::assertSame($type, $file->getClientMediaType());
    }

    /**
     * Ensure mediaType() throws when the file has been discarded.
     */
    public function testGetClientMediaTypeThrows(): void
    {
        $file = self::createUploadedFile(self::createFileMap(["type" => "text/plain",]));
        $this->mockFilesystemFunctions();
        $file->discard();
        self::expectException(UploadedFileException::class);
        $file->getClientMediaType();
    }
}
