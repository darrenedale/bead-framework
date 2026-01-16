<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

use Stringable;

/** Abstract representation of the user info section of a URI. */
interface UriUserInfo extends Stringable
{
    /** Fetch the username part of the user info. */
    public function username(): string;

    /** Fetch the password part of the user info, if it has one. */
    public function password(): ?string;
}
