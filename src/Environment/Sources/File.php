<?php

declare(strict_types=1);

namespace Bead\Environment\Sources;

use Bead\Contracts\Environment as EnvironmentContract;
use Bead\Exceptions\EnvironmentException;
use RuntimeException;
use SplFileInfo;

/** Sources environment variables from a file. */
class File implements EnvironmentContract
{
    use ValidatesVariableNames;

    /** @var string The env file to read. */
    private string $fileName;

    /** @var array<string,mixed> The environment variables read from the file. */
    private ?array $data = null;

    /**
     * Initialise a new reader.
     *
     * Env files are parsed on-demand - construction is a very cheap operation.
     *
     * @param string $fileName The file to read.
     */
    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
    }

    /** Check whether the file has been parsed. */
    protected function isParsed(): bool
    {
        return isset($this->data);
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
                throw new EnvironmentException("Invalid varaible name '{$keyValue[0]}' at line {$lineNumber} in '{$this->fileName()}'.");
            }

            if (array_key_exists($key, $data)) {
                throw new EnvironmentException("Varaible name '{$key}' at line {$lineNumber} has been defined previously in '{$this->fileName()}'.");
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
     * @throws EnvironmentException
     */
    public function has(string $name): bool
    {
        if (!$this->isParsed()) {
            $this->parse();
        }

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
        if (!$this->isParsed()) {
            $this->parse();
        }

        return $this->data[$name] ?? "";
    }

    /**
     * Fetch the names of all defined variables.
     *
     * @return string[]
     */
    public function names(): array
    {
        if (!$this->isParsed()) {
            $this->parse();
        }

        return array_keys($this->data);
    }

    /**
     * Fetch all the environment variables.
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        if (!$this->isParsed()) {
            $this->parse();
        }

        return $this->data;
    }
}
