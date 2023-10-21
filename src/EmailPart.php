<?php

namespace Bead;

/**
 * Class representing a part of a multipart email message.
 *
 * Objects of this class can be used as parts in a multipart email message.
 */
class EmailPart
{
    /** @var string The default content type for email message parts. */
    protected const DefaultContentType = "text/plain";

    /** @var string The default character encoding for email message parts. */
    protected const DefaultTextCharset = "utf-8";

    /** @var string The default content encoding for email message parts. */
    protected const DefaultContentEncoding = "quoted-printable";

    /** @var EmailHeader[] The part's headers. */
    protected array $m_headers = [];

    /** @var string The part's content. */
    protected string $m_content = "";

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
    public function __construct(string $content = "", string $contentType = EmailPart::DefaultContentType, string $contentEncoding = EmailPart::DefaultContentEncoding)
    {
        // setContentType() and setContentEncoding() can fail with invalid values, so we ensure here that the object
        // is initialised with defaults that are known to be valid: a quoted-printable-encoded text/plain body part
        $this->setContentType(EmailPart::DefaultContentType);
        $this->setContentEncoding(EmailPart::DefaultContentEncoding);

        $this->setContentType($contentType);
        $this->setContentEncoding($contentEncoding);
        $this->setContent($content);
    }

    /**
     * Adds a header line to the email message part.
     *
     * This is a convenience function to allow addition of pre-formatted headers to an email message part. Headers are
     * formatted as:
     *
     *     <key>:<value><cr><lf>
     *
     * This function will allow headers to be added either with or without the trailing <cr><lf>; in either case, the
     * resulting headers retrieved using `headers()` will be correctly formatted.
     *
     * Headers that do not contain the **:** delimiter will be rejected. Only the first instance of **:** is considered
     * a delimiter; anything after is treated as the value of the header. Multiple headers may not be added using a
     * single call to this method. Such attempts will be rejected.
     *
     * ### Note
     * This method does not yet ensure the validity of the name and value provided for the header. This behaviour must
     * not be relied upon - it should be assumed that this method will reject invalid header names or values in future.
     *
     * @param $header string The header line to add.
     *
     * @return bool `true` if the header was successfully added to the message part, `false` otherwise.
     */
    public function addHeaderLine(string $header): bool
    {
        $header = trim($header);

        if (empty($header)) {
            AppLog::error("empty header line provided");
            return false;
        }

        /* check for attempt to add multiple header lines  */
        if (strpos($header, Email::LineEnd) !== false) {
            AppLog::error("header provided contains more than one header");
            return false;
        }

        $rxMimeHeader = "/^ *([^[:^ascii:][:cntrl:]: ]+) *: *(.*) *$/";

        // TODO trigger_error() instead?
        if (!preg_match($rxMimeHeader, $header, $captures)) {
            AppLog::error("invalid header line provided (\"{$header}\")");
            return false;
        }

        /* EmailHeader constructor handles validation */
        return $this->addHeader(new EmailHeader($captures[0], $captures[1]));
    }

    /**
     * Add a header from a EmailHeader object.
     *
     * @param $header EmailHeader The header object.
     *
     * @return bool `true` if the header was added successfully, `false` otherwise.
     */
    private function addHeaderObject(EmailHeader $header): bool
    {
        // check for <cr><lf> in either header or value
        $headerName = $header->name();
        $headerValue = $header->value();

        if (!isset($headerName) || !isset($headerValue)) {
            AppLog::error("invalid header or value (or both)");
            return false;
        }

        if (false !== strpos(Email::LineEnd, $headerName) || false !== strpos(Email::LineEnd, $headerValue)) {
            AppLog::error("possible multi-header object");
            return false;
        }

        switch (mb_convert_case($headerName, MB_CASE_LOWER, "UTF-8")) {
            case "content-type":
                return $this->setContentType($headerValue);

            case "content-transfer-encoding":
                return $this->setContentEncoding($headerValue);

            default:
                $this->m_headers[] = $header;
        }

        return true;
    }

    /**
     * Add a header from a pair of strings.
     *
     * @param $header string The name of the header.
     * @param $value string The value for the header.
     *
     * @return bool `true` if the header was added successfully, `false` otherwise.
     */
    private function addHeaderStrings(string $header, string $value): bool
    {
        // EmailHeader constructor handles validation
        return $this->addHeaderObject(new EmailHeader($header, $value));
    }

    /**
     * Adds a header to the email message.
     *
     * The header may be a EmailHeader object or a PHP string. If it is the former, the value parameter is ignored in
     * favour of the value set in the header object. If it is a string, the value parameter must be provided and must
     * also be a string. PHP strings are always assumed to be encoded using UTF-8.
     *
     * @param $header EmailHeader|string The header to add.
     * @param $value string is the value for the header.
     *
     * @return bool `true` if the header was added, `false` otherwise.
     */
    public function addHeader(EmailHeader|string $header, string $value = ""): bool
    {
        if ($header instanceof EmailHeader) {
            return $this->addHeaderObject($header);
        } else {
            return $this->addHeaderStrings($header, $value);
        }
    }

