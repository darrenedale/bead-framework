<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

use Stringable;

/** Abstract representation of the authority part of a URI. */
interface UriAuthority extends Stringable
{
    /** Fetch the userinfo part of the authority, if it has one. */
    public function userInfo(): ?UriUserInfo;

    /** Fetch the username part of the authority, if it has one. */
    public function username(): ?string;

    /** Fetch the password part of the authority, if it has one. */
    public function password(): ?string;

    /** Fetch the host. */
    public function host(): string;

    /** Fetch the port, if the authority has one. */
    public function port(): ?int;
}
