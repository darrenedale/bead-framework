<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

/**
 * Interface for email message headers.
 */
interface Header
{
    /**
     * The header's name.
     *
     * The name never has leading or trailing whitespace.
     */
    public function name(): string;

    /** The header's value. */
    public function value(): string;

    /**
     * The header's parameters.
     *
     * For example, a `content-disposition` header with a value of `attachment` may have a `filename` parameter
     * indicating the filename of the attachment.
     *
     * @return array<string,string>
     */
    public function parameters(): array;

    /** Retrieve the full header line, without the trailing CRLF delimiter. */
    public function line(): string;
}