    /**
     * Clears all headers from the email message part.
     *
     * The required headers *Content-Type* and *Content-Transfer-Encoding* will be retained - these headers cannot be
     * cleared. If you want to reset the content type and content encoding to their default values you must make the
     * following calls, respectively:
     * - `$part->setContentType(EmailPart::DefaultContentType);`
     * - `$part->setContentEncoding(EmailPart::DefaultContentEncoding);`
     *
     * @return bool `true` if the headers were cleared, `false` otherwise.
     */
    public function clearHeaders(): bool
    {
        $contentType = $this->contentType();
        $contentEncoding = $this->contentEncoding();
        $this->m_headers = [];

        if (!$this->setContentType($contentType)) {
            $this->setContentType(EmailPart::DefaultContentType);
        }

        if (!$this->setContentEncoding($contentEncoding)) {
            $this->setContentEncoding(EmailPart::DefaultContentEncoding);
        }

        return true;
    }


    /**
     * Retrieves the value(s) associated with a header.
     *
     * It is legitimate to specify some headers multiple times in and email part, so this method always returns an array
     * on a successful call.
     *
     * If the header is not specified for the message part, an empty array will be returned. If the header is specified
     * multiple times, the array will contain one element for each time the header has been specified.
     *
     * @param $headerName string The name header whose value/s is/are sought. This must be UTF-8 encoded.
     *
     * @return array[string] All the values specified for the header.
     */
    public function headerValues(string $headerName): array
    {
        $ret = [];

        foreach ($this->m_headers as $header) {
            if (0 === strcasecmp($headerName, $header->name())) {
                $ret[] = $header->value();
            }
        }

        return $ret;
    }

    /**
     * Retrieves an array of properly formatted headers for the message part.
     *
     * @return array[EmailHeader] objects.
     */
    public function headers(): array
    {
        return $this->m_headers;
    }

    /**
     * Find a header object by its name.
     *
     * This method will check through the set of headers in the message part and return the first one whose name matches
     * that provided.
     *
     * @param $name string is the name of the header object to find.
     *
     * @return EmailHeader|null The header object if found, or `null` if not or on error.
     */
    private function findHeaderByName(string $name): ?EmailHeader
    {
        foreach ($this->headers() as $header) {
            if (0 === strcasecmp($header->name(), $name)) {
                return $header;
            }
        }

        return null;
    }

    /**
     * Gets the *Content-Type* for the message part.
     *
     * @return string|null The value of the *Content-Type* header, or `null` on error.
     */
    public function contentType(): ?string
    {
        $header = $this->findHeaderByName("Content-Type");

        if (isset($header)) {
            return $header->value();
        }

        // This should never happen
        AppLog::error("Content-Type header not found");
        return null;
    }

    /**
     * Sets the Content-Type header for the message part.
     *
     * @param $contentType string The content type for the message part.
     *
     * @return bool `true` if the *Content-Type* header was set, `false` otherwise.
     */
    public function setContentType(string $contentType): bool
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
        if ("*/*" != $contentType) {
            // can't initialise static $rxMimeType with non-const content so have to do it this way
            if (is_null($rxMimeType)) {
                $rxMimeType = "#^([a-z]+|x-{$token})/(?:({$token})( *; *{$token} *= *(?:{$token}|{$quotedString}))*)$#";
            }

            // for now we don't use the expression captures, but 1 = type, 2 = subtype, 3 = params
            if (!preg_match($rxMimeType, $contentType)) {
                AppLog::error("content type \"{$contentType}\" is not valid");
                return false;
            }
        }

        $header = $this->findHeaderByName("Content-Type");

        if ($header instanceof EmailHeader) {
            $header->setValue($contentType);
        } else {
            $this->m_headers[] = new EmailHeader("Content-Type", $contentType);
        }

        return true;
    }

    /**
     * Gets the Content-Transfer-Encoding for the message part.
     *
     * @return string|null The *Content-Transfer-Encoding* header value, or `null` on error.
     */
    public function contentEncoding(): ?string
    {
        $header = $this->findHeaderByName("Content-Transfer-Encoding");

        if (isset($header)) {
            return $header->value();
        }

        AppLog::error("Content-Transfer-Encoding header not found");
        return null;
    }

    /**
     * Sets the *Content-Transfer-Encoding* for the message part.
     *
     * ### Note
     * This does not magically re-encode the content. This is simply the type that will be reported for the content you
     * provide for the email message body part.
     *
     * @param $contentEncoding string is the content encoding. It is assumed to be a UTF-8 string.
     *
     * @return bool `true` if the encoding was set, `false` otherwise.
     */
    public function setContentEncoding(string $contentEncoding): bool
    {
        /* validate the content encoding: x-gzip | x-compress | token */
        if ("x-gzip" != $contentEncoding && "x-compress" != $contentEncoding) {
            /* FIXME validate the content-encoding properly */
            if ("" == trim($contentEncoding)) {
                AppLog::error("content encoding provided (\"{$contentEncoding}\") is not valid");
                return false;
            }
        }

        $header = $this->findHeaderByName("Content-Transfer-Encoding");

        if ($header instanceof EmailHeader) {
            $header->setValue($contentEncoding);
        } else {
            $this->m_headers[] = new EmailHeader("Content-Transfer-Encoding", $contentEncoding);
        }

        return true;
    }

    /**
     * Gets the body content of the message part.
     *
     * This method returns the content exactly as provided. EmailPart objects do not force the content to conform to
     * RFC2045 by chunking it into 76 character lines. It is therefore up to the containing class (usually Email) to
     * chunk up the data if required. The php function *chunk_split()* serves this purpose well.
     *
     * On a successful call, the content provided is always a byte sequence represented as a PHP string. It will be the
     * exact content provided either in the constructor or to `setContent()` if used after construction.
     *
     * @return string The part content, as provided in the call to `setContent()`.
     */
    public function content(): string
    {
        return $this->m_content;
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
        $this->m_content = $content;
    }
}
