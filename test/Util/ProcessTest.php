<?php

declare(strict_types=1);

namespace BeadTests\Util;

use Bead\Testing\XRay;
use InvalidArgumentException;
use RuntimeException;
use TypeError;
use Bead\Process;
use BeadTests\Framework\TestCase;
use Stringable;

/**
 * Unit test for the Process class.
 */
class ProcessTest extends TestCase
{
    /** Test data for testSetCleanupTimeout1 */
    public function dataForTestSetCleanupTimeout1(): iterable
    {
        for ($timeout = 10; $timeout <= 60; ++$timeout) {
            yield "typical{$timeout}" => [$timeout,];
        }

        yield "extreme0" => [0,];
        yield "extremeIntMax" => [PHP_INT_MAX,];
    }

    /**
     * Ensure we can set valid cleanup timeouts.
     *
     * @dataProvider dataForTestSetCleanupTimeout1
     *
     * @param int $timeout The timeout to test.
     */
    public function testSetCleanupTimeout1(int $timeout): void
    {
        Process::setCleanupTimeout($timeout);
        self::assertEquals($timeout, Process::cleanupTimeout(), "The cleanup timeout was not set successfully.");
    }

    /** Ensure we can reset the cleanup timeout. */
    public function testSetCleanupTimeout2(): void
    {
        Process::setCleanupTimeout(999);
        self::assertNotEquals(Process::DefaultCleanupTimeout, Process::cleanupTimeout());
        Process::setCleanupTimeout(null);
        self::assertEquals(Process::DefaultCleanupTimeout, Process::cleanupTimeout(), "The cleanup timeout was not reset successfully.");
    }

    /** Test data for testSetCleanupTimeout3 */
    public function dataForTestSetCleanupTimeout3(): iterable
    {
        yield "invalidNegative" => [-1,];
        yield "invalidIntMin" => [PHP_INT_MIN,];
    }

    /**
     * Ensure we get the expected exception when an invalid cleanup timeout is set.
     *
     * @dataProvider dataForTestSetCleanupTimeout3
     *
     * @param int $timeout The invalid timeout to test.
     */
    public function testSetCleanupTimeout3(int $timeout): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Expected cleanup timeout >=0, found {$timeout}");
        Process::setCleanupTimeout($timeout);
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
        $processXray = new XRay($process);

        self::assertEquals($command, $process->command(), "Process was not constructed with correct command.");
        self::assertEquals($arguments, $process->arguments(), "Process was not constructed with correct arguments.");
        self::assertEquals($workingDirectory ?? getcwd(), $process->workingDirectory(), "Process was not constructed with correct working directory.");

