<?php

declare(strict_types=1);

namespace Bead\Email;

use InvalidArgumentException;
use LogicException;

use function assert;
use function is_null;
use function is_string;
use function mb_strtolower;
use function preg_match;
use function trim;

/**
 * Class representing a part of a multipart email message.
 *
 * Objects of this class can be used as parts in a multipart email message.
 */
class Part
{
    use HasHeaders {
        addHeader as private traitAddHeader;
        clearHeaders as private traitClearHeaders;
    }

    /** @var string The default content type for email message parts. */
    const DefaultContentType = "text/plain";

    /** @var string The default character encoding for email message parts. */
    const DefaultTextCharset = "utf-8";

    /** @var string The default content encoding for email message parts. */
    const DefaultContentEncoding = "quoted-printable";

    /** @var string The part's content. */
    private string $content = "";

    /**
     * Create a new message part.
     *
     * The content is actually simply a byte sequence. The content must already be of the intended MIME type and encoded
     * according to the intended *content-transfer-encoding*. Objects of this class do not do any translation or
     * conversion of content.
     *
     * The default content type for message parts is *text/plain* and the default encoding is *quoted-printable*.
     *
     * @param $content string The content.
     * @param $contentType string The content type for the message part.
     * @param $contentEncoding string The content encoding.
     */
    function __construct(string $content = "", string $contentType = self::DefaultContentType, string $contentEncoding = self::DefaultContentEncoding)
    {
        $this->setContentType($contentType);
        $this->setContentEncoding($contentEncoding);
        $this->setContent($content);
    }

    /**
     * Adds a header to the email message.
     *
     * The header may be a EmailHeader object or a PHP string. If it is the former, the value parameter is ignored in
     * favour of the value set in the header object. If it is a string, the value parameter must be provided and must
     * also be a string. PHP strings are always assumed to be encoded using UTF-8.
     *
     * @param $header Header|string The header to add.
     * @param $value string|null is the value for the header, if `$header` is a header name.
     */
    public function addHeader(Header|string $header, ?string $value = null): void
    {
        if (is_string($header)) {
            $header = new Header($header, (string) $value);
        }

        switch (mb_strtolower($header->name(),self::DefaultTextCharset)) {
            case "content-type":
                $this->setContentType($header->value());
                break;

            case "content-transfer-encoding":
                $this->setContentEncoding($header->value());
                break;

            default:
                $this->traitAddHeader($header);
                break;
        }
    }

    /**
     * Clears all headers from the email message part.
     *
     * The required headers `Content-Type` and `Content-Transfer-Encoding` will be retained - these headers cannot be
     * cleared. If you want to reset the content type and content encoding to their default values you must make the
     * following calls, respectively:
     * - `$part->setContentType(EmailPart::DefaultContentType);`
     * - `$part->setContentEncoding(EmailPart::DefaultContentEncoding);`
     */
    public function clearHeaders(): void
    {
        $contentType = $this->contentType();
        $contentEncoding = $this->contentEncoding();
        $this->traitClearHeaders();
        $this->setContentType($contentType);
        $this->setContentEncoding($contentEncoding);
    }

    /**
     * Gets the `Content-Type` for the message part.
     *
     * @return string The value of the *Content-Type* header.
     */
    public function contentType(): string
    {
        $header = $this->headerByName("Content-Type");
        assert(isset($header), new LogicException("Content-Type header not found"));
        return $header->value();
    }

