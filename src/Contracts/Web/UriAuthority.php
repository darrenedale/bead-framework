<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

use Stringable;

interface UriAuthority extends Stringable
{

    public function userInfo(): ?UriUserInfo;

    public function host(): string;

    public function port(): ?int;
}
