<?php

namespace Equit;

class ConsoleApplication extends Application
{
    private ?string $m_cmd;
    private array $m_args;
    private $m_out = STDOUT;
    private $m_err = STDERR;
    private $m_in = STDIN;

    public function __construct(string $rootDir, array $args = null)
    {
        parent::__construct($rootDir);

        if (!isset($args)) {
            $args = [];
        }

        $this->m_cmd  = array_shift($args);
        $this->m_args = $args;
    }

    /**
     * Helper to write some text to a given stream.
     *
     * @param string $text The text to write.
     * @param resource $stream The stream to which to write it.
     */
    protected function write(string $text, $stream): void
    {
        fputs($stream, $text);
    }

    public function errorLine(string $line): void
    {
        $this->write("\033[31m{$line}\033[39m\n", $this->errorStream());
    }

    public function line(string $line): void
    {
        $this->write("{$line}\n", $this->outStream());
    }

    /**
     * Read from the input stream.
     *
     * You can optionally specify a maximum length. If provided, no more than this many characters will be read. The
     * read may still be shorter, if the user presses enter before the maximum number of characters.
     *
     * @param string $prompt The optional prompt to display on the output stream prior to reading.
     * @param int|null $maxLen The maximum number of characters to read.
     *
     * @return string The input.
     */
    public function read(string $prompt = "", ?int $maxLen = null): string
    {
        $this->write($prompt, $this->outStream());

        if (is_int($maxLen) && 0 < $maxLen) {
            $line = fgets($this->inStream(), $maxLen + 1);
        } else {
            $line = fgets($this->inStream());
        }

        if (false === $line) {
            throw new \RuntimeException("Failed to read from input stream.");
        }

        if (str_ends_with($line, "\n")) {
            $line = substr($line, 0, -1);
        }

        return $line;
    }

    /**
     * Read some input from the input stream.
     *
     * If the input stream is interactive, the user's input will not be echoed as they type. This is useful for
     * gathering passwords, for example, from users.
     *
     * This only works on *nix-like TTYs, and an exception will be thrown if secrecy can't be guaranteed..
     *
     * @param string $prompt The optional prompt to display to the user.
     *
     * @return string The provided secret input.
     */
    public function readSecret(string $prompt = ""): string
    {
        if (STDIN !== $this->inStream()) {
            throw new \RuntimeException("Input stream does not support hiding.");
        }

        $mode = shell_exec("stty -g");
        shell_exec("stty -echo");
        $value = $this->read($prompt);
        shell_exec("stty {$mode}");

        if (STDOUT === $this->outStream()) {
            $this->write("\n", $this->outStream());
        }

        return $value;
    }

    public function confirm(string $prompt): bool
    {
        $response = $this->read("$prompt [y|N] ", 1);
        return "Y" === strtoupper($response);
    }

    /**
     * Set the output stream.
     *
     * @param $stream resource The open stream resource to use for output.
     */
    public function setOutStream($stream): void
    {
        assert("stream" === get_resource_type($stream), new \InvalidArgumentException("Invalid output stream - not a 'stream' resource."));
        $this->m_out = $stream;
    }

    /**
     * Fetch the output stream.
     *
     * @return resource The output stream.
     */
    public function outStream()
    {
        return $this->m_out;
    }

    /**
     * Set the error stream.
     *
     * @param $stream resource The open stream resource to use for error output.
     */
    public function setErrorStream($stream): void
    {
        assert("stream" === get_resource_type($stream), new \InvalidArgumentException("Invalid error stream - not a 'stream' resource."));
        $this->m_err = $stream;
    }

    /**
     * Fetch the error output stream.
     *
     * @return resource The error output stream.
     */
    public function errorStream()
    {
        return $this->m_err;
    }

    /**
     * Set the input stream.
     *
     * @param $stream resource The open stream resource to use for input.
     */
    public function setInStream($stream): void
    {
        assert("stream" === get_resource_type($stream), new \InvalidArgumentException("Invalid input stream - not a 'stream' resource."));
        $this->m_in = $stream;
    }

    /**
     * Fetch the input stream.
     *
     * @return resource
     */
    public function inStream()
    {
        return $this->m_in;
    }

    /**
     * Fetch all the command-line arguments.
     * @return array
     */
    public function arguments(): array
    {
        return $this->m_args;
    }

    /**
     * Normalise an argument name.
     *
     * Normalisation ensures that an argument is either a single-character argument preceded by a '-' (e.g. "-f") or a
     * multi- character argument preceded by "--" (e.g. "--foo"). Provide the argument either with or without the '-' or
     * "--" prefix and you'll get back the normalised form.
     *
     * @param string $name The argument to normalise.
     *
     * @return string The normalised form of the argument.
     */
    protected static function normalisedArgumentName(string $name): string
    {
        if (1 === strlen($name)) {
            return "-{$name}";
        } else if (2 === strlen($name) && "-" === $name[0] && "-" !== $name[1]) {
            return $name;
        } else if (str_starts_with($name, "--")) {
            return $name;
        }

        return "--{$name}";
    }

    /**
     * Determine if the console application is in debug mode.
     *
     * Debug mode is set if it's specified in the "app.debugmode" configuration item or "--debug" is provided as a
     * command-line argument.
     *
     * @return bool
     */
    public function isInDebugMode(): bool
    {
        return parent::isInDebugMode() || $this->hasArgument("--debug");
    }

    /**
     * Check whether a argument was provided when invoking the command.
     *
     * The argument name can be provided either with or without its preceding dashes. If it's a single character, it
     * will be checked as if it were prefixed with a single '-'; otherwise it will be checked as if it were prefixed
     * with '--'. You can also provide the prefixed argument name.
     *
     * @param string $name The name of the argument to check for.
     *
     * @return bool
     */
    public function hasArgument(string $name): bool
    {
        return in_array(self::normalisedArgumentName($name), $this->arguments());
    }

    /**
     * Fetch the value given for a command-line argument.
     *
     * This method does no validation against expectations - if the argument you're asking about is really a switch and
     * it's followed by another switch or argument name, you'll get the following switch or argument name as the value.
     * For example, if a command was invoked with --foo --bar, and both --foo and --bar are intended to be switches,
     * calling argumentValue("foo") will return "--bar" and argumentValue("bar") will return null.
     *
     * @param string $name The argument name.
     *
     * @return string|null
     */
    public function argumentValue(string $name): ?string
    {
        $args = $this->arguments();
        $name = self::normalisedArgumentName($name);

        for ($idx = 0; $idx < count($args) - 1; ++$idx) {
            if ($name === $args[$idx]) {
                return $args[$idx + 1];
            }
        }

        return null;
    }

    /**
     * Empty implementation of exec().
     *
     * An empty implementation is provided so that you can use instances of this class "externally" without having to
     * create a subclass. In most cases you'll probably want to create a subclass to properly encapsulate the command
     * functionality but for simple use cases you can just do something like:
     *
     * ```php
     * $app = new ConsoleApplication($argv);
     *
     * if (!$app->hasArgument("--foo")) {
     *     if ($app->isInDebugMode()) {
     *         $app->error("Some very detailed debug info.")
     *     }
     *
     *     $app->error("Foo was not specified.");
     *     exit (1);
     * }
     *
     * $app->line("Bar.");
     * exit (ConsoleApplication::ExitOk);
     * ```
     *
     * @return int Always ExitOk.
     */
    public function exec(): int
    {
        return self::ExitOk;
    }
}
