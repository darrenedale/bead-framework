<?php

declare(strict_types=1);

namespace Bead\Email;

use Bead\Contracts\Email\Part as PartContract;

use function Bead\Helpers\Iterable\fill;
use function Bead\Helpers\Iterable\toArray;
use function implode;
use function is_string;
use function rand;

trait HasParts
{
    /** @var string The boundary to use between multiple parts, if it's a multipart message. */
    protected string $multipartBoundary = "";

    /** @var PartContract[] The parts of the email body. */
    protected array $parts = [];

    public function multipartBoundary(): string
    {
        if ("" === $this->multipartBoundary) {
            $this->multipartBoundary = Mime::generateMultipartBoundary();
        }

        return $this->multipartBoundary;
    }

    public function parts(): array
    {
        return $this->parts;
    }

    public function partCount(): int
    {
        return count($this->parts);
    }

    public function withPart(PartContract|string $part, ?string $contentType = null, ?string $contentEncoding = null): self
    {
        if (is_string($part)) {
            $args = [$part];

            if (is_string($contentEncoding)) {
                $args[] = ($contentType ?? Part::DefaultContentType);
            } elseif (is_string($contentType)) {
                $args[] = $contentType;
            }

            $part = new Part(...$args);
        }

        $clone = clone $this;
        $clone->parts[] = $part;
        return $clone;
    }
}
