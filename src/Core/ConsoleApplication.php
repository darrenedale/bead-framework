<?php

namespace Bead\Core;

use Bead\Contracts\Logger as LoggerContract;
use Bead\Exceptions\ServiceAlreadyBoundException;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use StdClass;

/**
 * Abstract base class for framework applications that are intended to run on the command-line.
 */
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
    private const Argument = 1;

    /**
     * @var int A command-line switch/flag.
     */
    private const Flag = 2;

    /** @var int Any data type is accepted by the option/argument. */
    public const TypeAny = 0;

    /** @var int Any string is accepted by the option/argument. */
    public const TypeString = 1;

    /** @var int The value for the option/argument must be an integer. */
    public const TypeInt = 2;

    /** @var int The value for the option/argument must be an real number. */
    public const TypeFloat = 3;

    /** @var int The option/argument accepts multiple values of any type. */
    public const TypeArray = 4;

    /** @var string The script that was run to cause the command to be executed. */
    private string $m_cmd;

    /** @var array The raw command-line arguments. */
    private array $m_args;

    /** @var string The console application's description. */
    private string $m_description = "";

    /** @var \StdClass[] The defined flags, options and arguments. */
    private array $m_parameterDefinitions = [];

    /**
     * @var array<string,string|bool> The values passed on the command-line parsed into the configured parameters.
     *
     * These are always indexed by name, not short name. This applies to flags that are negatable - the value will be
     * false for the flag name (i.e. if you configure the flag "bead", if the user specifies --not-bead on the command
     * line, the value for "bead" will be false; there won't be a value for "not-bead").
     */
    private array $m_parameterValues = [];

    /** @var resource The console application's output stream. */
    private $m_out;

    /** @var resource The console application's error stream. */
    private $m_err;

    /** @var resource The console application's input stream. */
    private $m_in;

    /**
     * @param string $rootDir
     * @param array|null $args
     *
     * @throws RuntimeException if the singleton is already set or the root dir does not exist and cannot be created.
     * @throws ServiceAlreadyBoundException if any of the service bindings set up by the Application is already bound.
     */
    public function __construct(string $rootDir, array $args = [])
    {
        parent::__construct($rootDir);

        // we don't use STDIN, etc. constants for testability
        $this->m_in = fopen("php://stdin", "r");
        $this->m_out = fopen("php://stdout", "w");
        $this->m_err = fopen("php://stderr", "w");

        /** @psalm-suppress MissingThrowsDocblock help and h are guaranteed valid and not yet defined */
        $this->addFlag("help", "h", "Show the command's help message.", false);

        /** @psalm-suppress MissingThrowsDocblock debug is guaranteed valid and not yet defined */
        $this->addFlag("debug", null, "Run the command in debug mode.", false);

        $this->m_cmd  = array_shift($args) ?? "";
        $this->m_args = $args;
    }

    /**
     * Check whether a parameter name is valid.
     * Names must start with an alpha character, and be composed of 2 or more alpha, numeric or _ or - characters.
     */
    final protected static function isValidParameterName(string $name): bool
    {
        return (bool) mb_ereg_match("^[[:alpha:]][[:alpha:]0-9_-]+\$", $name);
    }

    /**
     * Check whether a parameter short name is valid.
     * Only single-character alpha strings are valid short names. Non-ASCII characters are supported.
     */
    final protected static function isValidParameterShortName(string $name): bool
    {
        return (bool) mb_ereg_match("^[[:alpha:]]\$", $name);
    }

    /**
     * @psalm-assert-if-true $type === self::Option || $type === self::Flag || $type === self::Argument
     * @param int $type
     * @return bool
     */
    private static function isValidParameterType(int $type): bool
    {
        return in_array($type, [self::Flag, self::Option, self::Argument,]);
    }

    /**
     * Check whether a data type is valid.
     * @psalm-assert-if-true $type === self::AnyType || $type === self::StringType || $type === self::IntType || $type === self::FloatType || $type === self::ArrayType
     */
    final protected static function isValidParameterDataType(int $type): bool
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
    final protected static function extractParameterName(string $optionOrFlag): string
    {
        return match (true) {
            str_starts_with($optionOrFlag, "--") => substr($optionOrFlag, 2),
            str_starts_with($optionOrFlag, "-") => substr($optionOrFlag, 1),
            default => throw new LogicException("Expected option or flag, found \"{$optionOrFlag}\""),
        };
    }

    /**
     * Determine whether a command-line arg could be a set of compressed flags.
     *
     * Compressed flags are when -a -b -c is compressed to -abc.
     */
    final protected static function isCompressedFlags(string $arg): bool
    {
        return mb_ereg_match("^-[[:alpha:]]{2,}\$", $arg);
    }

    /**
     * Given a set of compressed flags, get an array of the individual flags.
     *
     * For example, given "-abc", return ["-a", "-b", "-c",].
     */
    final protected static function expandCompressedFlags(string $flags): array
    {
        return array_map(
            static fn (string $arg): string => "-{$arg}",
            str_split(substr($flags, 1))
        );
    }

    /** Check whether a parameter's name has already been defined. */
    final protected function parameterNameIsDefined(string $name): bool
    {
        foreach ($this->m_parameterDefinitions as $definition) {
            if ($definition->name === $name) {
                return true;
            }

            if (self::Flag === $definition->type && $definition->negatedName === $name) {
                return true;
            }
        }

        return false;
    }

    /** Check whether a parameter's short name has already been defined. */
    final protected function parameterShortNameIsDefined(string $name): bool
    {
        assert(1 === strlen($name), new LogicException("Invalid short name \"{$name}\" provided to shortNameIsDefined() helper."));

        foreach ($this->m_parameterDefinitions as $definition) {
            if (($definition->shortName ?? null) === $name) {
                return true;
            }

            if (self::Flag === $definition->type && $definition->negatedShortName === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Given a name or short name extracted from a command-line argument, find the flag it corresponds to.
     *
     * Negatable flags are accommodated - passing "not-bead" will match the flag defined with the name "bead" if it was
     * configured as negatable.
     *
     * @return StdClass the flag definition, or null if the name is not a defined flag.
     */
    final protected function flagDefinition(string $name): ?StdClass
    {
        foreach ($this->m_parameterDefinitions as $definition) {
            if (self::Flag !== $definition->type) {
                continue;
            }

            if ($name === $definition->name || $name === $definition->shortName) {
                return $definition;
            }

            // check if it's a negated flag
            if ($name === $definition->negatedName || $name === $definition->negatedShortName) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Given a name or short name extracted from a command-line argument, find the option it corresponds to.
     * @return StdClass the option definition, or null if the name is not a defined option.
     */
    final protected function optionDefinition(string $name): ?StdClass
    {
        foreach ($this->m_parameterDefinitions as $definition) {
            if (self::Option !== $definition->type) {
                continue;
            }

            if ($name === $definition->name || $name === $definition->shortName) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Find the definition of a named argument.
     * @return StdClass the argument definition, or null if the name is not a defined argument.
     */
    final protected function argumentDefinition(string $name): ?StdClass
    {
        foreach ($this->m_parameterDefinitions as $definition) {
            if (self::Argument === $definition->type && $name === $definition->name) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Parse the command-line arguments.
     *
     * The command-line arguments are assigned to defined options, arguments and flags.
     *
     * @throws InvalidArgumentException if we don't know what to do with one or more command-line arguments.
     */
    final protected function parseCommandLineArguments(): void
    {
        $argumentDefinitions = array_filter(
            $this->m_parameterDefinitions,
            static fn (StdClass $definition): bool => self::Argument === $definition->type,
        );

        $originalArgs = $this->commandLineArguments();

        for ($idx = 0; $idx < count($originalArgs); ++$idx) {
            // it might be a compressed set of flags, so we handle as an array so that we don't have different branches
            // dealing with flags vs. other args
            $args = [$originalArgs[$idx]];

            // we do this here rather than globally on the whole args array because until we have parsed previous args
            // we can't tell a compressed set of flags from a potential value for another parameter
            if (self::isCompressedFlags($args[0])) {
                $args = self::expandCompressedFlags($args[0]);
            }

            foreach ($args as $arg) {
                $definition = null;

                if (str_starts_with($arg, "-")) {
                    $name = self::extractParameterName($arg);
                    $definition = $this->flagDefinition($name) ?? $this->optionDefinition($name);
                }

                // if it's not a flag or option, assume it's a value for the next available arg
                if (null === $definition) {
                    $definition = array_shift($argumentDefinitions);

                    if (null === $definition) {
                        throw new InvalidArgumentException("Command-line argument {$arg} is not recognised");
                    }
                }

                assert(self::isValidParameterType($definition->type), new LogicException("Unexpected command-line argument definition type {$definition->type}"));

                $value = match ($definition->type) {
                    // name is guaranteed to be set to the extracted name for the current CLI arg
                    // if it matches the name or short name it's +ve, otherwise it's -ve
                    self::Flag => ($name === $definition->name || $name === $definition->shortName),
                    self::Option => $originalArgs[++$idx] ?? throw new InvalidArgumentException("Command-line parameter {$arg} expects a value but none was given"),
                    self::Argument => $arg,
                };

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
    }

    /**
     * Ensure the parsed arguments represent a valid set of values for the command.
     *
     * Ensures that mandatory options, arguments and flags are present and values are of the required type.
     *
     * @throws InvalidArgumentException if the set of arguments is not valid.
     */
    final protected function validateCommandLineArguments(): void
    {
        foreach ($this->m_parameterDefinitions as $definition) {
            if (self::Flag === $definition->type) {
                continue;
            }

            assert(self::isValidParameterType($definition->type), new LogicException("Unexpected command-line argument definition type {$definition->type}"));
            assert(self::isValidParameterDataType($definition->dataType), new LogicException("Unexpected command-line argument data type {$definition->dataType}"));

            if (!array_key_exists($definition->name, $this->m_parameterValues)) {
                if ($definition->optional ?? true) {
                    continue;
                }

                // can't be a flag type as flags are skipped at the beginning of the loop
                [$name, $argType] = match ($definition->type) {
                    self::Option => ["--{$definition->name}", "option",],
                    self::Argument => [$definition->name, "argument",],
                };

                if (self::Option === $definition->type && null !== ($definition->shortName ?? null)) {
                    $name .= "|-{$definition->shortName}";
                }

                throw new InvalidArgumentException("Command-line {$argType} \"{$name}\" is required but was not given");
            }

            $validated = match ($definition->dataType) {
                // parsing takes care of ensuring values are arrays where required
                self::TypeAny, self::TypeString, self::TypeArray => $this->m_parameterValues[$definition->name],
                self::TypeInt => filter_var($this->m_parameterValues[$definition->name], FILTER_VALIDATE_INT, ["flags" => FILTER_NULL_ON_FAILURE,]),
                self::TypeFloat => filter_var($this->m_parameterValues[$definition->name], FILTER_VALIDATE_FLOAT, ["flags" => FILTER_NULL_ON_FAILURE,]),
            };

            if (null === $validated) {
                // can't be a flag type as flags are skipped at the beginning of the loop
                [$name, $argType] = match ($definition->type) {
                    self::Option => ["--{$definition->name}", "option",],
                    self::Argument => [$definition->name, "argument",],
                };

                if (self::Option === $definition->type && null !== ($definition->shortName ?? null)) {
                    $name .= "|-{$definition->shortName}";
                }

                throw new InvalidArgumentException("Command-line value for {$argType} \"{$name}\" is not of the correct type");
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
     * @throws LogicException if the description is empty when trimmed.
     */
    final protected function setDescription(string $description): void
    {
        $trimmedDescription = trim($description);

        if ("" === $trimmedDescription) {
            throw new LogicException("Expecting non-empty command description, found \"{$description}\"");
        }

        $this->m_description = $trimmedDescription;
    }

    /** Get the command's description. */
    public function description(): string
    {
        return $this->m_description;
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
     * @throws LogicException if the name is not valid or is already in use, the data type is not valid, the description
     * is empty when trimmed, or the short name (if given) is not valid or is already in use.
     */
    final protected function addOption(string $name, ?string $shortName = null, string $description = "", int $type = self::TypeAny, bool $optional = false, string|float|int|array|null $default = null): void
    {
        if (!self::isValidParameterDataType($type)) {
            throw new LogicException("Expected valid data type, found \"{$type}\"");
        }

        if (!self::isValidParameterName($name)) {
            throw new LogicException("Expected valid option name, found \"{$name}\"");
        }

        if ("" === trim($description)) {
            throw new LogicException("Expected non-empty option description, found \"{$description}\"");
        }

        if ($this->parameterNameIsDefined($name)) {
            throw new LogicException("Option name \"{$name}\" is already defined");
        }

        if (null !== $shortName) {
            if (!self::isValidParameterShortName($shortName)) {
                throw new LogicException("Expected valid option short name, found \"{$shortName}\"");
            }

            if ($this->parameterShortNameIsDefined($shortName)) {
                throw new LogicException("Option short name \"{$shortName}\" is already defined");
            }
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
     * @throws LogicException if the name is not valid or is already in use, the data type is not valid, the description
     *  is empty when trimmed, or the argument is mandatory and optional arguments have already been defined.
     */
    final protected function addArgument(string $name, string $description, int $type = self::TypeAny, bool $optional = false, string|float|int|array|null $default = null): void
    {
        if ("" === trim($description)) {
            throw new LogicException("Expected non-empty argument description, found \"{$description}\"");
        }

        if (!self::isValidParameterName($name)) {
            throw new LogicException("Expected valid argument name, found \"{$name}\"");
        }

        if ($this->parameterNameIsDefined($name)) {
            throw new LogicException("Argument name \"{$name}\" is already defined");
        }

        if (!self::isValidParameterDataType($type)) {
            throw new LogicException("Expected valid data type, found \"{$type}\"");
        }

        $optionalArguments = array_filter(
            $this->m_parameterDefinitions,
            static fn (StdClass $definition): bool => self::Argument === $definition->type && true === $definition->optional
        );

        if (!$optional && 0 !== count($optionalArguments)) {
            throw new LogicException("Mandatory arguments cannot be defined after optional arguments");
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
     * @throws LogicException if the name (and negated name if requested) is not valid or is already in use, the
     * description is empty when trimmed, the short name (if given, and negated short name if requested) is not valid or
     * is already in use.
     */
    final protected function addFlag(string $name, ?string $shortName = null, string $description = "", bool $negatable = true, bool $default = false): void
    {
        if (!self::isValidParameterName($name)) {
            throw new LogicException("Expected valid flag name, found \"{$name}\"");
        }

        if (null !== $shortName && !self::isValidParameterShortName($shortName)) {
            throw new LogicException("Expected valid flag short name, found \"{$shortName}\"");
        }

        if ("" === trim($description)) {
            throw new LogicException("Expected non-empty flag description, found \"{$description}\"");
        }

        if ($this->parameterNameIsDefined($name)) {
            throw new LogicException("Flag name \"{$name}\" is already defined");
        }

        $notName = null;
        $notShortName =  null;

        if ($negatable) {
            $notName = "not-{$name}";

            if ($this->parameterNameIsDefined($notName)) {
                throw new LogicException("Negated flag name \"{$notName}\" is already defined");
            }
        }

        if (null !== $shortName) {
            if ($this->parameterShortNameIsDefined($shortName)) {
                throw new LogicException("Flag short name \"{$shortName}\" is already defined");
            }

            $notShortName = null;

            if ($negatable && ctype_lower($shortName)) {
                $notShortName = strtoupper($shortName);

                if ($this->parameterShortNameIsDefined($notShortName)) {
                    throw new LogicException("Negated flag short name \"{$notShortName}\" is already defined.");
                }
            }
        }

        $definition = (object) [
            "type" => self::Flag,
            "name" => $name,
            "shortName" => $shortName,
            "negatedName" => $notName,
            "negatedShortName" => $notShortName,
            "description" => $description,
            "default" => $default,
        ];

        $this->m_parameterDefinitions[$name] = $definition;

        if (null !== $shortName) {
            $this->m_parameterDefinitions[$shortName] = $definition;
        }
    }

    /**
     * Check whether a given option name has been defined for the console application.
     *
     * @param string $name The name of the option to check for. Can be the short or long name.
     *
     * @return bool
     */
    public function hasOption(string $name): bool
    {
        return self::Option === ($this->m_parameterDefinitions[$name]?->type ?? null);
    }

    /**
     * Check whether a given argument name has been defined for the console application.
     *
     * @param string $name The name of the argument to check for.
     *
     * @return bool
     */
    public function hasArgument(string $name): bool
    {
        return self::Argument === ($this->m_parameterDefinitions[$name]?->type ?? null);
    }

    /**
     * Check whether a given flag name has been defined for the console application.
     *
     * @param string $name The name of the flag to check for. Can be the short or long name.
     *
     * @return bool
     */
    public function hasFlag(string $name): bool
    {
        return self::Flag === ($this->m_parameterDefinitions[$name]?->type ?? null);
    }

    /**
     * Determine whether an option has been given a value.
     *
     * This is useful for checking whether non-mandatory options with no default have been given values or not.
     *
     * @return true if the option was provided on the command-line or has a default, false otherwise.
     * @throws LogicException if no option with the given name is not defined.
     */
    public function optionIsSet(string $name): bool
    {
        if (!array_key_exists($name, $this->m_parameterDefinitions) || self::Option !== $this->m_parameterDefinitions[$name]->type) {
            throw new LogicException("Option \"{$name}\" is not defined.");
        }

        return array_key_exists($name, $this->m_parameterValues) || null !== $this->m_parameterDefinitions[$name]->default;
    }

    /**
     * Determine whether an argument has been given a value.
     *
     * This is useful for checking whether optional arguments with no default have been given values or not.
     *
     * @return true if the argument was provided on the command-line or has a default, false otherwise.
     * @throws LogicException if no argument with the given name is not defined.
     */
    public function argumentIsSet(string $name): bool
    {
        if (!array_key_exists($name, $this->m_parameterDefinitions) || self::Argument !== $this->m_parameterDefinitions[$name]->type) {
            throw new LogicException("Argument \"{$name}\" is not defined");
        }

        return array_key_exists($name, $this->m_parameterValues) || null !== $this->m_parameterDefinitions[$name]->default;
    }

    /**
     * Fetch the value given for a command-line option.
     *
     * @param string $name The option name.
     *
     * @return string|float|int|array|bool|null The argument value.
     * @throws LogicException if no option with the given name is defined.
     */
    public function optionValue(string $name): string|float|int|array|bool|null
    {
        if (!array_key_exists($name, $this->m_parameterDefinitions) || self::Option !== $this->m_parameterDefinitions[$name]->type) {
            throw new LogicException("Option \"{$name}\" is not defined");
        }

        // we don't need to check and throw if not set - parsing throws if any required option is not set
        return $this->m_parameterValues[$name] ?? $this->m_parameterDefinitions[$name]->default;
    }

    /**
     * Fetch the value given for a command-line argument.
     *
     * @param string $name The argument name.
     *
     * @return string|float|int|array|bool|null The argument value.
     * @throws LogicException if no argument with the given name is defined.
     */
    public function argumentValue(string $name): string|float|int|array|bool|null
    {
        if (!array_key_exists($name, $this->m_parameterDefinitions) || self::Argument !== $this->m_parameterDefinitions[$name]->type) {
            throw new LogicException("Argument \"{$name}\" is not defined");
        }

        // we don't need to check and throw if not set - parsing throws if any required arg is not set
        return $this->m_parameterValues[$name] ?? $this->m_parameterDefinitions[$name]->default;
    }

    /**
     * Fetch the value given for a command-line flag.
     *
     * @param string $name The flag name.
     *
     * @return bool The flag value.
     * @throws LogicException if no flag with the given name is defined.
     */
    public function flagValue(string $name): bool
    {
        if (!array_key_exists($name, $this->m_parameterDefinitions) || self::Flag !== $this->m_parameterDefinitions[$name]->type) {
            throw new LogicException("Flag \"{$name}\" is not defined");
        }

        // flags are always optional and always have a default state
        return $this->m_parameterValues[$name] ?? $this->m_parameterDefinitions[$name]->default;
    }

    /** Show the auto-generated help for the command. */
    protected function showHelp(): void
    {
        $this->line("{$this->executedScript()}: {$this->description()}");

        $shortFlags = array_filter(
            $this->m_parameterDefinitions,
            static fn (StdClass $definition, string $key): bool => 1 === strlen($key) && self::Flag === $definition->type,
            ARRAY_FILTER_USE_BOTH,
        );

        $flags = array_filter(
            $this->m_parameterDefinitions,
            static fn (StdClass $definition, string $key): bool => 1 < strlen($key) && self::Flag === $definition->type,
            ARRAY_FILTER_USE_BOTH,
        );

        $options = array_filter(
            $this->m_parameterDefinitions,
            static fn (StdClass $definition, string $key): bool => 1 < strlen($key) && self::Option === $definition->type,
            ARRAY_FILTER_USE_BOTH,
        );

        $arguments = array_filter(
            $this->m_parameterDefinitions,
            static fn (StdClass $definition): bool => self::Argument === $definition->type,
        );

        $flagsSummary = "";

        if (0 < count($shortFlags)) {
            $flagsSummary .=
                "[-"
                . implode(
                    "",
                    array_map(
                        static fn (StdClass $definition): string => $definition->shortName,
                        $shortFlags,
                    )
                )
                . "] ";
            ;
        }

        $flagsSummary .= implode(
            " ",
            array_map(
                static fn (StdClass $definition): string => "[--{$definition->name}]",
                $flags,
            )
        );

        $optionsSummary = implode(
            " ",
            array_map(
                static function (StdClass $definition): string {
                    $ret = "--{$definition->name}";

                    if (null !== $definition->shortName) {
                        $ret .= "|-{$definition->shortName}";
                    }

                    if (self::TypeArray === $definition->dataType) {
                        $ret .= "...";
                    }

                    if (null !== $definition->default) {
                        $ret .= "={$definition->default}";
                    }

                    return $definition->optional ? "[{$ret}]" : $ret;
                },
                array_filter(
                    $this->m_parameterDefinitions,
                    static fn (StdClass $definition, string $key): bool => 1 < strlen($key) && self::Option === $definition->type,
                    ARRAY_FILTER_USE_BOTH,
                )
            )
        );

        $argumentsSummary = implode(
            " ",
            array_map(
                static fn (StdClass $definition): string => ($definition->optional ? "[{$definition->name}]" : $definition->name),
                array_filter(
                    $this->m_parameterDefinitions,
                    static fn (StdClass $definition): bool => self::Argument === $definition->type,
                )
            )
        );

        $summary = $flagsSummary;
        $summary .= ("" !== $optionsSummary ? " " : "") . $optionsSummary;
        $summary .= ("" !== $argumentsSummary ? " " : "") . $argumentsSummary;
        $this->line("  {$summary}");

        if (0 < count($flags)) {
            $this->line("");
            $this->line("Flags");

            foreach ($flags as $flag) {
                $flagSummary = "  --{$flag->name}";

                if (null !== $flag->shortName) {
                    $flagSummary .= "|-{$flag->shortName}";
                }

                $flagSummary .= " {$flag->description}";

                if ($flag->default) {
                    $flagSummary .= " (Default is on.)";
                }

                $this->line("  {$flagSummary}");
            }
        }

        if (0 < count($options)) {
            $this->line("");
            $this->line("Options");

            foreach ($options as $option) {
                $optionSummary = "  --{$option->name}";

                if (null !== $option->shortName) {
                    $optionSummary .= "|-{$option->shortName}";
                }

                assert(self::isValidParameterDataType($option->dataType), new LogicException("Unexpected command-line argument definition data type {$option->dataType}"));

                $optionSummary .= match ($option->dataType) {
                    self::TypeAny, self::TypeArray => " <any>",
                    self::TypeString => " <string>",
                    self::TypeInt => " <integer>",
                    self::TypeFloat => " <number>",
                };

                if ($option->optional) {
                    $optionSummary .= " (optional)";
                }

                $optionSummary .= " {$option->description}";

                if (self::TypeArray === $option->dataType) {
                    $optionSummary .= " (Can be specified more than once.)";
                }

                if ($option->default) {
                    $optionSummary .= " (Default is {$option->default}.)";
                }

                $this->line("  {$optionSummary}");
            }
        }

        if (0 < count($arguments)) {
            $this->line("");
            $this->line("Arguments");

            foreach ($arguments as $argument) {
                $argumentSummary = "  {$argument->name}";

                assert(self::isValidParameterDataType($argument->dataType), new LogicException("Unexpected command-line argument definition data type {$argument->dataType}"));

                $argumentSummary .= match ($argument->dataType) {
                    self::TypeAny, self::TypeArray => " (any",
                    self::TypeString => " (string",
                    self::TypeInt => " (integer",
                    self::TypeFloat => " (number",
                };

                if ($argument->optional) {
                    $argumentSummary .= ", optional";
                }

                $argumentSummary .= ") {$argument->description}";

                if ($argument->default) {
                    $argumentSummary .= " (Default is {$argument->default}.)";
                }

                $this->line("  {$argumentSummary}");
            }
        }
    }

    /**
     * Helper to write some text to a given stream.
     *
     * @param string $text The text to write.
     * @param resource $stream The stream to which to write it.
     */
    final protected function write(string $text, $stream): void
    {
        fputs($stream, $text);
    }

    /**
     * Output an error message.
     *
     * The message will be output in red text to the command's error stream. A newline character will be appended.
     */
    protected function errorLine(string $line): void
    {
        $this->write("\033[31m{$line}\033[39m\n", $this->errorStream());
    }

    /**
     * Output a message.
     *
     * The message will be output to the command's output stream. A newline character will be appended.
     */
    protected function line(string $line): void
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
    protected function read(string $prompt = "", ?int $maxLen = null): string
    {
        $this->write($prompt, $this->outStream());

        if (is_int($maxLen) && 0 < $maxLen) {
            $line = fgets($this->inStream(), $maxLen + 1);
        } else {
            $line = fgets($this->inStream());
        }

        if (false === $line) {
            throw new RuntimeException("Failed to read from input stream");
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
    protected function readSecret(string $prompt = ""): string
    {
        if (!stream_isatty($this->inStream())) {
            throw new RuntimeException("Input stream is not a TTY, input hiding is not available");
        }

        $mode = shell_exec("stty -g");
        shell_exec("stty -echo");
        $value = $this->read($prompt);
        shell_exec("stty {$mode}");

        if (function_exists("posix_ttyname")) {
            // we know in is a TTY, and since POSIX is available we know it's a POSIX TTY so this call won't fail
            $inTty = posix_ttyname($this->inStream());

            if (is_string($inTty) && stream_isatty($this->outStream()) && $inTty === posix_ttyname($this->outStream())) {
                $this->write("\n", $this->outStream());
            }
        }

        return $value;
    }

    /**
     * @param string $prompt
     * @return bool
     *
     * @throws RuntimeException if the input stream cannot be read.
     */
    protected function confirm(string $prompt): bool
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
        assert(is_resource($stream) && "stream" === get_resource_type($stream), new InvalidArgumentException("Invalid output stream - not a 'stream' resource."));
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
        assert(is_resource($stream) && "stream" === get_resource_type($stream), new InvalidArgumentException("Invalid output stream - not a 'stream' resource."));
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
        assert(is_resource($stream) && "stream" === get_resource_type($stream), new InvalidArgumentException("Invalid output stream - not a 'stream' resource."));
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
     * @return string The script name, or an empty string if the command was executed programmatically.
     */
    public function executedScript(): string
    {
        return $this->m_cmd;
    }

    /**
     * Fetch all the raw command-line arguments.
     * @return array
     */
    public function commandLineArguments(): array
    {
        return $this->m_args;
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
        return parent::isInDebugMode() || $this->flagValue("debug");
    }

    /**
     * Execute the command.
     *
     * This cannot be overriden in subclasses - implement run() instead. The exec() method will take care of
     * configuring the command and parsing and validating its command-line arguments before delegating to run() to
     * run the command.
     *
     * @return int The value returned by the command's run() method.
     * @throws InvalidArgumentException if the command-line arguments are not syntactically correct or have invalid
     * values.
     */
    final public function exec(): int
    {
        $this->configure();
        $this->parseCommandLineArguments();
        $this->validateCommandLineArguments();

        /** @psalm-suppress MissingThrowsDocblock help flag is guaranteed to be valid and defined. */
        if ($this->flagValue("help")) {
            $this->showHelp();
            return 0;
        }

        if ($this->flagValue("debug") && $this->has(LoggerContract::class)) {
            /** @psalm-suppress MissingThrowsDocblock service binding has been checked. */
            $this->get(LoggerContract::class)->setLevel(LoggerContract::DebugLevel);
        }

        return $this->run();
    }

    /**
     * Implement in Command classes to run the command.
     *
     * When this method is called, the command will have been configured and its command-line arguments parsed and
     * validated. You don't need to handle the "help" command-line arg, this is already handled and run() won't be
     * called if the help argument is specified on the command-line.
     *
     * Your implementation is entitled to assume that the command-line arguments passed meet the definitions it's set
     * up in configure().
     */
    abstract protected function run(): int;

    /**
     * Reimplement this to configure the command.
     *
     * Set the command's expected command-line arguments and description here.
     */
    protected function configure(): void
    {
    }
}
