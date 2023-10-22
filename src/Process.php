<?php

declare(strict_types=1);

namespace Bead;

use Closure;
use RuntimeException;
use InvalidArgumentException;
use Bead\Facades\Log;

use function Bead\Helpers\Iterable\all;

/**
 * Encapsulation of an external process.
 */
class Process
{
    /** @var int How many microseconds to wait between polling during timeouts. */
    private const TimeoutPollInterval = 1000;

    /** @var int The default timeout when cleaninup up a running process. */
    public const DefaultCleanupTimeout = 30;

    /** @var int When cleaning up a running process, how long to wait for terminate_proc() to work before giving up. */
    private static int $cleanupTimeout = self::DefaultCleanupTimeout;

    /** @var int The index of the input stream in $m_streams. */
    protected const InStream = 0;

    /** @var int The index of the output stream in $m_streams. */
    protected const OutStream = 1;

    /** @var int The index of the error stream in $m_streams. */
    protected const ErrStream = 2;

    /** @var string The process command. */
    private string $m_command;

    /** @var array<string|int|float> The arguments for the command. */
    private array $m_arguments;

    /** @var string The process working directory. */
    private string $m_workingDirectory;

    /** @var array<string,string|float|int>|null The process environment. */
    private ?array $m_environment = null;

    /** @var resource|null The process handle, `null` while the process is not running. */
    private $m_proc = null;

    /** @var int|null The process's PID while running. */
    private ?int $m_pid = null;

    /** @var int|null The process exit code. `null` until the process has finished. */
    private ?int $m_exitCode = null;

    /** @var array|null[] The input, output and error stream resources. */
    private array $m_streams = [null, null, null,];

    /** @var Closure|null The callback to notify when output is available. */
    private ?Closure $m_outputNotifier;

    /** @var Closure|null The callback to notify when error output is available. */
    private ?Closure $m_errorNotifier;

    /**
     * Initialise a new DriverProcess with a given command-line and working directory.
     * @param string $command The command to run.
     * @param array<string|int|float> $args The command line arguments.
     * @param string|null $workingDirectory The working directory in which to run the command.
     */
    public function __construct(string $command, array $args = [], ?string $workingDirectory = null, ?Closure $outputNotifier = null, ?Closure $errorNotifier = null)
    {
        $this->setCommand($command);
        $this->setArguments($args);
        $this->setWorkingDirectory($workingDirectory);
        $this->setOutputNotifier($outputNotifier);
        $this->setErrorNotifier($errorNotifier);
    }

    /**
     * Destroy the process.
     */
    public function __destruct()
    {
        // isRunning() calls checkStatus() which does all necessary cleanup
        if ($this->isRunning()) {
            proc_terminate($this->m_proc);
            $giveUp = microtime(true) + static::cleanupTimeout();

            while ($this->isRunning() && $giveUp > microtime(true)) {
                // sleep for 1/1000s before checking again
                usleep(self::TimeoutPollInterval);
            }

            if ($this->isRunning()) {
                Log::error("Process {$this->pid()} [{$this->command()}] could not be stopped.");
            }
        }
    }

    /**
     * Helper to build a command line from a command and arguments.
     *
     * @param string $command The command
     * @param array<string|float|int> $args The command-line arguments.
     *
     * @return string The command line.
     */
    protected static function buildCommandLine(string $command, array $args): string
    {
        array_walk($args, fn (& $arg, $key) => escapeshellarg($arg));
        return escapeshellcmd($command) . " " . implode(" ", $args);
    }

    /**
     * Fetch the global timeout used when cleaning up running processes.
     *
     * @return int The timeout.
     */
    public static function cleanupTimeout(): int
    {
        return static::$cleanupTimeout;
    }

    /**
     * Set the global timeout used when cleaning up running processes.
     *
     * The object will wait this many seconds for the process to exit after proc_terminate() before giving up. By
     * default this is 30 seconds.
     *
     * @param int|null $timeout The timeout in seconds or `null` to revert to the default.
     */
    public static function setCleanupTimeout(?int $timeout): void
    {
        if (isset($timeout)) {
            if (0 > $timeout) {
                throw new \InvalidArgumentException("The cleanup timeout must be >= 0.");
            }

            static::$cleanupTimeout = $timeout;
        } else {
            static::$cleanupTimeout = self::DefaultCleanupTimeout;
        }
    }

