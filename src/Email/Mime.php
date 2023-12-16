<?php

declare(strict_types=1);

namespace Bead\Email;

use function Bead\Helpers\Iterable\fill;
use function Bead\Helpers\Iterable\toArray;

/**
 * Static class providing some convenience MIME-related methods.
 */
final class Mime
{
    /** @var string CRLF as defined in RFC-822. */
    public const Rfc822LineEnd = "\r\n";

    /** @var string The possible characters to use in the randomly-generated portion of the mulitpart delimiter. */
    public const DelimiterAlphabet = "abcdefghijklmnopqrstuvwxyz0123456789";

    /**
     * PCRE pattern to identify valid RFC-2045 tokens.
     *
     * RFC-2045 "token" rx accepts any character which is *NOT*:
     * - [:^ascii:]               outside the ASCII range (ASCII range is 0-127)
     * - [:cntrl:]                a control character (ASCII 0-31)
     * -  ()<>@,;:\\\"/\\[\\]?=   one of these characters (note, this set includes the space character)
     */
    private const Rfc2045TokenPattern = "[^[:^ascii:][:cntrl:] ()<>@,;:\"/\\[\\]?=]+";

    /**
     * PCRE pattern to identify valid RFC-822 quoted-string elements.
     *
     * RFC-822 "quoted-string" (inherited in RFC-2045) accepts a sequence of any combination of:
     * - \\\\[[:ascii:]]          a backslash followed by any ASCII character
     * - any character which is *NOT*:
     *   - [:^ascii:]             outside the ASCII range
     *   - \"\\\\\\n              a double-quote, a backslash or a newline wrapped in double-quotes
     */
    private const Rfc822QuotedStringPattern = "\"(?:\\\\[[:ascii:]]|[^[:^ascii:]\"\\\\\\n])*\"";

    /**
     * PCRE pattern to identify valid RFC-822 header field names.
     *
     * See https://datatracker.ietf.org/doc/html/rfc822#section-3.2
     */
    private const Rfc822HeaderNamePattern = "/^[!#$%&'*+\\-0-9A-Z^_`a-z|~]+\$/";

    /** @var string[] Types of content-transfer-encoding registered with IANA*/
    public const IetfContentTransferEncodings = ["7bit", "8bit", "binary", "quoted-printable", "base64",];

    /**
     * Check whether a string contains a valid MIME header name.
     */
    public static function isValidHeaderName(string $name): bool
    {
        return 1 === preg_match(self::Rfc822HeaderNamePattern, $name);
    }

    /**
     * Check whether a string contains a valid value for the content-transfer-encoding header.
     */
    public static function isValidContentTransferEncoding(string $encoding): bool
    {
        return
            in_array(strtolower($encoding), self::IetfContentTransferEncodings)
            || 1 === preg_match("#^[xX]-" . self::Rfc2045TokenPattern . "\$#", $encoding);
    }

    /**
     * Checks whether a string contains a valid media type (formerly called MIME types).
     *
     * see "https://tools.ietf.org/html/rfc2045#page-12" for definition
     *
     * @param string $type
     *
     * @return bool
     */
    public static function isValidMediaType(string $type): bool
    {
        // having these as static vars makes the composed regex easier to read
        static $token = self::Rfc2045TokenPattern;
        static $quotedString = self::Rfc822QuotedStringPattern;

        // a MIME type can be one of the discrete (text, image, audio, video, application) or composite types (message,
        // multipart) an x-token (basically a custom application-specific extension beginning with "x-") or an
        // ietf-token. ietf-tokens are a moving target - it's whatever has been approved by IANA via an RFC track. so
        // it's impossible to validate completely without keeping up-to-date with IANA approvals. in lieu of this, any
        // sequence of a-z characters is accepted because this is a) a subset of the valid characters for a token; b) is
        // guaranteed not to clash with any application-specific extensions, and c) covers all known discrete- and
        // composite- tokens, and is likely to cover all IANA approved tokens (in Jan 2018 all IANA-approved tokens
        // were exclusively ASCII).
        //
        // in Oct 2023, the list of MIME types was available here:
        // https://www.iana.org/assignments/media-types/media-types.xhtml

        if ("*/*" === $type) {
            return true;
        }

        return 1 === preg_match("#^([a-z]+|x-{$token})/({$token})( *; *{$token} *= *(?:{$token}|{$quotedString}))*\$#", $type);
    }

    /** Generate a boundary string that can be used in multipart MIME messages. */
    public static function generateMultipartBoundary(): string
    {
        return "--bead-email-part-" . implode(toArray(fill(40, fn () => self::DelimiterAlphabet[rand(0, 35)]))) . "--";
    }
}
