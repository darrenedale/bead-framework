<?php

declare(strict_types=1);

namespace Bead\Email;

use InvalidArgumentException;

use function Bead\Helpers\Iterable\all;

/**
 * Trait to provide headers for MIME message parts.
 */
trait HasHeaders
{
    /** @var Header[] The headers for the email. */
    protected array $headers = [];

    /**
     * Helper to determine whether two sets of header parameters match.
     *
     * Two sets of headers match if they have the same names, and the values for each named parameter are identical in
     * the two sets. The order of the parameters does not need to match.
     *
     * @param array $first The first set of header parameters to compare.
     * @param array $second The second set of header parameters to compare.
     *
     * @return bool `true` if they match, `false` if not.
     */
    private static function parametersMatch(array $first, array $second): bool
    {
        return count($first) === count($second) && all(array_keys($first), fn ($name): bool => array_key_exists($name, $second) && $first[$name] === $second[$name]);
    }

    /**
     * Helper to determine whether two headers match.
     *
     * The headers are considered a match if they both have the same name and value, and their parameter sets are
     * considered a match by parametersMatch(). If comparing a header to a header name, just the header names need to
     * match.
     *
     * @param Header $header The header to compare.
     * @param Header|string $headerOrName The second header to compare, or the name of a header.
     *
     * @return bool `true` if they match, `false` if not.
     */
    private static function headersMatch(Header $header, Header|string $headerOrName): bool
    {
        if (is_string($headerOrName)) {
            return 0 === strcasecmp($header->name(), trim($headerOrName));
        }

        return
            0 === strcasecmp($header->name(), $headerOrName->name())
            && 0 === strcmp($header->value(), $headerOrName->value())
            && self::parametersMatch($header->parameters(), $headerOrName->parameters());
    }

    /**
     * Internal method to set a header's value.
     *
     * It will either create a new Header object if the header with the given name doesn't exist, or will find the first
     * existing header with the name and set the value of that.
     *
     * This violates the immutability of the object, so must only be used internally in the constructor, or when working
     * with the clone object in other methods.
     *
     * @throws InvalidArgumentException if the header name is not valid or any provided parameter is not valid.
     */
    private function setHeader(string $name, string $value, array $parameters = []): void
    {
        $header = new Header($name, $value, $parameters);
        $name = strtolower($name);

        for ($idx = 0; $idx < count($this->headers); ++$idx) {
            if (strtolower($this->headers[$idx]->name()) === $name) {
                break;
            }
        }

        if ($idx === count($this->headers)) {
            // not already present, so add it
            $this->headers[] = $header;
            return;
        }

        // otherwise, replace the existing header
        $this->headers[$idx] = $header;
    }

    /** @return string[] The trimmed, lower-case names of headers that can appear only once. */
    protected static function singleUseHeaders(): array
    {
        return [
            "mime-version",
            "content-type",
            "content-transfer-encoding",
            "content-disposition",
            "from",
            "subject",
        ];
    }

    /**
     * Get the headers.
     *
     * The headers for the message are always returned as an array of `Header` objects. If there are none set, an
     * empty array will be returned.
     *
     * @return Header[] The headers.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Gets the value(s) associated with a header key.
     *
     * There may be multiple instances of the same header in an email message (e.g. the `CC` header), hence an array is
     * returned rather than just a string. If the header requested contains just one value, an array with a single
     * element is returned.
     *
     * @param $headerName string is the name of the header whose value/s is/are sought.
     *
     * @return string[] All the values assigned to the specified header. The array will be empty if the header is not
     * specified.
     */
    public function headerValues(string $headerName): array
    {
        $ret = [];

        foreach ($this->headers() as $header) {
            if (0 === strcasecmp($header->name(), $headerName)) {
                $ret[] = $header->value();
            }
        }

        return $ret;
    }

    /**
     * Find a header by its name.
     *
     * This method will check through the set of headers return the first whose name matches that provided.
     *
     * @param $name string The name of the header to find.
     *
     * @return Header|null The header if found, `null` if not.
     */
    public function header(string $name): ?Header
    {
        $name = strtolower($name);

        foreach ($this->headers() as $header) {
            if (strtolower($header->name()) === $name) {
                return $header;
            }
        }

        return null;
    }

    /**
     * Find all headers by name.
     *
     * This method will check through the set of headers in the message part and return all whose name matches that
     * provided.
     *
     * @param $name string is the name of the headers to find.
     *
     * @return Header[] The headers if found, or an empty array if not.
     */
    public function headersNamed(string $name): array
    {
        return array_filter($this->headers(), fn (Header $header): bool => 0 === strcasecmp($header->name(), $name));
    }

    /**
     * Adds a header.
     *
     * If the header is one that's only allowed a single instance, any existing instance of a header with the same name
     * is replaced rather with the one provided (rather than it just being added).
     *
     * @param $header string|Header The header to add.
     * @param $value string|null is the value for the header. Only used if $header is a string.
     *
     * @return self A clone of the object with the provided header.
     *
     * @throws InvalidArgumentException if the header name or value is not valid.
     */
    public function withHeader(string|Header $header, ?string $value = null): self
    {
        if (is_string($header)) {
            assert(is_string($value), new InvalidArgumentException("\$value must be provided when \$header is a string"));
            $header = new Header($header, $value);
        }

        $headerName = mb_strtolower($header->name(), "UTF-8");
        $clone = clone $this;

        if (in_array($headerName, self::singleUseHeaders())) {
            for ($idx = 0; $idx < count($clone->headers); ++$idx) {
                if (mb_strtolower($clone->headers[$idx]->name(), "UTF-8") === $headerName) {
                    break;
                }
            }

            if ($idx < count($clone->headers)) {
                array_splice($clone->headers, $idx, 1);
            }
        }

        $clone->headers[] = $header;
        return $clone;
    }

    /**
     * Remove a header.
     *
     * Supplying a string will remove all headers with that name; providing a `Header` object will attempt to remove a
     * header that matches it precisely - including the header value and any parameters. If the header does not match
     * precisely any header in the message, no headers will be removed.
     *
     * @param $header string|Header The header to remove.
     *
     * @return self A clone of the object without the provided header.
     */
    public function withoutHeader(string|Header $header): self
    {
        $clone = clone $this;

        for ($idx = 0; $idx < count($clone->headers); ++$idx) {
            if (self::headersMatch($clone->headers[$idx], $header)) {
                array_splice($clone->headers, $idx, 1);
                --$idx;
            }
        }

        return $clone;
    }
}