    /**
     * Refresh the status of the process.
     */
    final protected function refreshStatus(): void
    {
        if (!$this->m_proc) {
            return;
        }

        $status = proc_get_status($this->m_proc);

        if (!$status["running"]) {
            // ensure all output has a chance of being read
            $this->checkOutput();
            $this->closeStreams();
            proc_close($this->m_proc);
            $this->m_proc = null;
            $this->m_pid = null;
            $this->m_exitCode = $status["exitcode"];
        }
    }

    /**
     * Helper to close the process's input and output streams.
     */
    final protected function closeStreams(): void
    {
        foreach ($this->m_streams as $pipe) {
            if ($pipe) {
                fclose($pipe);
            }
        }

        $this->m_streams = [null, null, null];
    }

    /**
     * Set the command of the process.
     *
     * THe command can't be set while the process is running.
     *
     * @param string $command The command to set.
     *
     * @throws RuntimeException if the process is running.
     */
    public function setCommand(string $command): void
    {
        if ($this->isRunning()) {
            throw new RuntimeException("Can't change the command of a running process.");
        }

        $this->m_command = $command;
    }

    /**
     * Fetch the command line of the process.
     *
     * @return string The command line.
     */
    public function command(): string
    {
        return $this->m_command;
    }

    /**
     * Set the command line arguments of the process.
     *
     * The command-line arguments can't be set while the process is running.
     *
     * @param array<string|float|int> $arguments The command line arguments to set.
     *
     * @throws RuntimeException if the process is running.
     */
    public function setArguments(array $arguments): void
    {
        if ($this->isRunning()) {
            throw new RuntimeException("Can't change the command-line arguments of a running process.");
        }

        if (!all($arguments, fn ($arg): bool => is_string($arg) || is_int($arg) || is_float($arg))) {
            throw new InvalidArgumentException("All arguments must be strings or numbers.");
        }

        $this->m_arguments = $arguments;
    }

    /**
     * Fetch the command-line arguments of the process.
     *
     * @return array<string|int|float> The command line arguments.
     */
    public function arguments(): array
    {
        return $this->m_arguments;
    }

    /**
     * Fetch the command-line of the process.
     *
     * @return string The command-line.
     */
    public function commandLine(): string
    {
        return static::buildCommandLine($this->command(), $this->arguments());
    }

    /**
     * Set the working directory for the process.
     *
     * @param string|null $path The working directory. Provide `null` to reset to the PHP process's working directory.
     */
    public function setWorkingDirectory(?string $path): void
    {
        if ($this->isRunning()) {
            throw new RuntimeException("Can't change the working directory of a running process.");
        }

        $this->m_workingDirectory = $path ?? getcwd();
    }

    /**
     * Fetch the working directory for the process.
     *
     * @return string The working directory.
     */
    public function workingDirectory(): string
    {
        return $this->m_workingDirectory;
    }

    /**
     * Set the environment for the process.
     *
     * The environment variable names must all be strings and values must all be strings, ints or floats. ints and
     * floats will be converted to strings before being set. The caller is responsible for ensuring that all variable
     * names are valid shell variable names.
     *
     * @param array<string,string|int|float>|null $env
     */
    public function setEnvironment(?array $env): void
    {
        if ($this->isRunning()) {
            throw new RuntimeException("The environment can't be set for a running process.");
        }

        if (!isset($env)) {
            $this->m_environment = null;
            return;
        }

        foreach ($env as $key => &$value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException("Environment keys must be strings.");
            }

            if (is_string($value)) {
                continue;
            }

            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException("Environment values must be strings or numbers.");
            }

