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
    public function headerByName(string $name): ?Header
    {
        foreach ($this->headers() as $header) {
            if (0 === strcasecmp($header->name(), $name)) {
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
    public function allHeadersByName(string $name): array
    {
        return array_filter($this->headers(), fn(Header $header): bool => 0 === strcasecmp($header->name(), $name));
    }

    /**
     * Adds a header.
     *
     * @param $header string|Header The header to add.
     * @param $value string|null is the value for the header. Only used if $header is a string.
     *
     * @throws InvalidArgumentException if the header name or value is not valid.
     */
    public function addHeader(string|Header $header, ?string $value = null): void
    {
        if (is_string($header)) {
            $header = new Header($header, (string) $value);
        }

        $this->headers[] = $header;
    }

    /**
     * Adds a header line to the email message part.
     *
     * This is a convenience function to allow addition of pre-formatted headers to an email message. Headers are
     * formatted as:
     *
     *     <key>:<value><cr><lf>
     *
     * This function will allow headers to be added either with or without the trailing `<cr><lf>`; in either case, the
     * resulting headers retrieved using `headers()` will be correctly formatted.
     *
     * Headers that do not contain the **:** delimiter will be rejected. Only the first instance of **:** is considered
     * a delimiter; anything after is treated as the value of the header. Multiple headers may not be added using a
     * single call to this method. Such attempts will be rejected.
     *
     * @param $header string The header line to add.
     *
     * @throws InvalidArgumentException If the header line to add is not valid.
     */
    public function addHeaderLine(string $header): void
    {
        $header = trim($header);

        if ("" === $header) {
            throw new InvalidArgumentException("Empty header line added.");
        }

        /* check for attempt to add multiple header lines  */
        if (preg_match("/\\r?\\n[^\\t]/", $header)) {
            throw new InvalidArgumentException("Header line contains more than one header.");
        }

        $components = explode(":", $header, 2);

        if (2 != count($components)) {
            throw new InvalidArgumentException("Ill-formed header line \"{$header}\".");
        }

        // TODO parse the parameters
        /* EmailHeader constructor handles validation */
        $this->addHeader(new Header(trim($components[0]), trim($components[1])));
    }

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
        return count($first) === count($second) && all(array_keys($first), fn($name): bool => array_key_exists($name, $second) && $first[$name] === $second[$name]);
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
            return 0 === strcasecmp($header->name(), $headerOrName);
        }

        return
            0 === strcasecmp($header->name(), $headerOrName->name())
            && 0 === strcmp($header->value(), $headerOrName->value())
            && self::parametersMatch($header->parameters(), $headerOrName->parameters());
    }

    /**
     * Remove a header.
     *
     * Supplying a string will remove all headers with that name; providing a `Header` object will attempt to remove a
     * header that matches it precisely - including the header value and any parameters. If the header does not match
     * precisely any header in the message, no headers will be removed.
     *
     * @param $header string|Header The header to remove.
     */
    public function removeHeader(string|Header $header): void
    {
        for ($idx = 0; $idx < count($this->headers); ++$idx) {
            if (self::headersMatch($this->headers[$idx], $header)) {
                array_splice($this->headers, $idx, 1);
                --$idx;
            }
        }
    }

    /**
     * Remove all the headers.
     */
    public function clearHeaders(): void
    {
        $this->headers = [];
    }
}
