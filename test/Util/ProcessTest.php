<?php

declare(strict_types=1);

namespace BeadTests\Util;

use InvalidArgumentException;
use TypeError;
use Bead\Process;
use BeadTests\Framework\TestCase;
use Stringable;

/**
 * Unit test for the Process class.
 */
class ProcessTest extends TestCase
{
    /**
     * Test data for testCleanupTimeout
     *
     * @return iterable The test data.
     */
    public function dataForTestCleanupTimeout(): iterable
    {
        yield from [
            "typical10" => [10,],
            "typical20" => [20,],
            "typical30" => [30,],
            "typical40" => [40,],
            "typical50" => [50,],
            "typical60" => [60,],
            "typicalReset" => [null,],
            "extreme0" => [0,],
            "extremeIntMax" => [PHP_INT_MAX,],
            "invalidNegative" => [-1, \InvalidArgumentException::class,],
            "invalidIntMin" => [PHP_INT_MIN, \InvalidArgumentException::class,],
            "invalidFloat" => [12.5, TypeError::class,],
            "invalidBool" => [true, TypeError::class,],
            "invalidArray" => [[30,], TypeError::class,],
            "invalidObject" => [(object) [30,], TypeError::class,],
            "invalidString" => ["30", TypeError::class,],
            "invalidAnonymousClass" => [
                new class
                {
                    public function __toInt()
                    {
                        return 30;
                    }
                },
                TypeError::class,
            ],
        ];

        // 100 random valid timeouts
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "random{$idx}" => [mt_rand(0, 600)];
        }

