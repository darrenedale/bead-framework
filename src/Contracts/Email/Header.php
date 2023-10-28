<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

interface Header
{
    public function name(): string;

    public function value(): string;

    /** @return array<string,string> */
    public function parameters(): array;

    /** Retrieve the full header line, without the trailing line delimiter. */
    public function line(): string;
}
