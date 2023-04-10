<?php

namespace Bead\Email;

use InvalidArgumentException;
use function Bead\Helpers\Iterable\all;

/**
 * Encapsulation of a header for an email message.
 *
 * An email header is composed of a header name, a header value and zero or more header value parameters. It is
 * presented in an email message as:
 *
 *     {name}: {value}[;{param-name}={param-value}[;{param-name}={param-value}]...]
 *
 * Use `name()` and `setName()` to fetch and set the header name. Use `value()` and `setValue()` to fetch and set the
 * value.
 *
 * The list of parameters can be fetched with `parameters()`. Values for individual named parameters be fetched and set
 * using `parameter()` and `setParameter()`. A parameter can be removed by passing its name to `clearParameter()`. The
 * presence of certain named parameters can be tested with `hasParameter()` and the number of parameters present in the
 * header can be checked with `parameterCount()`.
 *
 * The header name is validated when `setName()` is called, which throws an InvalidArgumentException if it doesn't pass
 * validation.
 *
 * The full string representation of the header, suitable for inclusion in an email message header section, can be
 * fetched by calling `generate()`.
 */
class Header
{
    /** @var string The name of the header. */
    private string $name;

    /** @var string The header value. */
    private string $value;

    /** @var array<string,string> The header value parameters. */
    private array $params = [];

    /**
     * Create a new email message header.
     *
     * @param $name string The name for the header.
     * @param $value string The value for the header.
     * @param $params array<string,string> The parameters for the header.
     */
    public function __construct(string $name, string $value, array $params = [])
    {
        assert(all(array_keys($params), fn($name): bool => is_string($name) && "" !== trim($name)), new InvalidArgumentException("All header parameter names must be strings."));
        assert(all($params, 'is_string'), new InvalidArgumentException("All header parameters must be strings."));
        $this->setName($name);
        $this->setValue($value);

        foreach ($params as $paramName => $paramValue) {
            $this->setParameter($paramName, $paramValue);
        }
    }

    /**
     * Set the name of the header.
     *
     * The name must be a UTF-8 encoded string. It may not be empty and must be composed entirely of characters from the
     * set !#$%&'*+-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZZ^_`abcdefghijklmnopqrstuvwxyz|~
     *
     * @param $name string The name for the header.
     *
     * @throws InvalidArgumentException If the name is not valid.
     */
    final public function setName(string $name): void
    {
        $name = trim($name);

        if (!preg_match("/^[!#$%&'*+\\-0-9A-Z^_`a-z|~]+\$/", $name)) {
            throw new InvalidArgumentException("Invalid header name \"$name\".");
        }

        $this->name = $name;
    }

    /**
     * Get the value of the header.
     *
     * @return string The header name, or `null` on error.
     */
    final public function name(): string
    {
        return $this->name;
    }

    /**
     * Set the value of the header.
     *
     * The value may be an empty string. It may also be `null` to indicate that the value is not set.
     *
     * @param $value string The value for the header.
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * Get the value of the header.
     *
     * @return string The header value, or `null` on error.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Check whether a parameter is set for the header.
     *
     * @param $name string the name of the parameter to check.
     *
     * @return bool `true` if the parameter is set, `false` otherwise.
     */
    public function hasParameter(string $name): bool
    {
        return array_key_exists($name, $this->params);
    }

    /**
     * Set the value of a parameter for the header.
     *
     * @param $name string The name of the parameter to set.
     * @param $value string The value for the parameter.
     */
    public function setParameter(string $name, string $value): void
    {
        $this->params[$name] = $value;
    }

    /**
     * Get the value of a parameter for the header.
     *
     * @param $name string The name of the parameter to get.
     *
     * @return string|null The value for the parameter, or `null` if the parameter is not set.
     */
    public function parameter(string $name): ?string
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Remove a parameter from the list of parameters for the header.
     *
     * @param $name string The name of the parameter to remove.
     */
    public function clearParameter(string $name): void
    {
        if (!$this->hasParameter($name)) {
            return;
        }

        unset($this->params[$name]);
    }

    /**
     * Count the number of parameters set for the header.
     *
     * @return int The number of parameters.
     */
    public function parameterCount(): int
    {
        return count($this->parameters());
    }

    /**
     * Get all parameters for the header.
     *
     * The parameters are returned as an array, keyed by the parameter key. Both the keys and values are always UTF-8
     * encoded strings.
     *
     * @return array<string,string> The parameters.
     */
    public function parameters(): array
    {
        return $this->params;
    }

    /**
     * Generate the header line.
     *
     * The header line is generated without any trailing delimiter. For SMTP and POP3 the delimiter is the sequence
     * <cr><lf> but other protocols, including protocols that are yet to be created, may use other delimiters. For this
     * reason, it is up to the protocol handler to add the appropriate delimiter.
     *
     * @return string The header line.
     */
    public function generate(): string
    {
        $header = "{$this->name()}: {$this->value()}";

        foreach ($this->parameters() as $key => $value) {
            $header .= "; {$key}={$value}";
        }

        return $header;
    }
}