        // 100 random invalid timeouts
        for ($idx = 0; $idx < 100; ++$idx) {
            yield "randomInvalid{$idx}" => [mt_rand(PHP_INT_MIN, -1), \InvalidArgumentException::class,];
        }
    }

    /**
     * @dataProvider dataForTestCleanupTimeout
     *
     * @param mixed $timeout The timeout to test.
     * @param string|null $exceptionClass The exception class expected, if any.
     */
    public function testCleanupTimeout($timeout, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        if (!isset($timeout)) {
            // set it to something we can reset from
            Process::setCleanupTimeout(Process::DefaultCleanupTimeout + rand(1, 30));
            self::assertNotEquals(Process::DefaultCleanupTimeout, Process::cleanupTimeout(), "The cleanup timeout was not be set to a random non-default value.");
        }

        Process::setCleanupTimeout($timeout);
        self::assertEquals($timeout ?? Process::DefaultCleanupTimeout, Process::cleanupTimeout(), "The cleanup timeout was not (re)set successfully.");
    }

    /**
     * Test data for testConstructor.
     *
     * @return array The test data.
     */
    public function dataForTestConstructor(): array
    {
        return [
            "typicalRootPwd" => ["/usr/bin/echo", [],  "/",],
            "typicalRootPwdWithArgs" => ["/usr/bin/echo", ["'foo'", "'bar'",],  "/",],
            "typicalDefaultPwd" => ["/usr/bin/echo", [], null,],
            "typicalDefaultPwdWithArgs" => ["/usr/bin/echo",  ["'foo'", "'bar'",], null,],
            "typicalRootPwdOutputNotifier" => [
                "/usr/bin/echo",
                [],
                "/",
                function () {
                },
            ],
            "typicalRootPwdOutputNotifierWithArgs" => [
                "/usr/bin/echo",
                ["'foo'", "'bar'",],
                "/",
                function () {
                },
            ],
            "typicalRootPwdErrorNotifier" => [
                "/usr/bin/echo",
                [],
                "/",
                null,
                function () {
                },
            ],
            "typicalRootPwdErrorNotifierWithArgs" => [
                "/usr/bin/echo",
                ["'foo'", "'bar'",],
                "/",
                null,
                function () {
                },
            ],
            "extremeEmptyCommand" => ["", [], "/",],
            "extremeEmptyCommandWithARgs" => ["",  ["'foo'", "'bar'",], "/",],
            "extremeEmptyPwd" => ["", [], "/",],
            "extremeEmptyPwdWithArgs" => ["",  ["'foo'", "'bar'",], "/",],
            "extremeEmptyCommandAndPwdWithArgs" => ["",  ["'foo'", "'bar'",], "",],
            "extremeRootPwdWithallTypesOfArgs" => ["/usr/bin/echo", ["'foo'", 0, 0.1, 21, 99.99,],  "/",],

            "invalidStringableAnonymousClassCommand" => [
                new class implements Stringable
                {
                    public function __toString(): string
                    {
                        return "/usr/bin/echo";
                    }
                },
                [],
                null,
                null,
                null,
                TypeError::class,
            ],
            "invalidStringableStdClassCommand" => [
                (object) [
                    "__toString" => function () {
                        return "/usr/bin/echo";
                    },
                ],
                [],
                null,
                null,
                null,
                TypeError::class,
            ],
            "invalidArrayCommand" => [
                [
                    "__toString" => function () {
                        return "/usr/bin/echo";
                    },
                ],
                [],
                null,
                null,
                null,
                TypeError::class,
            ],
            "invalidNullCommand" => [null, [], null, null, null, TypeError::class,],
            "invalidIntCommand" => [12, [], null, null, null, TypeError::class,],
            "invalidFloatCommand" => [29.456, [], null, null, null, TypeError::class,],

            "invalidArgsArrayWithInvalidElement" => ["/usr/bin/echo", ["'foo'", null,], null, null, null, InvalidArgumentException::class,],
            "invalidArgsArrayWithOnlyInvalidElements" => [
                "/usr/bin/echo",
                [
                    null,
                    new class implements Stringable
                    {
                        public function __toString(): string
                        {
                            return "'foo'";
                        }
                    },
                ],
                null,
                null,
                null,
                InvalidArgumentException::class,
            ],

            "invalidArrayableAnonymousClassArgs" => [
                "/usr/bin/echo",
                new class
                {
                    public function __toArray(): array
                    {
                        return ["'foo'"];
                    }
                },
                null,
                null,
                null,
                TypeError::class,
            ],
            "invalidArrayableStdClassArgs" => [
                "/usr/bin/echo",
                (object) [
                    "__toArray" => function () {
                        return ["'foo'"];
                    },
                ],
                null,
                null,
                null,
                TypeError::class,
            ],
            "invalidStringArgs" => ["/usr/bin/echo", "'foo'", null, null, null, TypeError::class,],
            "invalidNullArgs" => ["/usr/bin/echo", null, null, null, null, TypeError::class,],
            "invalidIntArgs" => ["/usr/bin/echo", 12, null, null, null, TypeError::class,],
            "invalidFloatArgs" => ["/usr/bin/echo", 29.456, null, null, null, TypeError::class,],

            "invalidStringableAnonymousClassPwd" => [
                "/usr/bin/echo",
                [],
                new class implements Stringable
                {
                    public function __toString(): string
                    {
                        return "/";
                    }
                },
                null,
                null,
                TypeError::class,
            ],
            "invalidStringableStdClassPwd" => [
                "/usr/bin/echo",
                [],
                (object) [
                    "__toString" => function () {
                        return "/";
                    },
                ],
                null,
                null,
                TypeError::class,
            ],
            "invalidArrayPwd" => [
                "/usr/bin/echo",
                [],
                [
                    "__toString" => function () {
                        return "/";
                    },
                ],
                null,
                null,
                TypeError::class,
            ],
            "invalidIntPwd" => ["/usr/bin/echo", [], 12, null, null, TypeError::class,],
            "invalidFloatPwd" => ["/usr/bin/echo", [], 29.456, null, null, TypeError::class,],

            "invalidInvokableOutputHandler" => [
                "/usr/bin/echo",
                [],
                "/",
                new class
                {
                    public function __invoke(): void
                    {
                    }
                },
                null,
                TypeError::class,
            ],
            "invalidInvokableErrorHandler" => [
                "/usr/bin/echo",
                [],
                "/",
                null,
                new class
                {
                    public function __invoke(): void
                    {
                    }
                },
                TypeError::class,
            ],
            "invalidInvokableOutputAndErrorHandler" => [
                "/usr/bin/echo",
                [],
                "/",
                new class
                {
                    public function __invoke(): void
                    {
                    }
                },
                new class
                {
                    public function __invoke(): void
                    {
                    }
                },
                TypeError::class,
            ],
        ];
    }

    /**
     * @dataProvider dataForTestConstructor
     *
     * @param mixed $command The process command line.
     * @param mixed $workingDirectory The process's working directory.
     * @param mixed $outputNotifier The output notifier to pass to the constructor.
     * @param mixed $errorNotifier The error notifier to pass to the constructor.
     * @param string|null $exceptionClass The expected exception, if any.
     *
     * @throws \ReflectionException
     */
    public function testConstructor($command, $arguments, $workingDirectory, $outputNotifier = null, $errorNotifier = null, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $process = new Process($command, $arguments, $workingDirectory, $outputNotifier, $errorNotifier);
        self::assertEquals($command, $process->command(), "Process was not constructed with correct command.");
        self::assertEquals($arguments, $process->arguments(), "Process was not constructed with correct arguments.");
        self::assertEquals($workingDirectory ?? getcwd(), $process->workingDirectory(), "Process was not constructed with correct working directory.");

        $property = new \ReflectionProperty($process, "m_outputNotifier");
        $property->setAccessible(true);
        self::assertEquals($outputNotifier, $property->getValue($process), "Output notifier was not set correctly by constructor.");

        $property = new \ReflectionProperty($process, "m_errorNotifier");
        $property->setAccessible(true);
        self::assertEquals($errorNotifier, $property->getValue($process), "Error notifier was not set correctly by constructor.");
    }

    /**
     * Test data for testSetCommand.
     *
     * @return array The test data.
     */
    public function dataForTestSetCommand(): array
    {
        return [
            "typical" => ["/usr/bin/echo",],
            "extremeEmpty" => ["", ],

            "invalidStringableAnonymousClass" => [
                new class implements Stringable
                {
                    public function __toString(): string
                    {
                        return "/usr/bin/echo";
                    }
                },
                TypeError::class,
            ],
            "invalidStringableStdClass" => [
                (object) [
                    "__toString" => function () {
                        return "/usr/bin/echo";
                    },
                ],
                TypeError::class,
            ],
            "invalidArray" => [
                [
                    "__toString" => function () {
                        return "/usr/bin/echo";
                    },
                ],
                TypeError::class,
            ],
            "invalidNull" => [null, TypeError::class,],
            "invalidInt" => [12, TypeError::class,],
            "invalidFloat" => [29.456, TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestSetCommand
     *
     * @param mixed $command The command to test with setCommand()
     * @param string|null $exceptionClass The expected exception, if any.
     */
    public function testSetCommand($command, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $process = new Process("");
        $process->setCommand($command);
        self::assertEquals($command, $process->command(), "Command was not set successfully.");
    }

    /**
     * Test setCommand() throws if used while process is running.
     */
    public function testSetCommandOnRunningProcess(): void
    {
        $this->expectException(\RuntimeException::class);
        $process = new Process("php", ["-r", "'sleep(2);'",]);
        $process->start();
        $process->setCommand("/usr/bin/echo");
    }

    /**
     * Test data for testSetArguments().
     *
     * @return array The test data.
     */
    public function dataForTestSetArguments(): array
    {
        return [
            "typicalEmpty" => [[],],
            "typicalTwoStringArgs" => [["'foo'", "'bar'",],],
            "extremeAllTypesOfArgs" => [["'foo'", 0, 0.1, 21, 99.99,],],

            "invalidOneInvalidElement" => [["'foo'", null,], InvalidArgumentException::class,],
            "invalidOnlyInvalidElements" => [
                [
                    null,
                    new class implements Stringable
                    {
                        public function __toString(): string
                        {
                            return "'foo'";
                        }
                    },
                ],
                InvalidArgumentException::class,
            ],

            "invalidArrayableAnonymousClass" => [
                new class
                {
                    public function __toArray(): array
                    {
                        return ["'foo'"];
                    }
                },
                TypeError::class,
            ],
            "invalidArrayableStdClass" => [
                (object) [
                    "__toArray" => function () {
                        return ["'foo'"];
                    },
                ],
                TypeError::class,
            ],
            "invalidString" => ["'foo'", TypeError::class,],
            "invalidNull" => [null, TypeError::class,],
            "invalidInt" => [12, TypeError::class,],
            "invalidFloat" => [29.456, TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestSetArguments
     *
     * @param mixed $args The arguments to set.
     * @param string|null $exceptionClass The expected exception, if any.
     */
    public function testSetArguments($args, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $process = new Process("");
        $process->setArguments($args);
        self::assertEquals($args, $process->arguments(), "Arguments were not set successfully.");
    }

    /**
     * Test setArguments() throws if used while process is running.
     */
    public function testSetArgumentsOnRunningProcess(): void
    {
        $this->expectException(\RuntimeException::class);
        $process = new Process("php", ["-r", "'sleep(2);'",]);
        $process->start();
        $process->setArguments(["-r", "'sleep(5);",]);
    }

    /**
     * Test data for testSetWorkingDirectory.
     *
     * @return array The test data.
     */
    public function dataForTestSetWorkingDirectory(): array
    {
        return [
            "typicalRoot" => ["/",],
            "typicalHome" => ["/home",],
            "typicalTmp" => ["/tmp",],
            "typicalNull" => [null,],
            "extremeEmpty" => ["", ],

            "invalidStringableAnonymousClass" => [
                new class implements Stringable
                {
                    public function __toString(): string
                    {
                        return "/";
                    }
                },
                TypeError::class,
            ],
            "invalidStringableStdClass" => [
                (object) [
                    "__toString" => function () {
                        return "/";
                    },
                ],
                TypeError::class,
            ],
            "invalidArray" => [
                [
                    "__toString" => function () {
                        return "/";
                    },
                ],
                TypeError::class,
            ],
            "invalidInt" => [12, TypeError::class,],
            "invalidFloat" => [29.456, TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestSetWorkingDirectory
     *
     * @param mixed $workingDirectory The working directory to test with setWorkingDirectory()
     * @param string|null $exceptionClass The expected exception, if any.
     */
    public function testSetWorkingDirectory($workingDirectory, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $process = new Process("");
        $process->setWorkingDirectory($workingDirectory);
        self::assertEquals($workingDirectory ?? getcwd(), $process->workingDirectory(), "Working directory was not set successfully.");
    }

    /**
     * Test setWorkingDirectory() throws if used while process is running.
     */
    public function testSetWorkingDirectoryOnRunningProcess(): void
    {
        $this->expectException(\RuntimeException::class);
        $process = new Process("php", ["-r", "'sleep(2);'",]);
        $process->start();
        $process->setWorkingDirectory("/");
    }

    /**
     * Test data for testSetArguments().
     *
     * @return array The test data.
     */
    public function dataForTestSetEnvironment(): array
    {
        return [
            "typicalString" => [["foo" => "bar",],],
            "typicalInt" => [["meaning" => 42,],],
            "typicalFloat" => [["pi" => 3.1415927,],],
            "typicalNull" => [null,],
            "extremeEmpty" => [[],],
            "extremeAllTypesOfArgs" => [["foo" => "bar", "meaning" => 42, "pi" => 3.1415927,],],

            "invalidArrayValue" => [["foo" => ["bar"],], InvalidArgumentException::class,],
            "invalidNullValue" => [["foo" => null,], InvalidArgumentException::class,],
            "invalidStringableAnonymousClassValue" => [
                [
                    "foo" => new class implements Stringable
                    {
                        public function __toString(): string
                        {
                            return "bar";
                        }
                    },
                ],
                InvalidArgumentException::class,
            ],
            "invalidStringableObjectValue" => [
                [
                    "foo" => (object) [
                        "__toString" => function (): string {
                            return "bar";
                        },
                    ],
                ],
                InvalidArgumentException::class,
            ],

            "invalidOneInvalidElement" => [["foo" => "bar", "baz" => null,], InvalidArgumentException::class,],
            "invalidAllInvalidElements" => [
                [
                    "foo" => null,
                    "bar" => new class implements Stringable
                    {
                        public function __toString(): string
                        {
                            return "'foo'";
                        }
                    },
                ],
                InvalidArgumentException::class,
            ],

            "invalidArrayableAnonymousClass" => [
                new class
                {
                    public function __toArray(): array
                    {
                        return ["foo" => "bar"];
                    }
                },
                TypeError::class,
            ],
            "invalidArrayableStdClass" => [
                (object) [
                    "__toArray" => function () {
                        return ["foo" => "bar"];
                    },
                ],
                TypeError::class,
            ],
            "invalidString" => ["foo", TypeError::class,],
            "invalidInt" => [12, TypeError::class,],
            "invalidFloat" => [29.456, TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestSetEnvironment
     *
     * @return void
     */
    public function testSetEnvironment($env, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $process = new Process("");
        $process->setEnvironment($env);
        self::assertEquals($env, $process->environment(), "Process environment was not set successfully.");
    }

    /**
     * Test data for testSetEnvironmentOnRunningProcess().
     * @return array The test data.
     */
    public function dataForTestSetEnvironmentOnRunningProcess(): array
    {
        return [
            [["foo" => "bar",]],
            [null],
            [[]],
        ];
    }

    /**
     * Test setEnvironment() throws if used while process is running.
     * @dataProvider dataForTestSetEnvironmentOnRunningProcess
     */
    public function testSetEnvironmentOnRunningProcess($env): void
    {
        $this->expectException(\RuntimeException::class);
        $process = new Process("php", ["-r", "'sleep(2);'",]);
        $process->start();
        $process->setEnvironment($env);
    }

    /**
     * @return void
     */
    public function dataForTestStart(): array
    {
        return [
            "typical" => ["php", ["-r", "'sleep(2); echo \"Done\";'",], true, 0, "Done", "",],
            "typicalNonZeroExitCode" => ["php", ["-r", "'echo \"Done\"; fprintf(STDERR, \"Error\"); exit(2);'",], true, 2, "Done", "Error",],
            "invalidEmptyCommand" => ["", [], false, null, "", "", \RuntimeException::class],
        ];
    }

    /**
     * @dataProvider dataForTestStart
     */
    public function testStart(string $command, array $args, bool $shouldStart, ?int $expectedExitCode, string $expectedStdOut, string $expectedStdErr, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $stdOut = "";
        $stdErr = "";

        $process = null;
        $process = new Process(
            $command,
            $args,
            null,
            function () use (&$stdOut, &$process): void {
                $stdOut .= $process->readOutput();
            },
            function () use (&$stdErr, &$process): void {
                $stdErr .= $process->readErrorOutput();
            }
        );

        self::assertEquals($shouldStart, $process->start(), "The process did not start.");
        self::assertEquals($shouldStart, $process->isRunning(), "Process is not running.");
        $process->wait();
        self::assertEquals($expectedExitCode, $process->exitCode(), "The expected exit code was not produced.");
        self::assertEquals($expectedStdOut, $stdOut, "Process did not produce the expected output.");
        self::assertEquals($expectedStdErr, $stdErr, "Process did not produce the expected error output.");
    }

    /**
     * Test processes stop as expected.
     */
    public function testStop(): void
    {
        $process = new Process("php", ["-r", "'sleep(20);'",]);
        $process->start();
        usleep(500000);
        self::assertTrue($process->isRunning(), "Test process could not be started.");
        $stoppedAt = microtime(true);
        $process->stop();
        $process->wait(20);
        self::assertFalse($process->isRunning(), "Process was not stopped successfully.");
        self::assertLessThan(20, microtime(true) - $stoppedAt, "Process was not stopped successfully within the 20s it was expected to run.");
        self::assertNotEquals(0, $process->exitCode(), "Process should not have exited with exit code 0.");
    }

    /**
     * Test data for testPid().
     *
     * @return array The test data.
     */
    public function dataForTestPid(): array
    {
        return [
            ["php", ["-r", "'sleep(1);'",],],
        ];
    }

    /**
     * @dataProvider dataForTestPid
     *
     * @param string $commandLine The command to run.
     * @param bool $expectNullPid Whether it's expected to yield a `null` PID (i.e. doesn't actually start).
     */
    public function testPid(string $command, array $args): void
    {
        $process = new Process($command, $args);
        $process->start();
        self::assertIsInt($process->pid(), "Pid is not valid.");
        $process->stop();
        $process->wait();
        self::assertNull($process->pid(), "PID for terminated process is not null.");
    }
}
