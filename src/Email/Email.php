<?php

declare(strict_types=1);

namespace Bead\Email;

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
 * Messages are always of type `multipart/mixed` for now, but may be expanded in future to support other subtypes of
 * multipart. It will never directly support non-MIME messages; simple `text/plain` messages are always encapsulated
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
    use HasHeaders {
        headers as private traitHeaders;
        addHeader as private traitAddHeader;
        clearHeaders as private traitClearHeaders;
    }

    /** @var string A single linefeed character. */
    private const LF = "\n";

    /** @var string The default delimiter to use between parts in the message body. */
    private const DefaultDelimiter = "--email-delimiter-16fbcac50765f150dc35716069dba9c9--";

    /**
     * some old (< 2.9 AFAIK) versions of postfix need the line end to be this on *nix
     *
     * @var string The line ending to use in the message body during transmission.
     */
    private const LineEnd = self::LF;

    /** @var Part[] The parts of the email body. */
    protected array $parts = [];

    /** @var string The delimiter for parts of the email body. */
    protected string $partDelimiter = Email::DefaultDelimiter;

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
     * @param $headers array<string|Header>|null The initial set of headers for the message.
     */
    public function __construct(?string $to = null, ?string $subject = "", ?string $msg = null, string $from = "", ?array $headers = null)
    {
        $this->addTo($to);
        $this->setBody($msg);

        if (is_array($headers)) {
            foreach ($headers as $header) {
                if ($header instanceof Header) {
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
     * Gets the headers for the message.
     *
     * The headers for the message are always returned as an array of `Header` objects. If there are none set, an
     * empty array will be returned.
     *
     * The Mime-Version, Content-Type and Content-Transfer-Encoding headers are automatically added. These cannot be
     * overridden or modified.
     *
     * @return Header[] The headers for the message.
     */
    public function headers(): array
    {
        $contentType = new Header("Content-Type", "multipart/mixed");
        $contentType->setParameter("boundary", "\"{$this->partDelimiter}\"");
        return [
            ...$this->traitHeaders(),
            new Header("MIME-Version", "1.0"),
            new Header("Content-Transfer-Encoding", "7bit"),
            $contentType
        ];
    }

    /**
     * Add a header to the message.
     *
     * The From and Subject headers are special - these overwrite any existing header rather than adding new values (i.e
     * there is never more than one From and one Subject header.
     *
     * @param Header|string $header The header to add.
     * @param string|null $value The value for the header, if `$header` is a header name.
     */
    public function addHeader(Header|string $header, ?string $value = null): void
    {
        if (is_string($header)) {
            $header = new Header($header, (string) $value);
        }

        switch (mb_strtolower($header->name(),"UTF-8")) {
            case "from":
                $this->setFrom($header->value());
                break;

            case "subject":
                $this->setSubject($header->value());
                break;

            default:
                $this->traitAddHeader($header);
                break;
        }
    }

    /**
     * Clears all headers from the email message.
     *
     * The required headers `MIME Version`, `Content-Type`, `To`, `Cc`, `Bcc`, `From`, `Subject` and
     * `Content-Transfer-Encoding` will be retained - these headers cannot be cleared.
     */
    public function clearHeaders(): void
    {
        $retainedHeaders = array_filter(
            $this->headers,
            fn(Header $header): bool => in_array(
                mb_strtolower($header->name(), "UTF-8"),
                ["mime-version", "content-type", "to", "cc", "bcc", "from", "subject", "content-transfer-encoding",]
            )
        );

        $this->traitClearHeaders();

        foreach ($retainedHeaders as $header) {
            $this->addHeader($header);
        }
    }

    /**
     * Gets the subject of the email message.
     *
     * @return string The message subject.
     */
    public function subject(): string
    {
        return $this->headerByName("subject")?->value() ?? "";
    }

    /**
     * Sets the subject of the email message.
     *
     * @param $subject string the new subject of the email message.
     */
    public function setSubject(string $subject): void
    {
        $header = $this->headerByName("Subject");

        if (!isset($header)) {
            $this->traitAddHeader(new Header("Subject", $subject));
        } else {
            $header->setValue($subject);
        }
    }

    /**
     * Gets the recipients of the message.
     *
     * @return string[] The primary recipients of the message.
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
     * @param $address string|string[] the new recipient address(es).
     */
    public function addTo(string|array $address): void
    {
        if (is_array($address)) {
            if (!all($address, "is_string")) {
                throw new InvalidArgumentException("Addresses provided to addTo() must all be strings.");
            }
        } else {
            $address = [$address];
        }

        foreach ($address as $addr) {
            $this->traitAddHeader(new Header("To", $addr));
        }
    }

    /**
     * Gets the carbon-copy recipients of the message.
     *
     * The cc recipients are returned as an array of addresses. If there are none, this will be an empty array.
     *
     * @return string[] The CC recipients.
     */
    public function cc(): array
    {
        return $this->headerValues("Cc");
    }

    /**
     * Add a recipient of the message.
     *
     * The recipient's address is added to the Cc list. The address should be provided in RFCxxxx format, although this
     * rule is not strictly enforced (yet).
     *
     * @param $address string|string[] the new recipient.
     */
    public function addCc(string|array $address): void
    {
        if (is_array($address)) {
            if (!all($address, "is_string")) {
                throw new InvalidArgumentException("Addresses provided to addCc() must all be strings.");
            }
        } else {
            $address = [$address];
        }

        foreach ($address as $addr) {
            $this->traitAddHeader(new Header("Cc", $addr));
        }
    }

    /**
     * Gets the blind-carbon-copy recipients of the message.
     *
     * The BCC recipients are returned as an array of addresses. If there are none, this will be an empty array.
     *
     * @return string[] The BCC recipients.
     */
    public function bcc(): array
    {
        return $this->headerValues("Bcc");
    }

    /**
     * Add a recipient of the message.
     *
     * The recipient's address is added to the Bcc list. The address should be provided in RFCxxxx format, although this
     * rule is not strictly enforced (yet).
     *
     * @param $address string|string[] the new recipient.
     */
    public function addBcc(string|array $address): void
    {
        if (is_array($address)) {
            if (!all($address, "is_string")) {
                throw new InvalidArgumentException("Addresses provided to addBcc() must all be strings.");
            }
        } else {
            $address = [$address];
        }

        foreach ($address as $addr) {
            $this->traitAddHeader(new Header("Bcc", $addr));
        }
    }

    /**
     * Gets the sender of the message.
     *
     * @return string The message sender.
     */
    public function from(): string
    {
        return $this->headerByName("From")?->value() ?? "";
    }

    /**
     * Sets the sender of the message.
     *
     * The sender should be provided in RFCxxxx format, although this rule is not strictly enforced (yet).
     *
     * @param $sender string the new sender of the message.
     */
    public function setFrom(string $sender): void
    {
        $header = $this->headerByName("From");

        if (isset($header)) {
            $header->setValue($sender);
        } else {
            $this->traitAddHeader(new Header("From", $sender));
        }
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
            $ret .= self::LineEnd . "--{$this->partDelimiter}" . self::LineEnd;

            /* output the part headers  */
            foreach ($part->headers() as $header) {
                $ret .= $header->generate() . self::LineEnd;
            }

            $ret .= self::LineEnd . $part->content() . self::LineEnd;
        }

        return "$ret--{$this->partDelimiter}--";
    }

    /**
     * Get the parts for the message body.
     *
     * @return array[EmailPart] The parts, or `null` on error.
     */
    public function parts(): array
    {
        return $this->parts;
    }

    /**
     * Get the number of parts for the message body.
     *
     * @return int The number of parts.
     */
    public function partCount(): int
    {
        return count($this->parts);
    }

    /**
     * Sets the body of the message from a string.
     *
     * ### Note Using this function replaces all existing message body parts with a single plain text body part.
     *
     * @param $body string|null The string to use as the body of the message, or `null` to clear the current parts.
     */
    public function setBody(?string $body): void
    {
        if (is_null($body)) {
            $this->parts = [];
            return;
        }

        // by default parts have content-type text/plain, content-transfer-encoding: quoted-printable
        $this->parts = [new Part($body)];
    }

    /**
     * Add a body part to the email message.
     *
     * Parts are always added to the end of the message. When adding unwrapped part content to the email, if no type or
     * encoding is provided, the defaults of `text/plain` (in UTF-8 character encoding) and `quoted-printable` will be
     * used respectively. It is the client code's responsibility to ensure that the data in the content string provided
     * matches the type and transfer encoding specified. No checks, translations or conversions will be carried out.
     *
     * @param Part|string $part The part to add.
     * @param string|null $contentType The content type for the part, if `$part` is a string.
     * @param string|null $contentEncoding The content transfer encoding for the part, if `$part` is a string.
     */
    public function addBodyPart(Part|string $part, ?string $contentType = null, ?string $contentEncoding = null): void
    {
        if (is_string($part)) {
            $part = new Part($part);
            $part->setContentType((string) $contentType);
            $part->setContentEncoding((string) $contentEncoding);
        }

        $this->parts[] = $part;
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
        $newPart = new Part($content);
        $newPart->setContentType($contentType);
        $newPart->setContentEncoding($contentEncoding);
        $newPart->addHeader(
            "Content-Disposition",
            "attachment; filename=\"" . str_replace("\"", "\\\"", $filename) . "\""
        );

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
        $headerString = "";

        foreach ($this->headers() as $header) {
            if (0 === strcasecmp("subject", $header->name())) {
                continue;
            }

            $headerString .= $header->generate() . self::LineEnd;
        }

        return mail(implode(",", array_unique(array_merge($this->to(), $this->cc(), $this->bcc()))), $this->subject(), $this->body(), $headerString);
    }
}
