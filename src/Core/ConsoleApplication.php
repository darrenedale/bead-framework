<?php

namespace Bead\Core;

use Bead\Exceptions\ServiceAlreadyBoundException;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use StdClass;

abstract class ConsoleApplication extends Application
{
    /**
     * @var int A named option.
     *
     * E.g. `php command.php --bead "framework"` will give the argument "bead" the value "framework"
     */
    private const Option = 0;

    /**
     * @var int An argument
     *
     * E.g. php command.php "framework" will provide the argument "framework" to the command. Arguments of this type are
     * assigned the values given on the command-line in the order they are configured.
     */
    private const Argument = 2;

    /**
     * @var int A command-line switch/flag.
     */
    private const Flag = 3;

    // types for arguments
    public const TypeAny = 0;

    public const TypeString = 1;

    public const TypeInt = 2;

    public const TypeFloat = 3;

    public const TypeArray = 4;

    private ?string $m_cmd = null;

    /** @var array The raw command-line arguments. */
    private array $m_args;

    /** @var string The console application's description. */
    private string $m_description = "";

    /** @var \StdClass[] */
    private array $m_parameterDefinitions = [];

    /** @var array<string,string|bool> The values passed on the command-line for arguments. */
    private array $m_parameterValues = [];

    /** @var resource The console application's output stream. */
    private $m_out = STDOUT;

    /** @var resource The console application's error stream. */
    private $m_err = STDERR;

    /** @var resource The console application's input stream. */
    private $m_in = STDIN;

    /**
     * @param string $rootDir
     * @param array|null $args
     *
     * @throws RuntimeException if the singleton is already set or the root dir does not exist and cannot be created.
     * @throws ServiceAlreadyBoundException if any of the service bindings set up by the Application is already bound.
     */
    public function __construct(string $rootDir, array $args = null)
    {
        parent::__construct($rootDir);
        $this->addFlag("help", "h", "Show the command's help message.", false);

        if (!isset($args)) {
            $args = [];
        }

        $this->m_cmd  = array_shift($args);
        $this->m_args = $args;
    }

    /** Check whether a parameter name is valid. */
    private static function isValidParameterName(string $name): bool
    {
        // must be at least 2 valid chars and start with alpha
        return (bool) preg_match("/^[a-zA-Z][a-zA-Z0-9_-]+$/", $name);
    }

    /** Check whether a parameter short name is valid. */
    private static function isValidShortParameterName(string $name): bool
    {
        return (bool) preg_match("/^[a-z]$/", $name);
    }

    /** Check whether a data type is valid. */
    private static function isValidDataType(int $type): bool
    {
        return match ($type) {
            self::TypeString, self::TypeFloat, self::TypeInt, self::TypeArray, self::TypeAny => true,
            default => false,
        };
    }

    /**
     * Extract the name from a command-line argument.
     *
     * Detects whether the argument is an option or flag name (e.g. --bead) or short name (e.g. -b), removes the -- or -
     * prefix, and returns the (short) name of the option or flag.
     *
     * If the given argument is not a valid flag or option name, an exception is thrown. The flag or option does not
     * have to actually exist, it just needs to be a valid (short) name.
     */
    private static function extractName(string $optionOrFlag): string
    {
        return match (true) {
            str_starts_with($optionOrFlag, "--") => substr($optionOrFlag, 2),
            str_starts_with($optionOrFlag, "-") => substr($optionOrFlag, 1),
            default => throw new LogicException("Expected option or flag, found \"{$optionOrFlag}\""),
        };
    }

    /**
     * Helper to support short-format short flags (e.g. `-bfn` instead of `-b -f -n`).
     *
     * Any argument that is not a short-format flag is passed through intact.
     *
     * @param array $arguments The command-line arguments to process.
     *
     * @return array The expanded command-line arguments.
     */
    private static function expandFlags(array $arguments): array
    {
        $ret = [];

        foreach ($arguments as $arg) {
            if (str_starts_with("-", $arg) && !str_starts_with("--", $arg) && 2 < strlen($arg)) {
                foreach (str_split(substr($arg, 1)) as $flag) {
                    $ret[] = "-{$flag}";
                }
            } else {
                $ret[] = $arg;
            }
        }

        return $ret;
    }

    /** Check whether a parameter's name has already been defined. */
    protected final function nameIsDefined(string $name): bool
    {
        foreach ($this->m_parameterDefinitions as $definition) {
            if ($definition->name === $name) {
                return true;
            }
        }

        return false;
    }

