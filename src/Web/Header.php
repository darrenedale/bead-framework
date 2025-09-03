<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\Header as HeaderContract;
use RuntimeException;

/** An immutable implementation of the Web\Header contract. */
class Header implements HeaderContract
{
    /** @var string The header name. */
    private string $name;

    /** @var string The header value. */
    private string $value;

    /**
     * PCRE pattern to identify valid RFC-822 header field names.
     *
     * See https://datatracker.ietf.org/doc/html/rfc822#section-3.2
     */
    private const Rfc822HeaderNamePattern = "/^[!#$%&'*+\\-0-9A-Z^_`a-z|~]+\$/";

    /**
     * Initialise a new header with a name and value.
     *
     * @param string $name Must be a valid RFC822 message header name.
     * @param string $value The header's value.
     * @throws RuntimeException if the provided header name is not valid.
     */
    public function __construct(string $name, string $value)
    {
        self::checkName($name);
        $this->name = mb_strtolower($name, "UTF-8");
        $this->value = $value;
    }

    /** @throws RuntimeException if the provided header name is not valid. */
    private static function checkName(string $name): void
    {
        if (!self::isValidName($name)) {
            throw new RuntimeException("Expected valid header name, found \"{$name}\"");
        }
    }

    /** Check whether a string contains a valid RFC822 header name. */
    public static function isValidName(string $name): bool
    {
        return 1 === preg_match(self::Rfc822HeaderNamePattern, $name);
    }

    /** The header name. */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Fetch a clone of the header with a different name.
     * @throws RuntimeException if the provided header name is not valid.
     */
    public function withName(string $name): self
    {
        self::checkName($name);
        $clone = clone $this;
        $clone->name = $name;
        return $clone;
    }

    /** The header value. */
    public function value(): string
    {
        return $this->value;
    }

    /** Fetch a clone of the header with a different value. */
    public function withValue(string $value): self
    {
        $clone = clone $this;
        $clone->value = $value;
        return $clone;
    }

    /** The full header line. */
    public function line(): string
    {
        return "{$this->name()}: {$this->value()}";
    }
}
