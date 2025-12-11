<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

use Stringable;

interface UriAuthority extends Stringable
{
    /** Fetch the userinfo part of the authority, if it has one. */
    public function userInfo(): ?UriUserInfo;

    /** Fetch the host. */
    public function host(): string;

    /** Fetch the port, if the authority has one. */
    public function port(): ?int;
}