            $value = "{$value}";
        }

        $this->m_environment = $env;
    }

    /**
     * The environment for the process.
     *
     * The default is `null` to inherit the environment from the PHP process.
     *
     * @return array|null The environment, or `null` if it's inherited.
     */
    public function environment(): ?array
    {
        return $this->m_environment;
    }

    /**
     * Set the callback to be called when output is available.
     *
     * @param \Closure|null $notifier The callback, or `null` to remove any existing callback.
     */
    public function setOutputNotifier(?Closure $notifier): void
    {
        $this->m_outputNotifier = $notifier;
    }

    /**
     * Set the callback to be called when error output is available.
     *
     * @param \Closure|null $notifier The callback, or `null` to remove any existing callback.
     */
    public function setErrorNotifier(?Closure $notifier): void
    {
        $this->m_errorNotifier = $notifier;
    }

    /**
     * Check whether there is any output to read.
     *
     * If there is output or error output, the appropriate notifier closure(s) will be called with this process as the
     * argument. The closure can then read the appropriate stream using readOutput() or readErrorOutput().
     *
     * Since PHP has no event loop, you should call this periodically to ensure your notifiers are called when
     * necessary.
     */
    public function checkOutput(): void
    {
        // no notifiers, no need to do anything
        if (!$this->m_proc || !$this->m_outputNotifier && !$this->m_errorNotifier) {
            return;
        }

        $availableStreams = $this->pollOutputStreams();

        if ($this->m_outputNotifier && in_array(self::OutStream, $availableStreams)) {
            ($this->m_outputNotifier)($this);
        }

        if ($this->m_errorNotifier && in_array(self::ErrStream, $availableStreams)) {
            ($this->m_errorNotifier)($this);
        }
    }

    /**
     * Check whether the process is currently running.
     *
     * @return bool `true` if the process is running, `false` otherwise.
     */
    public function isRunning(): bool
    {
        $this->refreshStatus();
        return isset($this->m_proc);
    }

    /**
     * Fetch the process's PID.
     *
     * @return int|null The PID, or `null` if the process is not running.
     */
    public function pid(): ?int
    {
        $this->refreshStatus();
        return $this->m_pid;
    }

    /**
     * Fetch the process's exit code.
     *
     * @return int|null The exit code, or `null` if the process is still running.
     */
    public function exitCode(): ?int
    {
        $this->refreshStatus();
        return $this->m_exitCode;
    }

    /**
     * Write to the process's standard input stream.
     *
     * @param string $content The bytes to write.
     */
    public function writeStdin(string $content): void
    {
        if (!$this->m_proc) {
            return;
        }

        fwrite($this->m_streams[self::InStream], $content);
    }

    /**
     * Read either the output or error stream.
     *
     * @param int $stream The stream. Must be either OutStream or ErrorStream.
     * @param int|null $len The optional maximum number of bytes to read.
     *
     * @return string|null The bytes read.
     */
    protected function readStream(int $stream, ?int $len = null): ?string
    {
        if (isset($len)) {
            $ret = fgets($this->m_streams[$stream], $len);
        } else {
            $ret = fgets($this->m_streams[$stream]);
        }

        if (false === $ret) {
            return null;
        }

        return $ret;
    }

    /**
     * Read any available content from the process's standard output stream.
     *
     * @param int|null $len The maximum number of bytes to read.
     *
     * @return string|null The bytes, or `null` if the process is not running or the output stream can't be read.
     */
    public function readOutput(?int $len = null): ?string
    {
        if (!$this->m_proc) {
            return null;
        }

        return $this->readStream(self::OutStream, $len);
    }

    /**
     * Read any available content from the process's standard error stream.
     *
     * @param int|null $len The maximum number of bytes to read.
     *
     * @return string|null The bytes, or `null` if the process is not running or the error stream can't be read.
     */
    public function readErrorOutput(?int $len = null): ?string
    {
        if (!$this->m_proc) {
            return null;
        }

        return $this->readStream(self::ErrStream, $len);
    }

    /**
     * Start the process.
     *
     * Throws if the process is already running.
     *
     * @return bool `true` if the process was started successfully, `false` if not.
     */
    public function start(): bool
    {
        if ($this->isRunning()) {
            throw new RuntimeException("Process '{$this->command()}' is already running (PID {$this->pid()}).");
        }

        if (empty($this->command())) {
            throw new RuntimeException("Can't start a process with no command.");
        }

        $this->m_exitCode = null;
        $this->m_proc = proc_open(
            $this->commandLine(),
            [
                ["pipe", "r"],
                ["pipe", "w"],
                ["pipe", "w"],
            ],
            $this->m_streams,
            $this->workingDirectory(),
            $this->environment()
        );

        if (false === $this->m_proc) {
            $this->m_proc = null;
            $this->closeStreams();
            return false;
        }

        $status = proc_get_status($this->m_proc);
        $this->m_pid = $status["pid"];
        return true;
    }

    /**
     * Request the process to stop.
     *
     * This is non-blocking - check isRunning() to see if/when it actually stops.
     */
    public function stop(): void
    {
        if ($this->isRunning()) {
            proc_terminate($this->m_proc);
        }
    }

    /**
     * Wait for the process to terminate.
     *
     * If the timeout expires the call returns and the process continues running.
     *
     * @param int|null $timeout How long to wait before returning if the process does not terminate.
     */
    public function wait(?int $timeout = null): void
    {
        if (!$this->isRunning()) {
            throw new RuntimeException("The process is not running.");
        }

        if (0 > $timeout) {
            throw new \InvalidArgumentException("The timeout must be >= 0.");
        }

        $timeout = (isset($timeout) ? microtime(true) + $timeout : null);

        while ($this->isRunning() && (!isset($timeout) || microtime(true) < $timeout)) {
            usleep(self::TimeoutPollInterval);
        }
    }

    /**
     * Wait for the process to terminate and terminate it if it doesn't.
     *
     * If the timeout expires the process is stopped before returning. Note that in this case it may be more than
     * `$timeout` seconds before the call returns because the method will wait for the process to actually terminate (up
     * to the timeout specified by `cleanupTimeout()`).
     *
     * @param int $timeout How long to wait before terminating the process.
     */
    public function waitOrStop(int $timeout): void
    {
        if (!$this->isRunning()) {
            throw new RuntimeException("The process is not running.");
        }

        if (0 > $timeout) {
            throw new \InvalidArgumentException("The timeout must be >= 0.");
        }

        $timeout += microtime(true);

        while ($this->isRunning() && microtime(true) < $timeout) {
            usleep(self::TimeoutPollInterval);
        }

        if ($this->isRunning()) {
            $this->stop();

            while ($this->isRunning() && microtime(true) < $timeout) {
                usleep(self::TimeoutPollInterval);
            }
        }
    }

    /**
     * Check the process's streams for any available output to read.
     *
     * The returned array will contain either OutStream, ErrStream, both or neither (i.e. an empty array), depending on
     * which of the two streams have output available.
     *
     * @return array The streams that contain some data ready for reading.
     */
    final protected function pollOutputStreams(): array
    {
        $read = [$this->m_streams[self::OutStream], $this->m_streams[self::ErrStream]];
        $write = null;
        $except = null;

        // TODO need to set streams to non-blocking?
        set_error_handler(function () {
            return true;
        });
        $result = @stream_select($read, $write, $except, 0, 0);
        restore_error_handler();

        if (false === $result || 0 === $result) {
            return [];
        }

        return array_filter([self::OutStream, self::ErrStream], fn ($stream): bool => in_array($this->m_streams[$stream], $read));
    }

    /**
     * Wait for some data to become available in the process's output stream.
     *
     * @param int|null $timeout How long to wait before returning if no data is available.
     *
     * @return bool `true` if there's data available, `false` if the timeout expired.
     */
    public function waitForOutput(?int $timeout = null): bool
    {
        $timeout = (isset($timeout) ? microtime(true) + $timeout : null);

        do {
            if (in_array(self::OutStream, $this->pollOutputStreams())) {
                return true;
            }

            usleep(self::TimeoutPollInterval);
        } while (!isset($timeout) || $timeout < microtime(true));

        return false;
    }

    /**
     * Wait for some data to become available in the process's output stream.
     *
     * @param int|null $timeout How long to wait before returning if no data is available.
     *
     * @return bool `true` if there's data available, `false` if the timeout expired.
     */
    public function waitForErrorOutput(?int $timeout = null): bool
    {
        $timeout = (isset($timeout) ? microtime(true) + $timeout : null);

        do {
            if (in_array(self::ErrStream, $this->pollOutputStreams())) {
                return true;
            }

            usleep(self::TimeoutPollInterval);
        } while (!isset($timeout) || $timeout < microtime(true));

        return false;
    }
}
