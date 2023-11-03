<?php

declare(strict_types=1);

namespace Bead\Email;

use Bead\Contracts\Email\Header as HeaderContract;
use Bead\Contracts\Email\MultipartMessage as MultipartMessageContract;
use Bead\Contracts\Email\Part as PartContract;
use InvalidArgumentException;

use function Bead\Helpers\Iterable\all;
use function Bead\Helpers\Iterable\fill;
use function Bead\Helpers\Iterable\toArray;

/**
 * Class encapsulating an email message.
 *
 * Messages are always of type `multipart/mixed` for now, but may be expanded in future to support other subtypes of
 * multipart. It will never directly support non-MIME messages; simple `text/plain` messages are always encapsulated
 * as multipart MIME messages with a single body part.
 */
class Message implements MultipartMessageContract
{
    use HasHeaders {
        withHeader as private traitWithHeader;
    }

    /** @var string The possible characters to use in the randomly-generated portion of the mulitpart delimiter. */
    private const DelimiterAlphabet = "abcdefghijklmnopqrstuvwxyz0123456789";

    /** @var PartContract[] The parts of the email body. */
    protected array $parts = [];

    /** @var string The delimiter for parts of the message body. */
    protected string $multipartBoundary;

    /**
     * Constructor.
     *
     * A Mime V1.0 multipart/mixed, 7bit-encoded message will be created by default. You can change this by setting the
     * appropriate headers, but you're encouraged not to do so. You cannot alter the multipart boundary that is used in
     * the message by setting the content-type header.
     *
     * Some headers are permitted only once (mime-version, content-type, content-transfer-encoding, from). For these, if
     * you provide multiple instances of those headers in your $headers array an InvalidArgumentException will be
     * thrown.
     *
     * @param $to string|null The destination address for the message.
     * @param $subject string|null The subject for the message.
     * @param $message string|null The initial body content for the message.
     * @param $from string|null The sender of the message.
     * @param $headers Header[] The initial set of headers for the message.
     *
     * @throws InvalidArgumentException if any invalid header is found.
     */
    public function __construct(?string $to = null, ?string $subject = null, ?string $message = null, ?string $from = null, array $headers = [])
    {
        if (!all($headers, fn(mixed $header): bool => $header instanceof HeaderContract)) {
            throw new InvalidArgumentException("Invalid header provided to Message constructor.");
        }

        // work out which headers the user has provided for which we shouldn't provide defaults
        $singleUseHeaders = array_combine(
            array_map(fn (string $headerName): string => strtolower($headerName), self::singleUseHeaders()),
            array_fill(0, count(self::singleUseHeaders()), false)
        );

        $this->multipartBoundary = "--bead-email-part-" . implode(toArray(fill(80, fn() => Message::DelimiterAlphabet[rand(0, 35)]))) . "--";

        foreach ($headers as & $header) {
            $headerName = strtolower($header->name());

            // if the caller provides a multipart content type, ensure it has the correct boundary string
            if ("content-type" === $headerName && str_starts_with(strtolower($header->value()), "multipart/")) {
                $header = $header->withParameter("boundary", "\"{$this->multipartBoundary}\"");
            }

            if (!array_key_exists($headerName, $singleUseHeaders)) {
                continue;
            }

            if ($singleUseHeaders[$headerName]) {
                throw new InvalidArgumentException("Header {$headerName} can only appear once.");
            }

            $singleUseHeaders[$headerName] = true;
        }

        if (is_string($to)) {
            $headers[] = new Header("to", $to);
        }

        if (is_string($message)) {
            $this->parts = [new Part($message)];
        }

        $this->headers = $headers;

        // fill in defaults for any missing headers that we require
        if (!($singleUseHeaders["content-type"] ?? false)) {
            $this->headers[] = (new Header("content-type", "multipart/mixed"))->withParameter("boundary", "\"{$this->multipartBoundary}\"");
        }

        if (!($singleUseHeaders["mime-version"] ?? false)) {
            $this->headers[] = new Header("mime-version", "1.0");
        }

        if (!($singleUseHeaders["content-transfer-encoding"] ?? false)) {
            $this->headers[] = new Header("content-transfer-encoding", "7bit");
        }

        // only now set the explicitly-provided sender and subject so they take precedence over headers
        if (is_string($from)) {
            $this->setHeader("from", $from);
        }

        if (is_string($subject)) {
            $this->setHeader("subject", $subject);
        }
    }

    /**
     * Clone the Message with an added header.
     *
     * If the header provided is the content-type header, and its value is a multipart media type, the boundary
     * parameter for the header will be set to the Message's boundary.
     *
     * @param Header|string $header
     * @param string|null $value
     *
     * @return self The clone of the Message, with the header set/added.
     */
    public function withHeader(Header|string $header, ?string $value = null, array $parameters = []): self
    {
        if (is_string($header)) {
            assert(is_string($value), new InvalidArgumentException("\$value must be provided when \$header is a string"));
            $header = new Header($header, $value, $parameters);
        }

        // ensure we always use the correct boundary
        if ("content-type" === strtolower($header->name()) && str_starts_with(strtolower($header->value()), "multipart/")) {
            $header = $header->withParameter("boundary", "\"{$this->multipartBoundary}\"");
        }

        return $this->traitWithHeader($header);
    }

    /**
     * Gets the subject of the email message.
     *
     * @return string The message subject.
     */
    public function subject(): string
    {
        return $this->header("subject")?->value() ?? "";
    }

