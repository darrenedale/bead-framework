<?php

namespace Bead;

use InvalidArgumentException;

use function Bead\Helpers\Iterable\all;

/**
 * Class encapsulating an email message.
 *
 * This class can be used to create and send email messages. It provides a comfortable object-oriented interface for
 * setting custom headers and additional `Cc` and `Bcc` recipients that can be challenging for new users of the
 * `mail()` function and makes it easy to create multipart messages. It guarantees that extra headers, for example,
 * will be set correctly.
 *
 * Messages are always of type *multipart/mixed* for now, but may be expanded in future to support other subtypes of
 * multipart. It will never directly support non-MIME messages; simple *text/plain* messages are always encapsulated
 * as multipart MIME messages with a single body part.
 *
 * That said, the class provides an interface to treat a message _as if_ it were a simple single-part message,
 * namely the `setBody()` family of methods. Use of any of these methods will immediately wipe all multipart content
 * in the message in favour of the supplied body content.
 *
 * To add more parts to a multipart message, use the `addBodyPart()`, `addBodyPartContent()` or `addAttachment()`
 * methods. The first two are generally more appropriate for content that is to be displayed inline; the latter is
 * for adding traditional file attachments to the message.
 *
 */
class Email
{
    /** @var string A single linefeed character. */
    private const LF = "\n";

    /** @var string The default delimiter to use between parts in the message body. */
    const DefaultDelimiter = "--email-delimiter-16fbcac50765f150dc35716069dba9c9--";

    /* some old (< 2.9 AFAIK) versions of postfix need the line end to be this on *nix */
    /** @var string The line ending to use in the message body during transmission. */
    const LineEnd = self::LF;

    /**
     * @var array|null The immutable headers for emails.
     *
     * This still being `null` acts as the trigger to initialise the class
     */
    private static ?array $s_immutableHeaders = null;

    /** @var EmailHeader[] The headers for the email. */
    protected array $m_headers = [];

    /** @var EmailPart[] The parts of the email body. */
    protected array $m_body = [];

    /** @var string The delimiter for parts of the email body. */
    protected string $m_bodyPartDelimiter = Email::DefaultDelimiter;

    /**
     * @var string[] The set of headers that have special treatment internally.
     *
     * Headers in this array are handled using special methods; the addHeader() methods handle redirection when used
     * to set them.
     */
    protected static array $s_specialHeaders = ["Content-Type", "To", "Cc", "Bcc", "From", "Subject", "Content-Transfer-Encoding"];

    /**
     * Constructor.
     *
     * The recipient and sender of the message may each be `null` to indicate that the recipient or sender is not
     * set. The recipient may not be an array of destination addresses: to add more addresses, use the `addTo()`,
     * `addCc()` or `addBcc()` methods.
     *
     * The subject is a special header which is handled independently of the other headers. It must be a string, or
     * `null` to indicate that the subject is not set.
     *
     * If the message body is `null` (default), an empty email will be created.
     *
     * The headers may be either an array of properly formatted mail header strings without the trailing `CRLF`, or
     * an array of `EmailHeader` objects. If it is an array of strings, they will be encapsulated within
     * `EmailHeader` objects internally. If `null` (default), no headers will be set. Note that should you
     * successfully set the message sender or subject in this way, they will be over-written with the sender and/or
     * subject set with the specific parameters for those headers, even if they are `null`.
     *
     * @param $to string|null The destination address for the message.
     * @param $subject string|null The subject for the message.
     * @param $msg string|null The initial body content for the message.
     * @param $from string The sender of the message.
     * @param $headers array<string|EmailHeader>|null The initial set of headers for the message.
     */
    public function __construct(?string $to = null, ?string $subject = "", ?string $msg = null, string $from = "", ?array $headers = null)
    {
        Email::initialiseClass();

        $this->addTo($to);
        $this->setBody($msg);

        if (is_array($headers)) {
            foreach ($headers as $header) {
                if ($header instanceof EmailHeader) {
                    $this->addHeader($header);
                } else if (is_string($header)) {
                    $this->addHeaderLine($header);
                }
            }
        }

        // do these after headers so that they take precedence over sender and subject set using direct headers
        $this->setFrom($from);
        $this->setSubject($subject);
    }

    /**
     * Initialise the class for its first use.
     *
     * This method initialises some internal static attributes ready for the first use of objects of the class.
     */
    private static function initialiseClass(): void
    {
        if (!isset(Email::$s_immutableHeaders)) {
            Email::$s_immutableHeaders = [new EmailHeader("Content-Transfer-Encoding", "7bit")];
        }
    }

