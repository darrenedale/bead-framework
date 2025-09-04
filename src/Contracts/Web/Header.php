<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

/** Interface for HTTP message headers. */
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

    /** Retrieve the full header line, without the trailing CRLF delimiter. */
    public function line(): string;
}
