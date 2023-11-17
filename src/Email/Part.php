<?php

declare(strict_types=1);

namespace Bead\Email;

use Bead\Contracts\Email\Multipart as MultipartContract;
use Bead\Contracts\Email\Part as PartContract;
use InvalidArgumentException;

use function trim;

/**
 * Class representing a part of a multipart email message.
 *
 * Parts can themselves consist of multiple parts. Adding a part to a part removes its existing body content; adding
 * body content removes its parts. So use one or the other for your message parts.
 */
class Part implements PartContract, MultipartContract
{
    use HasHeaders;
    use HasParts;

    /** @var string The default content type for email message parts. */
    public const DefaultContentType = "text/plain";

    /** @var string The default content encoding for email message parts. */
    public const DefaultContentEncoding = "quoted-printable";

    /** @var string|null The part's content, unless it's multipart. */
    private ?string $body = null;

    /**
     * Create a new message part.
     *
     * The content is actually simply a byte sequence. The content must already be of the intended MIME type and encoded
     * according to the intended *content-transfer-encoding*. Objects of this class do not do any translation or
     * conversion of content.
     *
     * The default content type for message parts is *text/plain* and the default encoding is *quoted-printable*.
     *
     * @param $content string|PartContract|null The content.
     * @param $contentType string The content type for the message part.
     * @param $contentEncoding string The content encoding.
     */
    function __construct(string|PartContract|null $content = null, string $contentType = self::DefaultContentType, string $contentEncoding = self::DefaultContentEncoding)
    {
        $this->setHeader("content-type", $contentType);
        $this->setHeader("content-transfer-encoding", $contentEncoding);

        if ($content instanceof PartContract) {
            $this->parts[] = $content;
        } else {
            $this->body = $content;
        }
    }

    /**
     * Gets the `Content-Type` for the message part.
     *
     * @return string|null The value of the *Content-Type* header.
     */
    public function contentType(): ?string
    {
        return $this->header("content-type")?->value();
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
     * Gets the Content-Transfer-Encoding for the message part.
     *
     * @return string|null The `Content-Transfer-Encoding` header value.
     */
    public function contentEncoding(): ?string
    {
        return $this->header("content-transfer-encoding")?->value();
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
     * @return $this A clone of the Part with the given encoding.
     */
    public function withContentEncoding(string $contentEncoding): self
    {
        if (!Mime::isValidContentTransferEncoding($contentEncoding)) {
            throw new InvalidArgumentException("Content encoding \"{$contentEncoding}\" is not valid.");
        }

        return $this->withHeader("content-transfer-encoding", $contentEncoding);
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
     * @return string|null The part's body, as provided in the call to `setContent()`.
     */
    public function body(): ?string
    {
        if (!empty($this->parts)) {
            $this->body = null;
        }

        return $this->body;
    }

    /**
     * Sets the body content of the message part.
     *
     * Set the part to have the given body content. All parts will be discarded. The content type will not be updated,
     * so you must also set the header to the appropriate type.
     *
     * @param $body string The content for the message part.
     *
     * @return $this A clone of the Part with the given body.
     */
    public function withBody(string $body): self
    {
        $clone = clone $this;
        $this->parts = [];
        $clone->body = $body;
        return $clone;
    }
}
