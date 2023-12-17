<?php

namespace BeadTests\Core\Binders;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Core\Application;
use Bead\Core\Binders\Logger as LoggerBinder;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Logging\CompositeLogger;
use Bead\Logging\FileLogger;
use Bead\Logging\NullLogger;
use Bead\Logging\StandardErrorLogger;
use Bead\Logging\StandardOutputLogger;
use Bead\Testing\StaticXRay;
use Bead\Testing\XRay;
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
        $this->mockMethod(Application::class, "instance", $this->app);
        $this->app->shouldReceive("rootDir")->andReturn("/")->byDefault();
    }

    public function tearDown(): void
    {
        Mockery::close();
        unset($this->logger, $this->app);
        parent::tearDown();
    }

    /** Ensure the default configuration is as expected. */
    public function testDefaultConfiguration(): void
    {
        $logger = new XRay($this->logger);

        self::assertEquals(
            [
                "loggers" => "file",
                "logs" => [
                    "file" => [
                        "driver" => "file",
                        "path" => "data/logs/bead-app.log",
                        "flags" => FileLogger::FlagAppend,
                    ],
                ],
            ],
            $logger->defaultConfiguration()
        );
    }

    public static function dataForTestCreateFileLogger1(): iterable
    {
        $config = [
            "driver" => "file",
            "path" => "the-bead-app-log.log",
            "flags" => FileLogger::FlagAppend,
        ];

        yield "relative file path" => [$config, self::tempDir(), self::tempDir() . "/the-bead-app-log.log"];

        $config["path"] = self::tempDir() . "/the-absolute-bead-app-log.log";
        yield "absolute file path" => [$config, "/some/other/path/that/is/not/used", self::tempDir() . "/the-absolute-bead-app-log.log"];

        $config["path"] = "..the-bead-app-log.log";
        yield "relative file path with legitimate leading .." => [$config, self::tempDir(), self::tempDir() . "/..the-bead-app-log.log"];
        $config["path"] = "the-bead-app-log.log..";
        yield "relative file path with legitimate trailing .." => [$config, self::tempDir(), self::tempDir() . "/the-bead-app-log.log.."];
        $config["path"] = "the-bead-app-log..log";
        yield "relative file path with legitimate .. in middle" => [$config, self::tempDir(), self::tempDir() . "/the-bead-app-log..log"];

        $config["path"] = self::tempDir() . "/..the-absolute-bead-app-log.log";
        yield "absolute file path with legitimate leading .." => [$config, "/some/other/path/that/is/not/used", self::tempDir() . "/..the-absolute-bead-app-log.log"];
        $config["path"] = self::tempDir() . "/the-absolute-bead-app-log.log..";
        yield "absolute file path with legitimate trailing .." => [$config, "/some/other/path/that/is/not/used", self::tempDir() . "/the-absolute-bead-app-log.log.."];
        $config["path"] = self::tempDir() . "/the-absolute-bead-app-log..log";
        yield "absolute file path with legitimate .. in middle" => [$config, "/some/other/path/that/is/not/used", self::tempDir() . "/the-absolute-bead-app-log..log"];
    }

    /**
     * Ensure file loggers are created with the appropriate path.
     *
     * @dataProvider dataForTestCreateFileLogger1
     * @param array $config
     * @param string $root
     * @param $expectedPath
     */
    public function testCreateFileLogger1(array $config, string $root, $expectedPath): void
    {
        $this->app->shouldReceive("rootDir")
            ->atMost()
            ->once()
            ->andReturn($root);

        $logger = new XRay($this->logger);
        $actualLogger = $logger->createFileLogger($config);
        self::assertInstanceOf(FileLogger::class, $actualLogger);
        self::assertEquals($expectedPath, $actualLogger->fileName());
    }

    public static function dataForTestCreateFileLogger2(): iterable
    {
        $config = [
            "driver" => "file",
            "flags" => FileLogger::FlagAppend,
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
            $config["path"] = $path;
            yield $label => [$config];
        }
    }

    /**
     * Ensure we can't create file loggers with .. in the path.
     *
     * @dataProvider dataForTestCreateFileLogger2
     */
    public function testCreateFileLogger2(array $config): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessageMatches("/^Expected log file path without directory traversal components, found \".*\\.\\..*\"\$/");
        $logger = new XRay($this->logger);
        $logger->createFileLogger($config);
    }

    /** Ensure createFileLogger() throws when the "path" config key is missing. */
    public function testCreateFileLogger3(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected log file path, none found");
        $logger = new XRay($this->logger);
        $logger->createFileLogger([
            "driver" => "file",
            "flags" => FileLogger::FlagAppend,
        ]);
    }

    /** Ensure createLogger() throws when the named logger is not found. */
    public function testCreateLogger1(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected configuration array for \"file\", found none");
        (new XRay($this->logger))->createLogger("file", [" file" => [],]);
    }

    /** Ensure createLogger() uses the configured log level. */
    public function testCreateLogger2(): void
    {
        $logger = (new XRay($this->logger))->createLogger("null", ["null" => ["driver" => "null", "level" => LoggerContract::DebugLevel,],]);
        self::assertInstanceOf(NullLogger::class, $logger);
        self::assertEquals(LoggerContract::DebugLevel, $logger->level());
    }

    /** Ensure createLogger() uses the default log level when none is given. */
    public function testCreateLogger3(): void
    {
        $logger = (new XRay($this->logger))->createLogger("null", ["null" => ["driver" => "null",],]);
        self::assertInstanceOf(NullLogger::class, $logger);
        self::assertEquals(LoggerContract::ErrorLevel, $logger->level());
    }

    public static function dataForTestBindServices1(): iterable
    {
        foreach (self::dataForTestCreateFileLogger1() as $key => $data) {
            $config = $data[0];
            $data[0]["loggers"] = "file";
            $data[0]["logs"] = [
                "file" => $config,
            ];

            yield $key => $data;
        }
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

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.loggers")
            ->andReturn($config["loggers"]);

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.logs")
            ->andReturn($config["logs"]);

        $this->app->shouldReceive("rootDir")
            ->atMost()
            ->once()
            ->andReturn($root);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(LoggerContract::class, Mockery::on($match));

        $this->logger->bindServices($this->app);
    }

    /** Ensure we can successfully bind stdout logger. */
    public function testBindServices2(): void
    {
        $match = function (mixed $logger): bool {
            self::assertInstanceOf(StandardOutputLogger::class, $logger);
            return true;
        };

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.loggers")
            ->andReturn("stdout");

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.logs")
            ->andReturn(["stdout" => ["driver" => "stdout",],]);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(LoggerContract::class, Mockery::on($match));

        $this->logger->bindServices($this->app);
    }

    /** Ensure we can successfully bind stderr logger. */
    public function testBindServices3(): void
    {
        $match = function (mixed $logger): bool {
            self::assertInstanceOf(StandardErrorLogger::class, $logger);
            return true;
        };

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.loggers")
            ->andReturn("stderr");

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.logs")
            ->andReturn(["stderr" => ["driver" => "stderr",],]);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(LoggerContract::class, Mockery::on($match));

        $this->logger->bindServices($this->app);
    }

    /** Ensure we can successfully bind null logger. */
    public function testBindServices4(): void
    {
        $match = function (mixed $logger): bool {
            self::assertInstanceOf(NullLogger::class, $logger);
            return true;
        };

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.loggers")
            ->andReturn("null");

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.logs")
            ->andReturn(["null" => ["driver" => "null",],]);

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(LoggerContract::class, Mockery::on($match));

        $this->logger->bindServices($this->app);
    }

    public static function dataForTestBindServices5(): iterable
    {
        yield "empty array" => [[]];
        yield "int" => [42,];
        yield "float" => [3.14159,];
        yield "object" => [(object) [],];
        yield "true" => [true,];
        yield "false" => [false,];
        yield "array with non-string" => [["file", 42,],];
    }

    /**
     * Ensure bindServices() throws if invalid loggers are specified.
     *
     * @dataProvider dataForTestBindServices5
     */
    public function testBindServices5(mixed $loggers): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("log.loggers")
            ->andReturn($loggers);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected defined log name or array of such");
        $this->logger->bindServices($this->app);
    }

    /** Ensure bindServices() throws if the log driver is invalid. */
    public function testBindServices6(): void
    {
        $this->app->shouldReceive("config")
            ->once()
            ->with("log.loggers")
            ->andReturn("file");

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.logs")
            ->andReturn(["file" => ["driver" => "invalid-bead-log-driver",],]);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage("Expected recognised log driver, found \"invalid-bead-log-driver\"");
        $this->logger->bindServices($this->app);
    }

    /** Ensure bindServices() uses the default configuration if no loggers are specified. */
    public function testBindServices7(): void
    {
        $match = function (mixed $logger): bool {
            self::assertInstanceOf(FileLogger::class, $logger);
            self::assertEquals(self::tempDir() . "/data/logs/bead-app.log", $logger->fileName());
            return true;
        };

        mkdir(self::tempDir() . "/data/logs/", 0777, true);

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.loggers")
            ->andReturn(null);

        $this->app->shouldNotReceive("config")
            ->with("log.logs");

        $this->app->shouldReceive("rootDir")
            ->atMost()
            ->once()
            ->andReturn(self::tempDir());

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(LoggerContract::class, Mockery::on($match));

        $this->logger->bindServices($this->app);
    }

    /** Ensure bindServices() binds a composite logger when multiple loggers are specified. */
    public function testBindServices8(): void
    {
        $match = function (mixed $logger): bool {
            self::assertInstanceOf(CompositeLogger::class, $logger);
            self::assertCount(2, $logger);
            self::assertInstanceOf(FileLogger::class, $logger[0]);
            self::assertEquals(self::tempDir() . "/app.log", $logger[0]->fileName());
            self::assertInstanceOf(StandardOutputLogger::class, $logger[1]);
            return true;
        };

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.loggers")
            ->andReturn(["file", "stdout",]);

        $this->app->shouldReceive("config")
            ->once()
            ->with("log.logs")
            ->andReturn(
                [
                    "file" => [
                        "driver" => "file",
                        "path" => "app.log",
                        "flags" => FileLogger::FlagAppend,
                    ],
                    "stdout" => [
                        "driver" => "stdout",
                    ],
                ]
            );

        $this->app->shouldReceive("rootDir")
            ->atMost()
            ->once()
            ->andReturn(self::tempDir());

        $this->app->shouldReceive("bindService")
            ->once()
            ->with(LoggerContract::class, Mockery::on($match));

        $this->logger->bindServices($this->app);
    }
}
