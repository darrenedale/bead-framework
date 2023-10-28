<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

interface Part
{
    /** @return Header[] The message part's headers. */
    public function headers(): array;

    /** @return string The message part's body. */
    public function body(): string;
}