    /** Check whether a parameter's short name has already been defined. */
    protected final function shortNameIsDefined(string $name): bool
    {
        assert (1 === strlen($name), new LogicException("Invalid short name \"{$name}\" provided to shortNameIsDefined() helper."));

        foreach ($this->m_parameterDefinitions as $definition) {
            if ($definition->shortName === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Given a command-line argument, attempt to locate the definition it matches.
     *
     * @param string $name The command-line argument.
     *
     * @return StdClass|null
     */
    private function identifyArgument(string $name): StdClass|null
    {
        // flags and options can start with - or --
        $couldBeFlagOrOption = str_starts_with($name, "-");

        if ($couldBeFlagOrOption) {
            $name = self::extractName($name);
        };

        $definition = $this->m_parameterDefinitions[$name] ?? null;

        if (!$definition) {
            return null;
        }

        if ($couldBeFlagOrOption) {
            return match ($definition->type) {
                self::Flag, self::Option => $definition,
                default => null,
            };
        }

        return $definition->type === self::Argument ? $definition : null;
    }

    /**
     * Parse the command-line arguments.
     *
     * The command-line arguments are assigned to defined options, arguments and flags.
     *
     * @throws InvalidArgumentException if we don't know what to do with one or more command-line arguments.
     */
    protected function parseArguments(): void
    {
        $args = self::expandFlags($this->arguments());
        $count = count($args);

        for ($idx = 0; $idx < $count; ++$idx) {
            $arg = $args[$idx];
            $definition = $this->identifyArgument($arg);

            if (null === $definition) {
                throw new InvalidArgumentException("Command-line argument {$arg} is not recognised.");
            }

            $value = null;

            switch ($definition->type) {
                case self::Flag:
                    $value = true;
                    break;

                case self::Option:
                    ++$idx;
                    $value = $args[$idx] ?? null;
                    break;

                case self::Argument:
                    $value = $args[$idx];
                    break;
            }

            if (null === $value) {
                throw new InvalidArgumentException("Command-line parameter {$arg} expects a value but none was given.");
            }

            // flags don't have dataType set
            if (self::TypeArray === ($definition->dataType ?? null)) {
                if (!array_key_exists($definition->name, $this->m_parameterValues)) {
                    $this->m_parameterValues[$definition->name] = [];
                }

                $this->m_parameterValues[$definition->name][] = $value;

                if (null !== ($definition->shortName ?? null)) {
                    if (!array_key_exists($definition->shortName, $this->m_parameterValues)) {
                        $this->m_parameterValues[$definition->shortName] = [];
                    }

                    $this->m_parameterValues[$definition->shortName][] = $value;
                }
            } elseif (array_key_exists($definition->name, $this->m_parameterValues)) {
                throw new InvalidArgumentException("Command-line parameter {$arg} was given more than once, but it doesn't accept multiple values");
            } else {
                $this->m_parameterValues[$definition->name] = $value;

                if (null !== ($definition->shortName ?? null)) {
                    $this->m_parameterValues[$definition->shortName] = $value;
                }
            }
        }
    }

    /**
     * Ensure the parsed arguments represent a valid set of values for the command.
     *
     * Ensures that mandatory options, arguments and flags are present and values are of the required type.
     *
     * @throws InvalidArgumentException if the set of arguments is not valid.
     */
    protected function validateArguments(): void
    {
        foreach ($this->m_parameterDefinitions as $definition) {
            if (self::Flag === $definition->type) {
                continue;
            }

            if (!array_key_exists($definition->name, $this->m_parameterValues)) {
                if ($definition->optional ?? true) {
                    continue;
                }

                $name = match ($definition->type) {
                    self::Option, self::Flag => "--{$definition->name}",
                    default => $definition->name,
                };

                if (null !== ($definition->shortName ?? null)) {
                    $name .= "|" . match ($definition->type) {
                            self::Option, self::Flag => "-{$definition->shortName}",
                            default => $definition->shortName,
                        };
                }

                throw new InvalidArgumentException("Command-line argument {$name} is required but was not given.");
            }

            $validated = null;

            switch ($definition->dataType) {
                case self::TypeAny:
                case self::TypeString:
                case self::TypeArray:
                    // parsing takes care of ensuring values are arrays where required
                    $validated = $this->m_parameterValues[$definition->name];
                    break;

                case self::TypeInt:
                    $validated = filter_var($this->m_parameterValues[$definition->name], FILTER_VALIDATE_INT, ["flags" => FILTER_NULL_ON_FAILURE,]);
                    break;

                case self::TypeFloat:
                    $validated = filter_var($this->m_parameterValues[$definition->name], FILTER_VALIDATE_FLOAT, ["flags" => FILTER_NULL_ON_FAILURE,]);
                    break;
            }

            if (null === $validated) {
                $name = match ($definition->type) {
                    self::Option, self::Flag => "--{$definition->name}",
                    default => $definition->name,
                };

                if (null !== ($definition->shortName ?? null)) {
                    $name .= "|" . match ($definition->type) {
                            self::Option, self::Flag => "-{$definition->shortName}",
                            default => $definition->shortName,
                        };
                }

                throw new InvalidArgumentException("Command-line value for argument {$name} is not of the correct type.");
            }

            $this->m_parameterValues[$definition->name] = $validated;

            // arguments don't have short names
            if (null !== ($definition->shortName ?? null)) {
                $this->m_parameterValues[$definition->shortName] = $validated;
            }
        }
    }

    /**
     * Set the command's description.
     *
     * The description should be a single-line summary of the command.
     *
     * @param string $description The description.
     */
    protected final function setDescription(string $description): void
    {
        $description = trim($description);

        if ("" === $description) {
            throw new LogicException("Expecting non-empty command descriptions, found \"{$description}\".");
        }

        $this->m_description = $description;
    }

    /**
     * Add an option-type parameter for the command.
     *
     * Option type parameters are named and can be typed. For example, defining a parameter named "bead" will expect
     * the command-line to contain a `--bead` option with a value (e.g. `--bead "framework"`.). Options can be marked
     * as optional, meaning the command-line need not contain the option. Options can also be typed, meaning the value
     * provided on the command-line for the option must be a valid string representation of a given type. See the class
     * constants for the supported types. Typing an option as an array means it can be provided more than once. Options
     * can also have a short-name equivalent. For example, defining an option with the name "bead" and the short name
     * "b" will expect either `--bead` or `-b` on the command-line.
     *
     * Option names must not clash with flag or argument names. Short names must not clash with flag short names.
     *
     * @param string $name The option name.
     * @param string|null $shortName The option short name, if it has one.
     * @param string $description The option description.
     * @param int $type The option type. Must be one of the class data type constants. Default is `TypeAny`.
     * @param bool $optional Whether the option is optional. Default is `false`.
     * @param string|float|int|array|null $default The default value for the option. If not given, `null` is used.
     */
    protected final function addOption(string $name, ?string $shortName = null, string $description = "", int $type = self::TypeAny, bool $optional = false, string|float|int|array|null $default = null): void
    {
        if (!self::isValidDataType($type)) {
            throw new LogicException("Expected valid data type, found {$type}");
        }

        if (!self::isValidParameterName($name)) {
            throw new LogicException("Expected valid parameter name, found {$name}");
        }

        if (null !== $shortName && !self::isValidShortParameterName($shortName)) {
            throw new LogicException("Expected valid short parameter name, found {$shortName}");
        }

        if ("" === trim($description)) {
            throw new LogicException("Expected non-empty parameter description, found {$description}");
        }

        if ($this->nameIsDefined($name)) {
            throw new LogicException("Option name {$name} is already defined.");
        }

        if (null !== $shortName && !self::isValidShortParameterName($shortName)) {
            throw new LogicException("Option short name {$name} is already defined.");
        }

        $definition = (object) [
            "type" => self::Option,
            "name" => $name,
            "shortName" => $shortName,
            "dataType" => $type,
            "description" => $description,
            "optional" => $optional,
            "default" => $default,
        ];

        $this->m_parameterDefinitions[$name] = $definition;

        if (null !== $shortName) {
            $this->m_parameterDefinitions[$shortName] = $definition;
        }
    }

    /**
     * Add an argument-type parameter for the command.
     *
     * Argument type parameters are not named, and are just arguments provided on the command-line. Arguments from the
     * command line that aren't an option or flag are assigned to defined arguments in the order in which they are
     * configured. Arguments can be typed. For example, defining an argument named "bead" will expect the command-line
     * to contain at least one argument. Arguments can be marked as optional, meaning the command-line need not contain
     * the argument. After the first optional argument, all subsequent arguments must also be optional.
     *
     * Argument names must not clash with flag or option names.
     *
     * @param string $name The argument name.
     * @param string $description The argument description.
     * @param int $type The argument type. Must be one of the class data type constants. Default is `TypeAny`.
     * @param bool $optional Whether the argument is optional. Default is `false`.
     * @param string|float|int|array|null $default The default value for the argument. If not given, `null` is used.
     */
    protected final function addArgument(string $name, string $description, int $type = self::TypeAny, bool $optional = false, string|float|int|array $default = null): void
    {
        if ("" === trim($description)) {
            throw new LogicException("Expected non-empty argument description. found {$description}");
        }

        if ($this->nameIsDefined($name)) {
            throw new LogicException("Argument name {$name} is already defined.");
        }

        if (!self::isValidDataType($type)) {
            throw new LogicException("Expected valid data type, found {$type}");
        }

        $optionalArguments = array_filter(
            $this->m_parameterDefinitions,
            static fn (StdClass $definition): bool => self::Argument === $definition->type && true === $definition->optional
        );

        if (!$optional && 0 !== count($optionalArguments)) {
            throw new LogicException("Mandatory arguments cannot be defined after optional arguments.");
        }

        $this->m_parameterDefinitions[$name] = (object) [
            "type" => self::Argument,
            "name" => $name,
            "dataType" => $type,
            "description" => $description,
            "optional" => $optional,
            "default" => $default,
        ];
    }


    /**
     * Add a flag-type parameter for the command.
     *
     * Flags represent features that can be on or off. They don't require values; instead they have a command-line
     * argument that turns the feature on, and (optionally) another that turns it off. Flags that are negatable in this
     * way will have the negative version of the flag auto-generated in this way:
     * - The provided flag name will be prefixed with "not-";
     * - If a short name is provided and it's lower-case, it be upper-cased; otherwise the negative of the flag won't
     *   have a short name.
     *
     * Flag names must not clash with option or argument names. Short names must not clash with option short names.
     * These include the negatable versions, if generated.
     *
     * @param string $name The flag name.
     * @param string|null $shortName The flag short name, if it has one.
     * @param string $description The option description.
     * @param bool $negatable Whether the flag is negatable.
     * @param bool $default The default state for the flag. If not given, `false` is used.
     */
    protected final function addFlag(string $name, ?string $shortName = null, string $description = "", bool $negatable = true, bool $default = false): void
    {
        if (!self::isValidParameterName($name)) {
            throw new LogicException("Expected valid flag name. found {$name}");
        }

        if (null !== $shortName && !self::isValidShortParameterName($shortName)) {
            throw new LogicException("Expected valid short flag name. found {$shortName}");
        }

        if ("" === trim($description)) {
            throw new LogicException("Expected non-empty flag description. found {$description}");
        }

        if ($this->nameIsDefined($name)) {
            throw new LogicException("Flag name {$name} is already defined.");
        }

        if ($negatable) {
            $notName = "not-{$name}";

            if ($this->nameIsDefined($notName)) {
                throw new LogicException("Flag name {$notName} is already defined.");
            }
        }

        if (null !== $shortName) {
            if ($this->shortNameIsDefined($shortName)) {
                throw new LogicException("Flag short name {$shortName} is already defined.");
            }

            $notShortName = null;

            if ($negatable && ctype_lower($shortName)) {
                $notShortName = strtoupper($shortName);

                if ($this->shortNameIsDefined($notShortName)) {
                    throw new LogicException("Flag short name {$notShortName} is already defined.");
                }
            }
        }

        $definition = (object) [
            "type" => self::Flag,
            "name" => $name,
            "shortName" => $shortName,
            "description" => $description,
            "default" => $default,
        ];

        $this->m_parameterDefinitions[$name] = $definition;

        if (null !== $shortName) {
            $this->m_parameterDefinitions[$shortName] = $definition;
        }

        if ($negatable) {
            $notDefinition = (object)[
                "type" => self::Flag,
                "name" => $notName,
                "shortName" => $notShortName,
                "description" => $description,
                "default" => !$default,
            ];

            $this->m_parameterDefinitions[$notName] = $notDefinition;

            if (null !== $notShortName) {
                $this->m_parameterDefinitions[$notShortName] = $notDefinition;
            }
        }
    }

    /** Get the command's description. */
    public function description(): string
    {
        return $this->m_description;
    }

    /** Show the auto-generated help for the command. */
    protected function showHelp(): void
    {
        $this->line("{$this->executedScript()}: {$this->description()}");

        $shortFlags = array_filter(
            $this->m_parameterDefinitions,
            static fn (StdClass $definition, string $key): bool => 1 === strlen($key) && self::Flag === $definition->type,
            ARRAY_FILTER_USE_BOTH
        );

        $flags = "";

        if (0 < count($shortFlags)) {
            $flags .= "-" . implode(
                    "",
                    array_map(
                        static fn(StdClass $definition): string => $definition->shortName,
                        $shortFlags,
                    )
                );
        }

        $flags .= " " . implode(
                " ",
                array_map(
                    static fn(StdClass $definition): string => "--{$definition->name}",
                    array_filter(
                        $this->m_parameterDefinitions,
                        static fn (StdClass $definition, string $key): bool => 1 < strlen($key) && self::Flag === $definition->type,
                        ARRAY_FILTER_USE_BOTH,
                    )
                )
            );

        $options = implode(
            " ",
            array_map(
                static function (StdClass $definition): string {
                    $ret = "--{$definition->longName}";

                    if (null !== $definition->shortName) {
                        $ret .= "|-{$definition->shortName}";
                    }

                    if (null !== $definition->default) {
                        $ret = "={$definition->default}";
                    }

                    if ($definition->optional) {
                        $ret = "[{$ret}]";
                    }

                    return $ret;
                },
                array_filter(
                    $this->m_parameterDefinitions,
                    static fn (StdClass $definition, string $key): bool => 1 < strlen($key) && self::Option === $definition->type,
                    ARRAY_FILTER_USE_BOTH,
                )
            )
        );

        $arguments = implode(
            " ",
            array_map(
                static fn (StdClass $definition): string => ($definition->optional ? "[{$definition->name}]" : $definition->name),
                array_filter(
                    $this->m_parameterDefinitions,
                    static fn (StdClass $definition): bool => self::Argument === $definition->type,
                )
            )
        );

        $this->line("  {$flags} {$options} {$arguments}");
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
     *
     * @throws RuntimeException if the input stream cannot be read.
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
            throw new RuntimeException("Failed to read from input stream.");
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
     *
     * @throws RuntimeException if the input stream can't be read from without echoing the input.
     */
    public function readSecret(string $prompt = ""): string
    {
        if (STDIN !== $this->inStream()) {
            throw new RuntimeException("Input stream does not support hiding.");
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

    /**
     * @param string $prompt
     * @return bool
     *
     * @throws RuntimeException if the input stream cannot be read.
     */
    public function confirm(string $prompt): bool
    {
        $response = $this->read("{$prompt} [y|N] ", 1);
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
     * Fetch the executed script.
     *
     * This is the script name, exactly as typed by the user. This may be null (for example, if the command was invoked
     * programmatically).
     *
     * @return string The script name, or null if it could not be determined.
     */
    public function executedScript(): ?string
    {
        return $this->m_cmd;
    }

    /**
     * Fetch all the raw command-line arguments.
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
        } elseif (2 === strlen($name) && "-" === $name[0] && "-" !== $name[1]) {
            return $name;
        } elseif (str_starts_with($name, "--")) {
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
     * @return string|float|int|array|bool|null
     */
    public function argumentValue(string $name): string|float|int|array|bool|null
    {
        if (!array_key_exists($name, $this->m_parameterDefinitions)) {
            throw new RuntimeException("Argument {$name} is not defined.");
        }

        $value = $this->m_parameterValues[$name] ?? null;

        if (null === $value) {
            $value = $this->m_parameterDefinitions[$name]->default;

            if (null === $value) {
                throw new RuntimeException("Argument {$name} is not set.");
            }
        }

        return $value;
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
        $this->configure();
        $this->parseArguments();
        $this->validateArguments();

        if ((bool) $this->argumentValue("help")) {
            $this->showHelp();
            return 0;
        }

        return $this->run();
    }

    /**
     * Implement in Command classes to run the command.
     *
     * When this method is called, the command will have been configured and its command-line arguments parsed and
     * validated. You don't need to handle the "help" command-line arg, this is already handled and run() won't be
     * called if the help argument is specified on the command-line.
     */
    protected abstract function run(): int;

    /**
     * Reimplement this to configure the command.
     *
     * Set the command's expected command-line arguments and description here.
     */
    protected function configure(): void
    {}
}
