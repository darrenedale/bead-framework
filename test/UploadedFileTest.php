<?php

declare(strict_types=1);

namespace BeadTests;

use Bead\Application;
use Bead\Contracts\Logger as LoggerContract;
use Bead\Facades\Log;
use Bead\Logging\NullLogger;
use Bead\UploadedFile;
use BeadTests\Framework\TestCase;
use Mockery;
use ReflectionClass;
use SplFileInfo;

use function uopz_get_mock;
use function uopz_set_mock;
use function uopz_unset_mock;

class UploadedFileTest extends TestCase
{
    public const TempFileName = "/tmp/uploaded-file.txt";

    public const DestinationFileName = "/var/www/uploads/uploaded-file.txt";

    public const TempFileSize = 2048;

    private array $callCounts = [];

    final public static function tempFileContents(): string
    {
        static $content = null;

        if (!isset($content)) {
            $content = random_bytes(self::TempFileSize);
        }

        return $content;
    }

    public function setUp(): void
    {
        $this->callCounts = [];
    }

    public function tearDown(): void
    {
        $this->callCounts = [];
        parent::tearDown();
    }

    private static function createFileMap(array $details): array
    {
        return [
            "name" => $details["name"] ?? "client-file-name.bin",
            "type" => $details["type"] ?? "application/octet-stream",
            "size" => $details["size"] ?? 0,
            "tmp_name" => $details["tmp_name"] ?? "/tmp/uploaded-file.bin",
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

    public function dataForTestMimeType(): iterable
    {
        yield from [
            "typical" => ["application/octet-stream",],
            "extremeEmpty" => ["",],
        ];
    }

    /**
     * @dataProvider dataForTestMimeType
     * @param string $type The MIME type for the UploadedFile
     */
    public function testMimeType(string $type): void
    {
        $file = self::createUploadedFile(self::createFileMap(["type" => $type,]));
        self::assertSame($type, $file->mimeType());
    }

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

    public function dataForTestErrorCode(): iterable
    {
        yield from [
            "typical" => [0,],
            "typicalNonZero" => [1,],
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

    private function mockFilesystemFunctions(): void
    {
        $callCounts =& $this->callCounts;

        $this->mockFunction(
            "file_exists",
            function (string $file) use (&$callCounts): bool {
                if ($file === UploadedFileTest::TempFileName) {
                    $callCounts["file_exists"] = ($callCounts["file_exists"] ?? 0) + 1;
                    return true;
                }

                UploadedFileTest::fail("file_exists() called with unexpected file name '{$file}'.");
            }
        );

        $this->mockFunction(
            "is_file",
            function (string $file) use (&$callCounts): bool {
                if ($file === UploadedFileTest::TempFileName) {
                    $callCounts["is_file"] = ($callCounts["is_file"] ?? 0) + 1;
                    return true;
                }

                UploadedFileTest::fail("is_file() called with unexpected file name '{$file}'.");
            }
        );

        $this->mockFunction(
            "is_readable",
            function (string $file) use (&$callCounts): bool {
                if ($file === UploadedFileTest::TempFileName) {
                    $callCounts["is_readable"] = ($callCounts["is_readable"] ?? 0) + 1;
                    return true;
                }

                UploadedFileTest::fail("is_readable() called with unexpected file name '{$file}'.");
            }
        );

        $this->mockFunction(
            "file_get_contents",
            function (string $file) use (&$callCounts): string {
                if ($file === UploadedFileTest::TempFileName) {
                    $callCounts["file_get_contents"] = ($callCounts["file_get_contents"] ?? 0) + 1;
                    return UploadedFileTest::tempFileContents();
                }

                UploadedFileTest::fail("file_get_contents() called with unexpected file name '{$file}'.");
            }
        );

        $this->mockFunction(
            "move_uploaded_file",
            function (string $file, string $destination) use (&$callCounts): bool {
                if (UploadedFileTest::TempFileName === $file && UploadedFileTest::DestinationFileName === $destination) {
                    $callCounts["move_uploaded_file"] = ($callCounts["move_uploaded_file"] ?? 0) + 1;
                    return true;
                }

                UploadedFileTest::fail("move_uploaded_file() called with unexpected temp file name and destination file name combination.");
            }
        );

        $this->mockFunction(
            "unlink",
            function (string $file) use (&$callCounts): bool {
                if ($file === UploadedFileTest::TempFileName) {
                    $callCounts["unlink"] = ($callCounts["unlink"] ?? 0) + 1;
                    return true;
                }

                UploadedFileTest::fail("unlink() called with unexpected file name.");
            }
        );

        require_once __DIR__ . "/MockSplFileInfo.php";
        uopz_set_mock(SplFileInfo::class, MockSplFileInfo::class);
    }

    private function removeFilesystemFunctionMocks(): void
    {
        foreach (["file_exists", "file_get_contents", "is_readable", "is_file", "unnlink",] as $functionName) {
            if ($this->isFunctionMocked($functionName)) {
                $this->removeFunctionMock($functionName);
            }
        }

        if (uopz_get_mock(SplFileInfo::class)) {
            uopz_unset_mock(SplFileInfo::class);
        }
    }

    public function testActualSize(): void
    {
        // test using size of temp file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));

        // NOTE we don't set up the mocks until here because we are mocking fs functions that the class loader uses and
        //  we don't want to interfere with that, so we wait until all classes have been autoloaded
        $this->mockFilesystemFunctions();
        self::assertSame(self::TempFileSize, $file->actualSize());
        self::assertSame(0, $this->callCounts["file_get_contents"] ?? 0);

        // test using length of content read from temp file
        uopz_unset_mock(SplFileInfo::class);
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        $file->data();
        self::assertSame(self::TempFileSize, $file->actualSize());
        self::assertSame(1, $this->callCounts["file_get_contents"]);
        self::assertSame(self::tempFileContents(), $file->data());
    }

    public function testData(): void
    {
        // set up a mock application as the data() method uses the Log facade
        $app = Mockery::mock(Application::class);
        $this->mockMethod(Application::class, "instance", $app);

        $app->shouldReceive("get")
            ->with(LoggerContract::class)
            ->andReturn(new NullLogger())
            ->byDefault();

        // force the autoloader to load the Log class before we mock the fs functions
        Log::info("");

        // test successful read
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        $this->mockFilesystemFunctions();
        self::assertTrue($file->isValid());
        self::assertEquals(self::tempFileContents(), $file->data());
        self::assertEquals(1, $this->callCounts["file_get_contents"]);

        // test with unreadable file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        self::assertTrue($file->isValid());
        $this->mockFilesystemFunctions();
        $this->mockFunction("is_readable", fn (string $fileName) => false);
        self::assertNull($file->data());

        // test with non-file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        self::assertTrue($file->isValid());
        $this->mockFilesystemFunctions();
        $this->mockFunction("is_file", fn (string $fileName) => false, true);
        self::assertNull($file->data());

        // test with non-existent file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        self::assertTrue($file->isValid());
        $this->mockFilesystemFunctions();
        $this->mockFunction("is_file", fn (string $fileName) => false, true);
        self::assertNull($file->data());
    }

    public function testIsValid(): void
    {
        // ensure valid file is reported as such
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        $this->mockFilesystemFunctions();
        self::assertTrue($file->isValid());

        // ensure non-0 error code is an invalid file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName, "error" => 1]));
        self::assertFalse($file->isValid());

        $this->removeFilesystemFunctionMocks();

        // ensure non-existent temp file is an invalid file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName, "error" => 1]));
        $this->mockFunction("file_exists", fn ($fileName) => false);
        self::assertFalse($file->isValid());

        // ensure non-file temp file is an invalid file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName, "error" => 1]));
        $this->mockFunction("file_exists", fn ($fileName) => true);
        $this->mockFunction("is_file", fn ($fileName) => false);
        self::assertFalse($file->isValid());

        // ensure unreadable temp file is an invalid file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName, "error" => 1]));
        $this->mockFunction("file_exists", fn ($fileName) => true);
        $this->mockFunction("is_file", fn ($fileName) => true);
        $this->mockFunction("is_readable", fn ($fileName) => false);
        self::assertFalse($file->isValid());
    }

    public function testDiscard(): void
    {
        // test discard invalidates uploaded file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        $this->mockFilesystemFunctions();
        self::assertTrue($file->isValid());
        self::assertTrue($file->discard());
        self::assertFalse($file->isValid());
        self::assertEquals(1, $this->callCounts["unlink"]);

        // test discard failing doesn't invalidate uploaded file
        $this->mockFunction("unlink", fn ($fileName) => false);
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        self::assertTrue($file->isValid());
        self::assertFalse($file->discard());
        self::assertTrue($file->isValid());
    }

    public function testMoveTo(): void
    {
        // test move invalidates uploaded file
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        $this->mockFilesystemFunctions();
        self::assertTrue($file->isValid());
        $info = $file->moveTo(self::DestinationFileName);
        self::assertEquals(self::DestinationFileName, $info->getPathname());
        self::assertFalse($file->isValid());
        self::assertEquals(1, $this->callCounts["move_uploaded_file"]);

        // test move failing doesn't invalidate uploaded file
        $this->mockFunction("move_uploaded_file", fn (string $fileName, string $destination) => false);
        $file = self::createUploadedFile(self::createFileMap(["tmp_name" => self::TempFileName]));
        self::assertTrue($file->isValid());
        self::assertNull($file->moveTo(self::DestinationFileName));
        self::assertTrue($file->isValid());
    }
}
