<?php

declare(strict_types=1);

namespace Bead\Email;

use Bead\Contracts\Email\Header as HeaderContract;
use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Multipart as MutlipartContract;
use InvalidArgumentException;
use LogicException;

use function Bead\Helpers\Iterable\all;

/**
 * Class encapsulating an email message.
 *
 * Messages are always of type `multipart/mixed` for now, but may be expanded in future to support other subtypes of
 * multipart. It will never directly support non-MIME messages; simple `text/plain` messages are always encapsulated
 * as multipart MIME messages with a single body part.
 */
class Message implements MessageContract, MutlipartContract
{
    use HasHeaders;
    use HasParts;

    private ?string $body = null;

    /**
     * Constructor.
     *
     * A text/plain, quoted-printable message will be created.
     *
     * The parameter names are guaranteed to remain stable within major versions of the framework.
     *
     * @api
     *
     * @param $to string|null The destination address for the message.
     * @param $subject string|null The subject for the message.
     * @param $body string|null The initial body content for the message.
     * @param $from string|null The sender of the message.
     *
     * @throws InvalidArgumentException if any invalid header is found.
     */
    public function __construct(?string $to = null, ?string $subject = null, ?string $body = null, ?string $from = null)
    {
        $this->body = $body;
        $this->headers[] = new Header("content-type", "text/plain");
        $this->headers[] = new Header("content-transfer-encoding", "quoted-printable");

        if (is_string($to)) {
            $this->headers[] = new Header("to", $to);
        }

        if (is_string($from)) {
            $this->headers[] = new Header("from", $from);
        }

        if (is_string($subject)) {
            $this->headers[] = new Header("subject", $subject);
        }
    }

    /**
     * Gets the content type of the email message.
     *
     * @api
     * @return string The message content type.
     */
    public function contentType(): string
    {
        $contentType = $this->header("content-type");
        assert($contentType instanceof HeaderContract, new LogicException("It is an invariant that Message instances have a content-type header"));
        return $contentType->value();
    }

    /**
     * Sets the content type of the message.
     *
     * Setting the content type does not transform the content. The caller is responsible for ensuring the content is
     * correct for the type.
     *
     * @api
     * @param $contentType string the new content type.
     * @param $parameters array<string,string> the content type header parameters, if any.
     *
     * @return $this A clone of the Message, with the content type set to that provided.
     * @throws InvalidArgumentException if the content type is not valid.
     */
    public function withContentType(string $contentType, array $parameters = []): self
    {
        $contentType = trim($contentType);

        if (!Mime::isValidMediaType($contentType)) {
            throw new InvalidArgumentException("Expected valid media type, found \"{$contentType}\"");
        }

        return $this->withHeader(new Header("content-type", $contentType, $parameters));
    }

    /**
     * Gets the transfer encoding of the email message.
     *
     * @api
     * @return string The message transfer encoding.
     */
    public function contentTransferEncoding(): string
    {
        $contentEncoding = $this->header("content-transfer-encoding");
        assert($contentEncoding instanceof HeaderContract, new LogicException("It is an invariant that Message instances have a content-transfer-encoding header"));
        return $contentEncoding->value();
    }

    /**
     * Sets the content transfer encoding of the message.
     *
     * Setting the content transfer encoding does not transform the content. The caller is responsible for ensuring the
     * content is correct for the content transfer encoding.
     *
     * @api
     * @param $contentEncoding string the new content transfer encoding.
     * @param $parameters array<string,string> the content transfer encoding header parameters, if any.
     *
     * @return $this A clone of the Message, with the content transfer encoding set to that provided.
     * @throws InvalidArgumentException if the transfer encoding is not valid.
     */
    public function withContentTransferEncoding(string $contentEncoding, array $parameters = []): self
    {
        $contentEncoding = trim($contentEncoding);

        if (!Mime::isValidContentTransferEncoding($contentEncoding)) {
            throw new InvalidArgumentException("Expected valid content transfer encoding, found \"{$contentEncoding}\"");
        }

        return $this->withHeader(new Header("content-transfer-encoding", $contentEncoding, $parameters));
    }

    /**
     * Gets the subject of the email message.
     *
     * @api
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
        /** @psalm-suppress MissingThrowsDocblock these args are guaranteed to be valid. */
        return $this->withHeader("subject", $subject);
    }

    /**
     * Gets the recipients of the message.
     *
     * @api
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
     * @api
     * @param $address string|string[] the recipient address(es) to add.
     * @throws InvalidArgumentException if an array of addresses is provided and not all are strings
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
     * @api
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
     * @throws InvalidArgumentException if an array of addresses is provided and not all are strings
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
        return $this->headerValues("Bcc");
    }

    /**
     * Add a recipient of the message.
     *
     * The recipient's address is added to the Bcc list. The address should be provided in RFCxxxx format, although this
     * rule is not strictly enforced (yet).
     *
     * @param $address string|string[] the BCC recipient address(es) to add.
     * @throws InvalidArgumentException if an array of addresses is provided and not all are strings
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
        /** @psalm-suppress MissingThrowsDocblock These args are guaranteed to be valid. */
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
     * @return string|null The full body of the email message.
     */
    public function body(): ?string
    {
        if (!empty($this->parts)) {
            $this->body = null;
        }

        return $this->body;
    }

    /**
     * Sets the body of the message from a string.
     *
     * ### Note Using this function replaces all existing message parts with a single body content (or null). You must
     * ensure the message has an appropriate content-type header for the content provided.
     *
     * @param $body string|null The string to use as the body of the message, or `null` to clear the current body.
     */
    public function withBody(?string $body): self
    {
        $clone = clone $this;
        $clone->parts = [];
        $clone->body = $body;
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
     *
     * @throws InvalidArgumentException if the content type or content encoding (or both) are not valid.
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