    /**
     * Gets the headers for the message.
     *
     * The headers for the message are always returned as an array of `EmailHeader` objects. If there are none set, an
     * empty array will be returned.
     *
     * @return array[EmailHeader] The headers for the message.
     */
    public function headers(): array
    {
        $ret = Email::$s_immutableHeaders;
        $contentType = new EmailHeader('Content-Type', 'multipart/mixed');
        $contentType->setParameter('boundary', '"' . $this->m_bodyPartDelimiter . '"');
        $ret[] = $contentType;
        return array_merge($ret, $this->m_headers);
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
     * @return array[string] All the values assigned to the specified header, or `null` if an error occurred. The array
     * will be empty if the header is not specified.
     */
    public function headerValues(string $headerName): array
    {
        $ret = [];

        foreach ($this->m_headers as $header) {
            if (0 === strcasecmp($header->name(), $headerName)) {
                $ret[] = $header->value();
            }
        }

        return $ret;
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
     * ### Note
     * This method does not yet ensure the validity of the name and value provided for the header. This behaviour must
     * not be relied upon - it should be assumed that this method will reject invalid header names or values in future.
     *
     * @param $header string The header line to add.
     *
     * @return bool `true` if the header was successfully added to the message, `false` otherwise.
     */
    public function addHeaderLine(string $header): bool
    {
        $header = trim($header);

        if (empty($header)) {
            AppLog::error("empty header line provided");
            return false;
        }

        /* check for attempt to add multiple header lines  */
        if (preg_match("/\\r\\n[^\\t]/", $header)) {
            AppLog::error("header provided contains more than one header");
            return false;
        }

        // FIXME this currently leaves any parameters with the value ( which should still work OK for now)
        // Note Don't use structured decomposition because explode() might return an array of length 1, which would
        // trigger an E_NOTICE
        $header = explode(":", $header, 2);

        if (2 != count($header)) {
            AppLog::error("invalid header line provided (\"$header\")");
            return false;
        }

        /* EmailHeader constructor handles validation */
        return $this->addHeader(new EmailHeader(trim($header[0]), trim($header[1])));
    }

    /**
     * Add a header from an `EmailHeader` object.
     *
     * @param $header EmailHeader The header object.
     *
     * @return bool `true` if the header was added successfully, `false` otherwise.
     */
    private function _addHeaderObject(EmailHeader $header): bool
    {
        /* check for CRLF in either header or value  */
        $headerName = $header->name();
        $headerValue = $header->value();

        if (!(is_string($headerName)) || !(is_string($headerValue))) {
            AppLog::error("invalid header - missing header name or value (or both)");
            return false;
        }

        if (false !== strpos(self::LineEnd, $headerName)) {
            AppLog::error("invalid header - might contain more than one header line");
            return false;
        }

        switch (mb_convert_case($headerName, MB_CASE_LOWER, "UTF-8")) {
            case "to":
                return $this->addTo($headerValue);

            case "from":
                return $this->setFrom($headerValue);

            case "subject":
                $this->setSubject($headerValue);
                return true;

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
    private function _addHeaderStrings(string $header, string $value): bool
    {
        /* EmailHeader constructor handles validation */
        return $this->_addHeaderObject(new EmailHeader($header, $value));
    }

    /**
     * Adds a header to the email message.
     *
     * ### Note
     * This method cannot be used to set the subject, content-type, content-encoding or sender of the message. See the
     * `setSubject()` and `setFrom()` methods. The `content-type` and `content-encoding` headers are fixed.
     *
     * @param $header string|EmailHeader The header to add.
     * @param $value string|null is the value for the header. Only used if $header is a string.
     *
     * @return bool `true` if the header was added, `false` otherwise.
     */
    public function addHeader(string|EmailHeader $header, ?string $value = null): bool
    {
        if ($header instanceof EmailHeader) {
            return $this->_addHeaderObject($header);
        } else {
            return $this->_addHeaderStrings($header, $value);
        }
    }

    /**
     * Remove a named header.
     *
     * All headers found with a matching name will be removed.
     *
     * @param $headerName string is the name of the header to remove.
     */
    private function _removeHeaderByName(string $headerName): void
    {
        for ($i = 0; $i < count($this->m_headers); ++$i) {
            if (0 === strcasecmp($this->m_headers[$i]->name(), $headerName)) {
                array_splice($this->m_headers, $i, 1);
                --$i;
            }
        }
    }

    /**
     * Remove a header.
     *
     * All headers found to match the specified header in name, value and all parameters will be removed.
     *
     * @param $header EmailHeader The header to remove.
     */
    private function _removeHeaderObject(EmailHeader $header): void
    {
        $headerName = $header->name();
        $headerValue = $header->value();

        if (is_null($headerName) || is_null($headerValue)) {
            return;
        }

        $n = count($this->m_headers);

        for ($i = 0; $i < $n; ++$i) {
            $retain = true;

            if (0 === strcasecmp($this->m_headers[$i]->name(), $headerName) && 0 === strcmp($this->m_headers[$i]->value(), $headerValue)) {
                $retain = false;

                foreach ($this->m_headers[$i]->parameters() as $key => $value) {
                    if (!$header->hasParameter($key) || $header->parameter($key) != $value) {
                        $retain = true;
                        break;
                    }
                }
            }

            if (!$retain) {
                array_splice($this->m_headers, $i, 1);
                --$i;
            }
        }
    }

    /**
     * Remove a header.
     *
     * Supplying a string will remove all headers with that name; providing an `EmailHeader` object will attempt to
     * remove a header that matches it precisely - including the header value and any parameters. If the header does not
     * match precisely any header in the message, no headers will be removed.
     *
     * @param $header string|EmailHeader The header to remove.
     *
     * @return bool `true` if the header has been removed or did not exist, `false` if an error occurred.
     */
    public function removeHeader(string|EmailHeader $header): bool
    {
        if ($header instanceof EmailHeader) {
            $this->_removeHeaderObject($header);
        } else {
            $this->_removeHeaderByName($header);
        }

        return true;
    }

    /**
     * Find a header by its name.
     *
     * This method will check through the set of headers in the message part and return the first one whose name matches
     * that provided.
     *
     * @param $name string The name of the header to find.
     *
     * @return EmailHeader|null The header if found, or `null` if not or on error.
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
     * Find all headers by name.
     *
     * This method will check through the set of headers in the message part and return all whose name matches that
     * provided.
     *
     * @param $name string is the name of the headers to find.
     *
     * @return array[EmailHeader] The headers if found, or an empty array if not.
     */
    private function _findAllHeadersByName(string $name): array
    {
        $ret = [];

        foreach ($this->headers() as $header) {
            if (strcasecmp($header->name(), $name)) {
                $ret[] = $header;
            }
        }

        return $ret;
    }

    /**
     * Clears all headers from the email message.
     *
     * The required headers `Content-Type`, `To`, `Cc`, `Bcc`, `From`, `Subject` and `Content-Transfer-Encoding` will be
     * retained - these headers cannot be cleared. If you want to reset the content type and content encoding to their
     * default values you must make the following calls, respectively:
     * - $part->setContentType(EmailPart::DEFAULT_CONTENT_TYPE);
     * - $part->setContentEncoding(EmailPart::DEFAULT_CONTENT_ENCODING);
     */
    public function clearHeaders(): void
    {
        $retainedHeaders = [];

        foreach (Email::$s_specialHeaders as $headerName) {
            $headers = $this->_findAllHeadersByName($headerName);

            foreach ($headers as $h) {
                $retainedHeaders[] = $h;
            }
        }

        // $s_immutableHeaders handles content-transfer-encoding retention
        $this->m_headers = $retainedHeaders;
    }

    /**
     * Gets the body of the message.
     *
     * The body of the message is formatted in a way that complies with RFC2045. Briefly, this means that the content of
     * message parts is split using `LineEnd` into lines of no more than 76 characters. An exception to this is any
     * message part that has a content type of *text/plain*, which is inserted into the message's main body as is
     * without any modification.
     *
     * @return string The full body of the email message.
     */
    public function body(): string
    {
        $ret = "";

        foreach ($this->parts() as $part) {
            $ret .= self::LineEnd . "--{$this->m_bodyPartDelimiter}" . self::LineEnd;

            /* output the part headers  */
            $headers = $part->headers();

            /** @var EmailHeader $header */
            foreach ($headers as $header) {
                $myHeader = $header->generate();

                if (empty($myHeader)) {
                    AppLog::error("invalid header: \"" . $header->name() . ": " . $header->value() . "\"");
                } else {
                    $ret .= $myHeader . self::LineEnd;
                }
            }

            $ret .= self::LineEnd . $part->content() . self::LineEnd;
        }

        return "$ret--{$this->m_bodyPartDelimiter}--";
    }

    /**
     * Get the parts for the message body.
     *
     * @return array[EmailPart] The parts, or `null` on error.
     */
    public function parts(): array
    {
        return $this->m_body;
    }

    /**
     * Get the number of parts for the message body.
     *
     * @return int The number of parts.
     */
    public function partCount(): int
    {
        return count($this->m_body);
    }

    /**
     * Sets the body of the message from a string.
     *
     * ### Note Using this function replaces all existing message body parts with a single plain text body part.
     *
     * @param $body string is the string to use as the body of the message.
     */
    public function setBody(?string $body): void
    {
        if (is_null($body)) {
            $this->m_body = [];
            return;
        }

        // by default parts have content-type text/plain, content-transfer-encoding: quoted-printable
        $this->m_body = [new EmailPart($body)];
    }

    /**
     * Gets the subject of the email message.
     *
     * @return string The message subject.
     */
    public function subject(): string
    {
        $header = $this->findHeaderByName("subject");

        if (isset($header)) {
            return $header->value();
        }

        return "";
    }

    /**
     * Sets the subject of the email message.
     *
     * @param $subject string the new subject of the email message.
     */
    public function setSubject(string $subject): void
    {
        $header = $this->findHeaderByName("Subject");

        if (!isset($header)) {
            $this->m_headers[] = new EmailHeader("Subject", $subject);
        } else {
            $header->setValue($subject);
        }
    }

    /**
     * Gets the recipients of the message.
     *
     * @return array[string] The primary recipients of the message.
     */
    public function to(): array
    {
        return $this->headerValues("To");
    }

    /**
     * Add a recipient of the message.
     *
     * The recipient should be provided in RFCxxxx format, although this rule is not strictly enforced (yet).
     *
     * @param $address string the new recipient address.
     *
     * @return bool `true` if the address was valid and was added to the recipient list, `false` otherwise.
     */
    public function addTo(string $address): bool
    {
        $this->m_headers[] = new EmailHeader("To", $address);
        return true;
    }

    /**
     * Add several recipients of the message.
     *
     * The recipients should be provided in RFCxxxx format, although this rule is not strictly enforced (yet). If
     * any address in the provided array is found to be invalid for any reason, none of the addresses in the array
     * will be added.
     *
     * @param $addresses string[] the new recipient addresses.
     *
     * @throws InvalidArgumentException if any of the provided addresses is not a string.
     */
    public function addToAddresses(array $addresses): void
    {
        if (!all($addresses, "is_string")) {
            throw new InvalidArgumentException("Addresses provided to addToAddresses() must all be strings.");
        }

        foreach ($addresses as $addr) {
            $this->m_headers[] = new EmailHeader("To", $addr);
        }
    }

    /**
     * Add a recipient of the message.
     *
     * The recipient's address is added to the Cc list. The address should be provided in RFCxxxx format, although this
     * rule is not strictly enforced (yet).
     *
     * @param $address string the new recipient.
     *
     * @return bool `true` if the address was valid and was added to the recipient list, `false` otherwise.
     */
    public function addCc(string $address): bool
    {
        $this->m_headers[] = new EmailHeader("Cc", $address);
        return true;
    }

    /**
     * Add several recipients of the message.
     *
     * The recipients should be provided in RFCxxxx format, although this rule is not strictly enforced (yet). If
     * any address in the provided array is found to be invalid for any reason, none of the addresses in the array
     * will be added.
     *
     * @param $addresses string[] the new recipient addresses.
     *
     * @throws InvalidArgumentException if any of the provided addresses is not a string.
     */
    public function addCcAddresses(array $addresses): void
    {
        if (!all($addresses, "is_string")) {
            throw new InvalidArgumentException("Addresses provided to addToAddresses() must all be strings.");
        }

        foreach ($addresses as $addr) {
            $this->m_headers[] = new EmailHeader("Cc", $addr);
        }
    }

    /**
     * Add a recipient of the message.
     *
     * The recipient's address is added to the Bcc list. The address should be provided in RFCxxxx format, although this
     * rule is not strictly enforced (yet).
     *
     * @param $address string the new recipient.
     *
     * @return bool `true` if the address was valid and was added to the recipient list, `false` otherwise.
     */
    public function addBcc(string $address): bool
    {
        $this->m_headers[] = new EmailHeader("Bcc", $address);
        return true;
    }

    /**
     * Add several recipients of the message.
     *
     * The recipients should be provided in RFCxxxx format, although this rule is not strictly enforced (yet). If any
     * address in the provided array is found to be invalid for any reason, none of the addresses in the array will
     * be added.
     *
     * @param $addresses string[] the new recipient addresses.
     *
     * @throws InvalidArgumentException if any of the provided addresses is not a string.
     */
    public function addBccAddresses(array $addresses): void
    {
        if (!all($addresses, "is_string")) {
            throw new InvalidArgumentException("Addresses provided to addToAddresses() must all be strings.");
        }

        foreach ($addresses as $addr) {
            $this->m_headers[] = new EmailHeader("Bcc", $addr);
        }
    }

    /**
     * Gets the sender of the message.
     *
     * @return string The message sender.
     */
    public function from(): string
    {
        $from = $this->findHeaderByName("From");

        if (isset($from)) {
            return $from->value();
        }

        return "";
    }

    /**
     * Sets the sender of the message.
     *
     * The sender should be provided in RFCxxxx format, although this rule is not strictly enforced (yet).
     *
     * @param $sender string the new sender of the message.
     *
     * @return bool `true` if the sender was set, `false` otherwise.
     */
    public function setFrom(string $sender): bool
    {
        $header = $this->findHeaderByName("From");

        if (isset($header)) {
            $header->setValue($sender);
        } else {
            $this->m_headers[] = new EmailHeader("From", $sender);
        }

        return true;
    }

    /**
     * Gets the carbon-copy recipients of the message.
     *
     * The cc recipients are returned as an array of addresses. If there are none, this will be an empty array.
     *
     * @return array[string] The CC recipients.
     */
    public function cc(): array
    {
        return $this->headerValues("Cc");
    }

    /**
     * Gets the blind-carbon-copy recipients of the message.
     *
     * The BCC recipients are returned as an array of addresses. If there are none, this will be an empty array.
     *
     * @return array[string] The BCC recipients.
     */
    public function bcc()
    {
        return $this->headerValues("Bcc");
    }

    /**
     * Add a body part to the email message.
     *
     * @param $part EmailPart The part to add.
     *
     * Parts are always added to the end of the message.
     */
    public function addBodyPart(EmailPart $part): void
    {
        $this->m_body[] = $part;
    }

    /**
     * Add a body part to the email message.
     *
     * If no type or encoding is provided, the defaults of *text/plain* (in UTF-8 character encoding) and
     * *quoted-printable* will be used respectively.
     *
     * It is the client code's responsibility to ensure that the data in the content string provided matches the type
     * and transfer encoding specified. No checks, translations or conversions will be carried out.
     *
     * @param $content string the content part to add.
     * @param $contentType string is the MIME type of the content part to add.
     * @param $contentEncoding string is the transfer encoding of the part to add.
     */
    public function addBodyPartContent(string $content, string $contentType = "text/plain; charset=\"utf-8\"", string $contentEncoding = "quoted-printable"): void
    {
        $part = new EmailPart($content);
        $part->setContentType($contentType);
        $part->setContentEncoding($contentEncoding);
    }

    /**
     * Add an attachment to the email message.
     *
     * If no type or encoding is provided, the defaults of *text/plain* (in UTF-8 character encoding) and
     * *quoted-printable* will be used respectively.
     *
     * It is the client code's responsibility to ensure that the data in the content string provided matches the type
     * and transfer encoding specified. No checks, translations or conversions will be carried out.
     *
     * ### Note
     * The filename parameter is not the name of the local file to attach to the email message. It is the default name
     * to give to the attachment when it is received and the recipient saves it.
     *
     * @param $content string The content of the attachment to add.
     * @param $contentType string The MIME type of the attachment to add.
     * @param $contentEncoding string The transfer encoding of the attachment to add.
     * @param $filename string The name of the file to assign to the attachment when it is attached to the message.
     */
    public function addAttachment(string $content, string $contentType, string $contentEncoding, string $filename): void
    {
        $newPart = new EmailPart($content);
        $newPart->setContentType("$contentType; name=\"$filename\"");
        $newPart->setContentEncoding($contentEncoding);
        $newPart->addHeader("Content-Disposition", "attachment");

        $this->addBodyPart($newPart);
    }

    /**
     * Send the message.
     *
     * Send the message using the internal PHP function `mail()`.
     *
     * @return bool `true` if the message was submitted for delivery, false otherwise.
     */
    public function send(): bool
    {
        /* get all headers except the subject, which is supplied separately in the mail() function  */
        $headers = $this->headers();
        $headerString = "MIME-Version: 1.0" . self::LineEnd;

        foreach ($headers as $header) {
            if (0 === strcasecmp("subject", $header->name())) {
                continue;
            }

            $myHeader = $header->generate();

            if (empty($myHeader)) {
                AppLog::error("invalid header: \"" . $header->name() . ": " . $header->value() . "\"");
            } else {
                $headerString .= $myHeader . self::LineEnd;
            }
        }

        return mail(implode(",", array_unique(array_merge($this->to(), $this->cc(), $this->bcc()))), $this->subject(), $this->body(), $headerString);
    }
}