        self::assertEquals($outputNotifier, $processXray->m_outputNotifier, "Output notifier was not set correctly by constructor.");
        self::assertEquals($errorNotifier, $processXray->m_errorNotifier, "Error notifier was not set correctly by constructor.");
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
        $this->expectException(RuntimeException::class);
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
        $this->expectException(RuntimeException::class);
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
        $this->expectException(RuntimeException::class);
        $process = new Process("php", ["-r", "'sleep(2);'",]);
        $process->start();
        $process->setWorkingDirectory("/");
    }

    public function dataForTestSetEnvironment1(): array
    {
        return [
            "typicalString" => [["foo" => "bar",],],
            "typicalInt" => [["meaning" => 42,],],
            "typicalFloat" => [["pi" => 3.1415927,],],
            "extremeEmpty" => [[],],
            "extremeAllTypesOfArgs" => [["foo" => "bar", "meaning" => 42, "pi" => 3.1415927,],],
        ];
    }

    /**
     * @dataProvider dataForTestSetEnvironment1
     */
    public function testSetEnvironment1(array $env, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $process = new Process("");
        $process->setEnvironment($env);
        self::assertEquals($env, $process->environment(), "Process environment was not set successfully.");
    }

    /** Ensure we can set a null environment. */
    public function testSetEnvironment2(): void
    {
        $process = new Process("php");
        $process->setEnvironment(["foo" => "bar",]);
        self::assertNotNull($process->environment());
        $process->setEnvironment(null);
        self::assertNull($process->environment(), "Process environment was not reset successfully.");
    }

    public function dataForTestSetEnvironment3(): iterable
    {
        yield "invalidArrayValue" => [["foo" => ["bar"],],];
        yield "invalidNullValue" => [["foo" => null,],];

        yield "invalidStringableAnonymousClassValue" => [
            [
                "foo" => new class implements Stringable
                {
                    public function __toString(): string
                    {
                        return "bar";
                    }
                },
            ],
        ];

        yield "invalidStringableObjectValue" => [
            [
                "foo" => (object) [
                    "__toString" => function (): string {
                        return "bar";
                    },
                ],
            ],
        ];

        yield "invalidOneInvalidElement" => [["foo" => "bar", "baz" => null,],];

        yield "invalidAllInvalidElements" => [
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
        ];
    }

    /**
     * Ensure setEnvironment() throws with an invalid environment variable values.
     * @dataProvider dataForTestSetEnvironment3
     */
    public function testSetEnvironment3(array $env): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Environment values must be strings or numbers");
        $process = new Process("php");
        $process->setEnvironment($env);
    }

    public function dataForTestSetEnvironment4(): iterable
    {
        yield "invalid-int-last" => [["foo" => "bar", 2 => "baz",],];
        yield "invalid-int-first" => [[2 => "foo", "foo" => "bar",],];
        yield "invalid-int-middle" => [["foo" => "bar", 2 => "foo", "fax" => "box",],];
        yield "invalid-all-ints" => [[3 => "bar", 1 => "foo", 2 => "box",],];
    }

    /**
     * Ensure setEnvironment() throws with an invalid environment variable names.
     * @dataProvider dataForTestSetEnvironment4
     */
    public function testSetEnvironment4(array $env): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Environment keys must be strings");
        $process = new Process("php");
        $process->setEnvironment($env);
    }

    public function dataForTestSetEnvironment5(): iterable
    {
        yield "null" => [null,];
        yield "environment" => [["foo" => "bar",],];
    }

    /**
     * Test setEnvironment() throws if used while process is running.
     * @dataProvider dataForTestSetEnvironment5
     */
    public function testSetEnvironment5(?array $env): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The environment can't be set for a running process");
        $process = new Process("php", ["-r", "'sleep(2);'",]);
        $process->start();
        $process->setEnvironment($env);
    }

    /** Ensure the process starts successfully. */
    public function testStart1(): void
    {
        $process = new Process(
            "php",
            ["-r", "'echo \"Done\";'",],
        );

        self::assertEquals(true, $process->start(), "The process did not start.");
        $process->wait();
    }

    /** Ensure start() throws when the process is not valid. */
    public function testStart2(): void
    {
        $process = new Process("");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Can't start a process with no command");
        $process->start();
    }

    /** Test processes stop as expected. */
    public function testStop1(): void
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

    public static function dataForTestExitCode1(): iterable
    {
        yield "zero" => ["php", ["-r", "'exit(0);'"], 0];
        yield "non-zero" => ["php", ["-r", "'exit(105);'"], 105];
        exit();
    }

    /**
     * Ensure we can get the process's exit code.
     *
     * @dataProvider dataForTestExitCode1
     */
    public function testExitCode1(string $command, array $args, int $expectedExitCode): void
    {
        $process = new Process(
            $command,
            $args,
        );

        self::assertTrue($process->start(), "The process did not start.");
        $process->wait();
        self::assertEquals($expectedExitCode, $process->exitCode(), "The expected exit code was not produced.");
    }

    /** Ensure we get notified of writes to the process's output streams. */
    public function testStdio1(): void
    {
        $processStdOut = "";
        $processStdErr = "";
        $process = new Process("php", ["-r", "'fprintf(STDOUT, \"stdout content\"); fprintf(STDERR, \"stderr content\");'"]);

        $process->setOutputNotifier(static function () use (&$processStdOut, $process): void {
            $processStdOut .= $process->readOutput();
        });

        $process->setErrorNotifier(static function () use (&$processStdErr, $process): void {
            $processStdErr .= $process->readErrorOutput();
        });

        self::assertTrue($process->start(), "The process did not start.");
        $process->wait();
        $process->checkOutput();
        self::assertEquals("stdout content", $processStdOut, "Process did not produce the expected output.");
        self::assertEquals("stderr content", $processStdErr, "Process did not produce the expected error output.");
    }

    /** Ensure we can get the PID while the process is running. */
    public function testPid1(): void
    {
        $process = new Process("php", ["-r", "'usleep(100000);'",]);
        $process->start();
        self::assertIsInt($process->pid(), "Pid is not valid.");
        $process->wait();
    }

    /** Ensure we get mnull for the PID when the process has finished. */
    public function testPid2(): void
    {
        $process = new Process("php", ["-r", "'usleep(100000);'",]);
        $process->start();
        $process->wait();
        self::assertNull($process->pid(), "PID for terminated process is not null.");
    }
}
