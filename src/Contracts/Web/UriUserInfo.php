<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

interface UriUserInfo
{
    public function username(): string;

    public function password(): ?string;
}