    /**
     * Sets the subject of the email message.
     *
     * @param $subject string the new subject of the email message.
     */
    public function withSubject(string $subject): self
    {
        return $this->withHeader("subject", $subject);
    }

    /**
     * Gets the recipients of the message.
     *
     * @return string[] The primary recipients of the message.
     */
    public function to(): array
    {
        return $this->headerValues("to");
    }

    /**
     * Add a recipient of the message.
     *
     * The recipient should be provided in RFCxxxx format, although this rule is not strictly enforced (yet).
     *
     * @param $address string|string[] the recipient address(es) to add.
     */
    public function withTo(string|array $address): self
    {
        if (is_array($address)) {
            if (!all($address, "is_string")) {
                throw new InvalidArgumentException("Addresses provided to withTo() must all be strings.");
            }
        } else {
            $address = [$address];
        }

        $clone = clone $this;

        foreach ($address as $addr) {
            $clone = $clone->withHeader("to", $addr);
        }

        return $clone;
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
        return $this->headerValues("cc");
    }

    /**
     * Add a recipient of the message.
     *
     * The recipient's address is added to the Cc list. The address should be provided in RFCxxxx format, although this
     * rule is not strictly enforced (yet).
     *
     * @param $address string|string[] the CC recipient address(es) to add.
     */
    public function withCc(string|array $address): self
    {
        if (is_array($address)) {
            if (!all($address, "is_string")) {
                throw new InvalidArgumentException("Addresses provided to withCc() must all be strings.");
            }
        } else {
            $address = [$address];
        }

        $clone = clone $this;

        foreach ($address as $addr) {
            $clone = $clone->withHeader("cc", $addr);
        }

        return $clone;
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
        return $this->headerValues("bcc");
    }

    /**
     * Add a recipient of the message.
     *
     * The recipient's address is added to the Bcc list. The address should be provided in RFCxxxx format, although this
     * rule is not strictly enforced (yet).
     *
     * @param $address string|string[] the BCC recipient address(es) to add.
     */
    public function withBcc(string|array $address): self
    {
        if (is_array($address)) {
            if (!all($address, "is_string")) {
                throw new InvalidArgumentException("Addresses provided to withBcc() must all be strings.");
            }
        } else {
            $address = [$address];
        }

        $clone = clone $this;

        foreach ($address as $addr) {
            $clone = $clone->withHeader("bcc", $addr);
        }

        return $clone;
    }

    /**
     * Gets the sender of the message.
     *
     * @return string The message sender.
     */
    public function from(): string
    {
        return $this->header("from")?->value() ?? "";
    }

    /**
     * Sets the sender of the message.
     *
     * The sender should be provided in RFCxxxx format, although this rule is not strictly enforced (yet).
     *
     * @param $sender string the new sender of the message.
     */
    public function withFrom(string $sender): self
    {
        return $this->withHeader("from", $sender);
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
            $ret .= Mime::Rfc822LineEnd . "--{$this->multipartBoundary}" . Mime::Rfc822LineEnd;

            /* output the part headers  */
            foreach ($part->headers() as $header) {
                $ret .= $header->line() . Mime::Rfc822LineEnd;
            }

            $ret .= Mime::Rfc822LineEnd . $part->body() . Mime::Rfc822LineEnd;
        }

        return "{$ret}--{$this->multipartBoundary}--";
    }

    /**
     * Sets the body of the message from a string.
     *
     * ### Note Using this function replaces all existing message body parts with a single plain text body part.
     *
     * @param $body string|null The string to use as the body of the message, or `null` to clear the current parts.
     */
    public function withBody(?string $body): self
    {
        $clone = clone $this;

        // by default parts have content-type text/plain, content-transfer-encoding: quoted-printable
        $clone->parts = is_string($body) ? [new Part($body)] : [];

        return $clone;
    }

    /**
     * Get the parts for the message body.
     *
     * @return PartContract[] The parts, or `null` on error.
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
     * Add a body part to the email message.
     *
     * Parts are always added to the end of the message. When adding unwrapped part content to the email, if no type or
     * encoding is provided, the defaults of `text/plain` (in UTF-8 character encoding) and `quoted-printable` will be
     * used respectively. It is the client code's responsibility to ensure that the data in the content string provided
     * matches the type and transfer encoding specified. No checks, translations or conversions will be carried out.
     *
     * @param PartContract|string $part The part to add.
     * @param string|null $contentType The content type for the part, if `$part` is a string.
     * @param string|null $contentEncoding The content transfer encoding for the part, if `$part` is a string.
     */
    public function withPart(PartContract|string $part, ?string $contentType = null, ?string $contentEncoding = null): self
    {
        if (is_string($part)) {
            $part = new Part($part, (string) $contentType, (string) $contentEncoding);
        }

        $clone = clone $this;
        $clone->parts[] = $part;
        return $clone;
    }

    /** Make a filename safe for use with the content-disposition header. */
    private static function escapeAttachmentFilename(string $filename): string
    {
        return str_replace("\"", "\\\"", $filename);
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
    public function withAttachment(string $content, string $contentType, string $contentEncoding, string $filename): self
    {
        $dispositionHeader = new Header(
            "content-disposition",
            "attachment",
            ["filename" => "\"" . self::escapeAttachmentFilename($filename) . "\""]
        );

        $newPart = (new Part($content))
            ->withContentType($contentType)
            ->withContentEncoding($contentEncoding)
            ->withHeader($dispositionHeader);

        return $this->withPart($newPart);
    }
}
