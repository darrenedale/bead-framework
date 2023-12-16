<?php

declare(strict_types=1);

namespace Bead\Email;

use Bead\Contracts\Email\Header as HeaderContract;
use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\MimeBuilder as MimeBuilderContract;
use Bead\Contracts\Email\Multipart as MultipartContract;
use Bead\Contracts\Email\Part as PartContract;
use Bead\Exceptions\Email\MimeException;
use InvalidArgumentException;
use LogicException;
use phpDocumentor\Reflection\Exception\PcreException;

/**
 * Build a MIME message from a Message instance.
 */
class MimeBuilder implements MimeBuilderContract
{
    /** @var string The MIME version 1.0 */
    public const MimeVersion10 = "1.0";

    /** @var string The MIME version to use when building the message. */
    private string $mimeVersion;

    /** @var string The line end to use when building the MIME message. */
    private string $lineEnd = Mime::Rfc822LineEnd;

    /**
     * Initialise a new MIME message builder.
     *
     * @param string $mimeVersion The MIME version to use. Currently, the only MIME version in existence is 1.0.
     * @throws InvalidArgumentException if the MIME version is not supported.
     */
    public function __construct(string $mimeVersion = self::MimeVersion10)
    {
        if (!self::isSupportedMimeVersion($mimeVersion)) {
            throw new InvalidArgumentException("Expected supported MIME version, found \"{$mimeVersion}\"");
        }

        $this->mimeVersion = $mimeVersion;
    }

    /**
     * Extract the multipart boundary from a multipart message or part's headers.
     *
     * The caller is responsible for ensuring the message or part has a content-type header with a boundary parameter.
     *
     * @return string The boundary string.
     */
    private static function multipartBoundary(MessageContract|PartContract $source): string
    {
        assert($source instanceof MultipartContract, new LogicException("multipartBoundary() must only be called with Multipart instances"));
        $headers = $source->headers();
        $contentTypeHeader = null;

        for ($idx = 0; $idx < count($headers); ++$idx) {
            if ("content-type" === strtolower($headers[$idx]->name())) {
                $contentTypeHeader = $idx;
                break;
            }
        }

        assert(null !== $contentTypeHeader, new LogicException("content-type header must be guaranteed to exist at this point."));
        $boundary = null;

        foreach ($headers[$contentTypeHeader]->parameters() as $name => $value) {
            if (strtolower($name) === "boundary") {
                $boundary = trim($value);

                if ("\"" === $boundary[0] && "\"" === $boundary[-1]) {
                    $boundary = self::unescapeQuotedHeaderContent($boundary);
                }
            }
        }

        assert(null !== $boundary, new LogicException("content-type header must be guaranteed to have a valid boundary at this point."));
        return $boundary;
    }

    /**
     * Helper to remove the quotes from a quoted string in a header.
     *
     * It's the caller's responsibility to ensure that the string provided is genuinely quoted. It won't be checked.
     *
     * @param $quoted string The quoted string.
     *
     * @return string The unquoted string.
     */
    private static function unescapeQuotedHeaderContent(string $quoted): string
    {
        assert("\"" === $quoted[0] && "\"" === $quoted[-1], new LogicException("This method must only be called with quoted content."));

        $unquoted = substr($quoted, 1, -1);

        for ($idx = 0; $idx < strlen($unquoted) - 1; ++$idx) {
            if ("\\" === $unquoted[$idx]) {
                if ("\"" === $unquoted[$idx + 1]) {
                    // unescape an escaped "
                    $unquoted = substr($unquoted, 0, $idx) . substr($unquoted, $idx + 1);
                } else {
                    // otherwise its escaping something else or is a literal \
                    ++$idx;
                }
            }
        }

        return $unquoted;
    }

    /**
     * Check whether a string is a valid MIME version supported by the builder.
     *
     * Currently the only valid MIME version in existence is 1.0.
     *
     * @param string $mimeVersion The version to check.
     *
     * @return bool `true` if the MIME version is valid, `false` otherwise.
     */
    public static function isSupportedMimeVersion(string $mimeVersion): bool
    {
        return self::MimeVersion10 === $mimeVersion;
    }

    /**
     * Fetch the MIME version that the builder will use when generating the header block for a message.
     *
     * @return string The MIME version.
     */
    public function mimeVersion(): string
    {
        return $this->mimeVersion;
    }

    /**
     * Clone and set the MIME version that will be used by the builder.
     *
     * @api
     * @param string $version
     *
     * @return $this The clone with the MIME version set.
     * @throws InvalidArgumentException if the MIME version is not supported.
     */
    public function withMimeVersion(string $version): self
    {
        if (!self::isSupportedMimeVersion($version)) {
            throw new InvalidArgumentException("Expected supported MIME version, found \"{$version}\"");
        }

        $clone = clone $this;
        $clone->mimeVersion = $version;
        return $clone;
    }

    /**
     * Fetch the line end that the builder will use.
     *
     * By default this is the RFC822-compliant CRLF sequence, but the builder can be set to use just LF if compatibility
     * is required for some buggy MTUs.
     *
     * @return string
     */
    public function lineEnd(): string
    {
        return $this->lineEnd;
    }

    /**
     * Fetch a builder set to use the non-standards-compliant LF line ending.
     *
     * Just in case there are still some buggy MTAs out there.
     *
     * @return $this
     */
    public function withLfLineEnd(): self
    {
        $clone = clone $this;
        $clone->lineEnd = "\n";
        return $clone;
    }

    /**
     * Fetch a builder set to use the RFC822-compliant CRLF line ending.
     *
     * @return $this
     */
    public function withRfc822LineEnd(): self
    {
        $clone = clone $this;
        $clone->lineEnd = Mime::Rfc822LineEnd;
        return $clone;
    }

