<?php

namespace Bead;

/**
 * A class encapsulating a header for an email message.
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
 * The header name is validated when `setName()` is called, and if it doesn't pass validation the name is not set.
 *
 * The full string representation of the header, suitable for inclusion in an email message header section, can be
 * fetched by calling `generate()`.
 */
class EmailHeader
{
    /** @var string|null The name of the header. */
    private ?string $m_name = null;

    /** @var string|null The header value. */
    private ?string $m_value = null;

    /** @var array The header value parameters. */
    private array $m_params = [];

    /**
     * Create a new email message header.
     *
     * @param $name string|null The name for the header.
     * @param $value string|null The value for the header.
     */
    public function __construct(?string $name = null, ?string $value = null)
    {
        $this->setName($name);
        $this->setValue($value);
    }

    /**
     * Get the value of the header.
     *
     * @return string|null The header name, or `null` on error.
     */
    public function name(): ?string
    {
        return $this->m_name;
    }

    /**
     * Get the value of the header.
     *
     * @return string|null The header value, or `null` on error.
     */
    public function value(): ?string
    {
        return $this->m_value;
    }

    /**
     * Set the name of the header.
     *
     * The name must be a UTF-8 encoded string. It may not be empty. It may be `null` to indicate that the header name
     * is not set.
     *
     * @param $name string|null The name for the header.
     *
     * @return bool `true` if the name was set, `false` otherwise.
     */
    public function setName(?string $name): bool
    {
        if (is_string($name)) {
            $name = trim($name);

            if (!preg_match("/^[\\!#\\\$%&'\\*\\+\\-0-9A-Z\\^_`a-z\\|~]+\$/", $name)) {
                AppLog::error("invalid name \"{$name}\"");
                return false;
            }
        }

        $this->m_name = $name;
        return true;
    }

    /**
     * Set the value of the header.
     *
     * The value may be an empty string. It may also be `null` to indicate that the value is not set.
     *
     * @param $value string|null The value for the header.
     */
    public function setValue(?string $value): void
    {
        $this->m_value = $value;
    }

    /**
     * Check whether a parameter is set for the header.
     *
     * @param $name string the name of the parameter to check.
     *
     * @return bool `true` if the parameter is set, `false` otehrwise.
     */
    public function hasParameter(string $name): bool
    {
        return array_key_exists($name, $this->m_params);
    }

    /**
     * Set the value of a parameter for the header.
     *
     * @param $name string The name of the parameter to set.
     * @param $value string The value for the parameter.
     */
    public function setParameter(string $name, string $value): void
    {
        $this->m_params[$name] = $value;
    }

    /**
     * Get the value of a parameter for the header.
     *
     * @param $name string The name of the parameter to get.
     *
     * @return string|null The value for the parameter, or `null` if the parameter is not set or an error occurred.
     */
    public function parameter(string $name): ?string
    {
        if ($this->hasParameter($name)) {
            return $this->m_params[$name];
        }

        return null;
    }

    /**
     * Remove a parameter from the list of parameters for the header.
     *
     * @param $name string The name of the parameter to remove.
     *
     * @return bool `true` if the parameter was found and removed, `false` otherwise.
     */
    public function clearParameter(string $name): bool
    {
        if ($this->hasParameter($name)) {
            unset($this->m_params[$name]);
            return true;
        }

        return false;
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
     * @return array[string=>string] The parameters.
     */
    public function parameters(): array
    {
        return $this->m_params;
    }

    /**
     * Generate the header line.
     *
     * The header line is generated without any trailing delimiter. For SMTP and POP3 the delimiter is the sequence
     * <cr><lf> but other protocols, including protocols that are yet to be created, may use other delimiters. For this
     * reason, it is up to the protocol handler to add the appropriate delimiter.
     *
     * @return string|null The header line, or `null` if it is not valid.
     */
    public function generate(): ?string
    {
        $ret = null;
        $name = $this->name();
        $value = $this->value();

        if (is_string($name) && is_string($value)) {
            $ret = "{$name}: {$value}";

            foreach ($this->parameters() as $key => $value) {
                $ret .= ("; {$key}={$value}");
            }
        }

        return $ret;
    }
}
