<?php

declare(strict_types=1);

namespace Bead\Contracts\Azure;

interface RestClient
{
    public function send(RestCommand $command, ?Authorisation $authorisation = null): mixed;
}
