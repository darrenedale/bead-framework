<?php

declare(strict_types=1);

namespace Bead\Responses;

trait SanitisesReasonPhrase
{
    public abstract function reasonPhrase(): string;

    public function sanitisedReasonPhrase(): string
    {
        return str_replace(["\r", "\n",], " ", $this->reasonPhrase());
    }
}