    /**
     * Sets the Content-Type header for the message part.
     *
     * @param $contentType string The content type for the message part.
     *
     * @throws InvalidArgumentException if the content type is not valid.
     */
    public function setContentType(string $contentType): void
    {
        // see "https://tools.ietf.org/html/rfc2045#page-8" for definition

        // in the following explanations, the expression fragments have kept their PHP escaping AND
        // their PCRE escaping - hence a single backslash literal in the PCRE is "\\\\"

        // RFC2045 "token" rx accepts any character which is NOT:
        // - [:^ascii:]               outside the ASCII range (ASCII range is 0-127)
        // - [:cntrl:]                a control character (ASCII 0-31)
        // -  ()<>@,;:\\\"/\\[\\]?=   one of these characters (note, this set includes the space
        //                            character)
        static $token = "[^[:^ascii:][:cntrl:] ()<>@,;:\\\"/\\[\\]?=]+";

        // RFC822 "quoted-string" (inherited in RFC2045) accepts a sequence of any combination of:
        // - \\\\[[:ascii:]]          a backslash followed by any ASCII character
        // - any character which is NOT:
        //   - [:^ascii:]             outside the ASCII range
        //   - \"\\\\\\n              a double-quote, a backslash or a newline
        // wrapped in double-quotes
        static $quotedString = "\"(?:\\\\[[:ascii:]]|[^[:^ascii:]\"\\\\\\n])*\"";

        // a MIME type can be one of the discrete (text, image, audio, video, application) or
        // composite types (message, multipart) an x-token (basically a custom application-specific
        // extension beginning with "x-") or an ietf-token. ietf-tokens are a moving target - it's
        // whatever has been approved by IANA via an RFC track. so it's impossible to validate
        // completely without keeping up-to-date with IANA approvals. in lieu of this, any sequence
        // of a-z characters is accepted because this is a) a subset of the valid characters for a
        // token; b) is guaranteed not to clash with any application-specific extensions, and
        // c) covers all known discrete- and composite- tokens, and is likely to cover all IANA
        // approved tokens (as of Jan 2018 all IANA-approved tokens are exclusively ASCII).
        //
        // on Jan 2018, the list of MIME types was available here:
        // https://www.iana.org/assignments/media-types/media-types.xhtml
        //
        // also, apparently they're now called "media types".
        static $rxMimeType = null;        // initialised immediately before first use

        $contentType = trim($contentType);

        /* validate the content type */
        if ("*/*" !== $contentType) {
            // can't initialise static $rxMimeType with non-const content so have to do it this way
            if (is_null($rxMimeType)) {
                $rxMimeType = "#^([a-z]+|x-{$token})/({$token})( *; *{$token} *= *(?:{$token}|{$quotedString}))*\$#";
            }

            // for now we don't use the expression captures, but 1 = type, 2 = subtype, 3 = params
            if (!preg_match($rxMimeType, $contentType)) {
                throw new InvalidArgumentException("Content type \"{$contentType}\" is not valid.");
            }
        }

        $header = $this->headerByName("Content-Type");

        if ($header instanceof Header) {
            $header->setValue($contentType);
        } else {
            $this->traitAddHeader(new Header("Content-Type", $contentType));
        }
    }

    /**
     * Gets the Content-Transfer-Encoding for the message part.
     *
     * @return string The `Content-Transfer-Encoding` header value.
     */
    public function contentEncoding(): string
    {
        $header = $this->headerByName("Content-Transfer-Encoding");
        assert(isset($header), new LogicException("Content-Transfer-Encoding header not found"));
        return $header->value();
    }

    /**
     * Sets the *Content-Transfer-Encoding* for the message part.
     *
     * ### Note
     * This does not magically re-encode the content. This is simply the type that will be reported for the content you
     * provide for the email message body part.
     *
     * @param $contentEncoding string is the content encoding. It is assumed to be a UTF-8 string.
     */
    public function setContentEncoding(string $contentEncoding): void
    {
        /* validate the content encoding: x-gzip | x-compress | token */
        if ("x-gzip" !== $contentEncoding && "x-compress" !== $contentEncoding) {
            /* FIXME validate the content-encoding properly */
            if ("" === trim($contentEncoding)) {
                throw new InvalidArgumentException("Content encoding \"$contentEncoding\" is not valid.");
            }
        }

        $header = $this->headerByName("Content-Transfer-Encoding");

        if ($header instanceof Header) {
            $header->setValue($contentEncoding);
        } else {
            $this->traitAddHeader(new Header("Content-Transfer-Encoding", $contentEncoding));
        }
    }

    /**
     * Gets the body content of the message part.
     *
     * This method returns the content exactly as provided. Part objects do not force the content to conform to
     * RFC2045 by chunking it into 76 character lines. It is therefore up to the containing class (usually Email) to
     * chunk up the data if required. The php function `chunk_split()` serves this purpose well.
     *
     * On a successful call, the content provided is always a byte sequence represented as a PHP string. It will be the
     * exact content provided either in the constructor or to `setContent()` if used after construction.
     *
     * @return string The part content, as provided in the call to `setContent()`.
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * Sets the body content of the message part.
     *
     * The content must be a PHP string. It is regarded internally as a sequence of bytes. It need not be pre-formatted
     * to conform to RFC2045. It is the responsibility of the code that uses the part for output to construct a
     * multipart message to ensure that content is properly formatted to conform to RFC2045.
     *
     * @param $content string The content for the message part.
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }
}