    /**
     * Ensure a message or part has the required headers.
     *
     * @throws MimeException if:
     * - the message or part is missing content-type and/or content-transfer-encoding headers; or
     * - the message or part is multipart but does not have a multipart content type; or
     * - the message or part is multipart but does not have a part boundary; or
     * - the message has no recipients (only messages with throw for this reason, parts won't)
     */
    public function checkHeaders(MessageContract|PartContract $source): void
    {
        $contentType = null;
        $contentTransferEncoding = null;

        foreach ($source->headers() as $header) {
            if ("content-type" === strtolower($header->name())) {
                $contentType = $header;
            } elseif ("content-transfer-encoding" === strtolower($header->name())) {
                $contentTransferEncoding = strtolower(trim($header->value()));
            }

            if (!is_null($contentType) && !is_null($contentTransferEncoding)) {
                break;
            }
        }

        if (is_null($contentType)) {
            throw new MimeException("The message or part has no content-type header.");
        }

        if (is_null($contentTransferEncoding)) {
            throw new MimeException("The message or part has no content-transfer-encoding header.");
        }

        // multipart messages have appropriate content type
        if ($source instanceof MultipartContract && 0 < count($source->parts())) {
            if (!str_starts_with(strtolower(trim($contentType->value())), "multipart/")) {
                throw new MimeException("The message or part has multiple parts but does not have a \"multipart/\" content type.");
            }

            $haveBoundary = false;

            foreach ($contentType->parameters() as $name => $value) {
                if ("boundary" === strtolower($name)) {
                    if ("" !== trim($value)) {
                        $haveBoundary = true;
                    }

                    break;
                }
            }

            if (!$haveBoundary) {
                throw new MimeException("The message or part has multiple parts but no boundary defined in the content-type header.");
            }
        }

        if ($source instanceof MessageContract && empty($source->to()) && empty($source->cc()) && empty($source->bcc())) {
            throw new MimeException("The message has no recipients.");
        }
    }

    /**
     * Ensure a message or part has parts or a body.
     *
     * @throws MimeException if the message or part is multipart and has no parts, or is not multiplart and has no body.
     */
    public function checkBody(MessageContract|PartContract $source): void
    {
        if ($source instanceof MultipartContract && 0 < count($source->parts())) {
            return;
        }

        if (null === $source->body()) {
            throw new MimeException("Message or part has no parts or body.");
        }
    }

    /**
     * Build a full MIME message for a Message instance.
     *
     * @param MessageContract $message The message to turn into MIME.
     *
     * @return string The MIME for the message.
     * @throws MimeException if the message is multipart and has no parts, or is not multipart and has no body.
     */
    public function mime(MessageContract $message): string
    {
        return $this->headers($message) . $this->lineEnd() . $this->body($message);
    }

    /**
     * Fetch the MIME header block for a message or part.
     *
     * @api
     * @param MessageContract|PartContract $source The message or part whose headers are required.
     *
     * @return string The MIME header block.
     */
    public function headers(MessageContract|PartContract $source): string
    {
        $headers = $source->headers();

        if ($source instanceof MessageContract) {
            $haveMimeVersion = false;

            for ($idx = 0; $idx < count($headers); ++$idx) {
                if ("mime-version" === strtolower($headers[$idx]->name())) {
                    $haveMimeVersion = true;
                    /** @psalm-suppress MissingThrowsDocblock These args are guaranteed to be valid. */
                    $headers[$idx] = new Header("mime-version", $this->mimeVersion());
                }
            }

            if (!$haveMimeVersion) {
                /** @psalm-suppress MissingThrowsDocblock These args are guaranteed to be valid. */
                $headers[] = new Header("mime-version", $this->mimeVersion());
            }
        }

        $lineEnd = $this->lineEnd();

        return array_reduce(
            $headers,
            fn (string $carry, HeaderContract $header): string => "{$carry}{$header->line()}{$lineEnd}",
            ""
        );
    }

    /**
     * Fetch the MIME body for a message or part.
     *
     * If the message or part has multiple parts, the full body containing all the parts, including the constituent
     * part headers, is returned. (Note the headers of the `$source` message or part are NOT included as these are not
     * part of its MIME body.
     *
     * @api
     * @param MessageContract|PartContract $source
     *
     * @return string The MIME body.
     * @throws MimeException if the message or part contains parts that are multipart and two or more of them are using
     * the same boundary.
     */
    public function body(MessageContract|PartContract $source): string
    {
        static $recursionLevel = 0;
        static $boundaries = [];

        // TODO should we check headers here or in headers()?
        $this->checkHeaders($source);
        $this->checkBody($source);
        ++$recursionLevel;
        $body = "";
        $lineEnd = $this->lineEnd();

        if ($source instanceof MultipartContract && 0 < count($source->parts())) {
            $boundary = self::multipartBoundary($source);

            if (in_array($boundary, $boundaries)) {
                $boundaries = [];
                $recursionLevel = 0;
                throw new MimeException("Message contains duplicate part boundary \"{$boundary}\"");
            }

            $boundaries[] = $boundary;

            foreach ($source->parts() as $part) {
                $body .= "{$lineEnd}--{$boundary}"
                    . "{$lineEnd}{$this->headers($part)}"
                    . "{$lineEnd}{$this->body($part)}{$lineEnd}";
            }

            $body .= "--{$boundary}--";
        } else {
            $body = $source->body();
        }

        --$recursionLevel;

        if (0 === $recursionLevel) {
            // we're done, so we clear the boundaries we've seen so that they don't influence subsequent calls
            $boundaries = [];
        }

        return $body;
    }
}
