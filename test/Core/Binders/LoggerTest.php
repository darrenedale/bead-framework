<?php

namespace BeadTests\Core\Binders;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Core\Application;
use Bead\Core\Binders\Logger as LoggerBinder;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Logging\FileLogger;
use Bead\Logging\NullLogger;
use Bead\Logging\StandardErrorLogger;
use Bead\Logging\StandardOutputLogger;
use Bead\Testing\StaticXRay;
use BeadTests\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionClassConstant;

/** Test the bundled logger binder. */
final class LoggerTest extends TestCase
{
    private LoggerBinder $logger;

    /** @var Application&MockInterface  */
    private Application $app;

    public function setUp(): void
    {
        $this->logger = new LoggerBinder();
        $this->app = Mockery::mock(Application::class);
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->logger, $this->app);
        parent::tearDown();
    }

    public static function dataForTestFileLogger1(): iterable
    {
        $config = [
            "driver" => "file",
            "file" => [
                "path" => "the-bead-app-log.log",
                "flags" => FileLogger::FlagAppend,
            ],
        ];

        yield "relative file path" => [$config, self::tempDir(), self::tempDir() . "/the-bead-app-log.log"];

        $config["file"]["path"] = self::tempDir() . "/the-absolute-bead-app-log.log";
        yield "absolute file path" => [$config, "/some/other/path/that/is/not/used", self::tempDir() . "/the-absolute-bead-app-log.log"];

        $config["file"]["path"] = "..the-bead-app-log.log";
        yield "relative file path with legitimate leading .." => [$config, self::tempDir(), self::tempDir() . "/..the-bead-app-log.log"];
        $config["file"]["path"] = "the-bead-app-log.log..";
        yield "relative file path with legitimate trailing .." => [$config, self::tempDir(), self::tempDir() . "/the-bead-app-log.log.."];
        $config["file"]["path"] = "the-bead-app-log..log";
        yield "relative file path with legitimate .. in middle" => [$config, self::tempDir(), self::tempDir() . "/the-bead-app-log..log"];

        $config["file"]["path"] = self::tempDir() . "/..the-absolute-bead-app-log.log";
        yield "absolute file path with legitimate leading .." => [$config, "/some/other/path/that/is/not/used", self::tempDir() . "/..the-absolute-bead-app-log.log"];
        $config["file"]["path"] = self::tempDir() . "/the-absolute-bead-app-log.log..";
        yield "absolute file path with legitimate trailing .." => [$config, "/some/other/path/that/is/not/used", self::tempDir() . "/the-absolute-bead-app-log.log.."];
        $config["file"]["path"] = self::tempDir() . "/the-absolute-bead-app-log..log";
        yield "absolute file path with legitimate .. in middle" => [$config, "/some/other/path/that/is/not/used", self::tempDir() . "/the-absolute-bead-app-log..log"];
    }

    /**
     * Ensure file loggers are created with the appropriate path.
     *
     * @dataProvider dataForTestFileLogger1
     * @param array $config
     * @param string $root
     * @param $expectedPath
     */
    public function testFileLogger1(array $config, string $root, $expectedPath): void
    {
        $logger = new StaticXRay(LoggerBinder::class);
        $actualLogger = $logger->fileLogger($config, $root);
        self::assertInstanceOf(FileLogger::class, $actualLogger);
        self::assertEquals($expectedPath, $actualLogger->fileName());
    }

    public static function dataForTestFileLogger2(): iterable
    {
        $config = [
            "driver" => "file",
            "file" => [
                "flags" => FileLogger::FlagAppend,
            ],
        ];

        $datasets = [
            "just .." => "..",
            ".. at beginning" => "../some/path",
            ".. at end" => "some/path/..",
            ".. in middle" => "some/path/../with/../in/it",
            "absolute with .. at end" => "/some/path/..",
            "absolute with .. in middle" => "/some/path/../with/../in/it",
        ];

        foreach ($datasets as $label => $path) {
            $config["file"]["path"] = $path;
            yield $label => [$config];
        }
    }

    /**
     * Ensure we can't create file loggers with .. in the path.
     *
     * @dataProvider dataForTestFileLogger2
     */
    public function testFileLogger2(array $config): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessageMatches("/^Expected log file path without directory traversal components, found \".*\\.\\..*\"\$/");
        $logger = new StaticXRay(LoggerBinder::class);
        $logger->fileLogger($config, "");
    }

    public static function dataForTestBindServices1(): iterable
    {
        yield from self::dataForTestFileLogger1();
    }

    /**
     * Ensure we can successfully bind file loggers.
     *
     * @dataProvider dataForTestBindServices1
     */
    public function testBindServices1(array $config, string $root, string $expectedPath): void
    {
        $match = function (mixed $logger) use ($expectedPath): bool {
            self::assertInstanceOf(FileLogger::class, $logger);
            self::assertEquals($expectedPath, $logger->fileName());
            return true;
        };

        $defaultConfig = (new ReflectionClassConstant(LoggerBinder::class, "DefaultConfig"))->getValue();

        $this->app->shouldReceive("config")
            ->once()
            ->with("log", $defaultConfig)
            ->andReturn($config);

        $this->app->shouldReceive("rootDir")
            ->once()
            ->andReturn($root);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(LoggerContract::class, Mockery::on($match));

        $this->logger->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we can successfully bind stdout logger. */
    public function testBindServices2(): void
    {
        $match = function (mixed $logger): bool {
            self::assertInstanceOf(StandardOutputLogger::class, $logger);
            return true;
        };

        $defaultConfig = (new ReflectionClassConstant(LoggerBinder::class, "DefaultConfig"))->getValue();

        $this->app->shouldReceive("config")
            ->once()
            ->with("log", $defaultConfig)
            ->andReturn(["driver" => "stdout",]);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(LoggerContract::class, Mockery::on($match));

        $this->logger->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we can successfully bind stderr logger. */
    public function testBindServices3(): void
    {
        $match = function (mixed $logger): bool {
            self::assertInstanceOf(StandardErrorLogger::class, $logger);
            return true;
        };

        $defaultConfig = (new ReflectionClassConstant(LoggerBinder::class, "DefaultConfig"))->getValue();

        $this->app->shouldReceive("config")
            ->once()
            ->with("log", $defaultConfig)
            ->andReturn(["driver" => "stderr",]);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(LoggerContract::class, Mockery::on($match));

        $this->logger->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure we can successfully bind stderr logger. */
    public function testBindServices4(): void
    {
        $match = function (mixed $logger): bool {
            self::assertInstanceOf(NullLogger::class, $logger);
            return true;
        };

        $defaultConfig = (new ReflectionClassConstant(LoggerBinder::class, "DefaultConfig"))->getValue();

        $this->app->shouldReceive("config")
            ->once()
            ->with("log", $defaultConfig)
            ->andReturn(["driver" => "null",]);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(LoggerContract::class, Mockery::on($match));

        $this->logger->bindServices($this->app);
        self::markTestAsExternallyVerified();
    }

    /** Ensure bindServices() throws if the log driver is not specified. */
    public function testBindServices5(): void
    {
        $defaultConfig = (new ReflectionClassConstant(LoggerBinder::class, "DefaultConfig"))->getValue();

        $this->app->shouldReceive("config")
            ->once()
            ->with("log", $defaultConfig)
            ->andReturn([]);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected log driver, none found");
        $this->logger->bindServices($this->app);
    }

    /** Ensure bindServices() throws if the log driver is invalid. */
    public function testBindServices6(): void
    {
        $defaultConfig = (new ReflectionClassConstant(LoggerBinder::class, "DefaultConfig"))->getValue();

        $this->app->shouldReceive("config")
            ->once()
            ->with("log", $defaultConfig)
            ->andReturn(["driver" => "invalid-bead-log-driver",]);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected recognised log driver, found \"invalid-bead-log-driver\"");
        $this->logger->bindServices($this->app);
    }
}
