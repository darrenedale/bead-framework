<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

interface UriUserInfo
{
    /** Fetch the username part of the user info. */
    public function username(): string;

    /** Fetch the password part of the user info. */
    public function password(): ?string;
}
