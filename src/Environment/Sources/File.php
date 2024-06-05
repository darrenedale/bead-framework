<?php

declare(strict_types=1);

namespace Bead\Environment\Sources;

use Bead\Contracts\Environment as EnvironmentContract;
use Bead\Exceptions\EnvironmentException;
use RuntimeException;
use SplFileInfo;

use function array_key_exists;
use function array_keys;
use function count;
use function explode;
use function substr;
use function trim;

/** Sources environment variables from a file. */
class File implements EnvironmentContract
{
    use ValidatesVariableNames;

    /** @var string The env file to read. */
    private string $fileName;

    /** @var array<string,mixed> The environment variables read from the file. */
    private ?array $data = null;

    /**
     * Initialise a new environment source with a given environment file.
     *
     * @param string $fileName The file to read.
     * @throws EnvironmentException if the file is not a valid environment file.
     */
    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
        $this->parse();
    }

    /**
     * Parse the file.
     *
     * @throws EnvironmentException
     */
    private function parse(): void
    {
        $fileInfo = new SplFileInfo($this->fileName());

        try {
            $file = $fileInfo->openFile();
        } catch (RuntimeException $err) {
            throw new EnvironmentException("Failed to read env file '{$this->fileName()}': {$err->getMessage()}", previous: $err);
        }

        $data = [];
        $lineNumber = 0;

        foreach ($file as $line) {
            ++$lineNumber;
            $line = trim($line);

            if ("" === $line || "#" === $line[0]) {
                continue;
            }

            $keyValue = explode("=", $line, 2);

            if (2 !== count($keyValue)) {
                throw new EnvironmentException("Invalid declaration at line {$lineNumber} in '{$this->fileName()}'.");
            }

            $key = self::validateVariableName($keyValue[0]);

            if (!isset($key)) {
                throw new EnvironmentException("Invalid variable name '{$keyValue[0]}' at line {$lineNumber} in '{$this->fileName()}'.");
            }

            if (array_key_exists($key, $data)) {
                throw new EnvironmentException("Variable name '{$key}' at line {$lineNumber} has been defined previously in '{$this->fileName()}'.");
            }

            $data[$key] = self::extractValue($keyValue[1]);
        }

        $this->data = $data;
    }

    /**
     * Helper to extract a value from a possibly quoted string.
     *
     * If the value is wrapped in single or double quotes, its content will be extracted; otherwise, it will be returned
     * as-is.
     *
     * @param string $value The value to parse.
     */
    private static function extractValue(string $value): string
    {
        $value = trim($value);

        if ("" !== $value) {
            foreach (["\"", "'"] as $quote) {
                if ($value[0] === $quote && $value[-1] === $quote) {
                    return substr($value, 1, -1);
                }
            }
        }

        return $value;
    }

    /** Fetch the name of the env file being read. */
    public function fileName(): string
    {
        return $this->fileName;
    }

    /**
     * Determine whether the file contains a given key.
     *
     * Calling this will trigger the file to be parsed if it hasn't been already.
     *
     * @param string $name The key to check for.
     *
     * @return bool true if the key is defined in tne env file, false if not.
     */
    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Fetch a value from the environment file.
     *
     * @param string $name The key of the value sought.
     *
     * @return string The value, or an empty string if the file does not have a value for the key.
     */
    public function get(string $name): string
    {
        return $this->data[$name] ?? "";
    }

    /**
     * Fetch the names of all defined variables.
     *
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->data);
    }

    /**
     * Fetch all the environment variables.
     *
     * @return array<string,string>
     */
    public function all(): array
    {
        return $this->data;
    }
}
