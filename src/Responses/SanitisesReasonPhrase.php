<?php

declare(strict_types=1);

namespace Bead\Responses;

trait SanitisesReasonPhrase
{
    abstract public function reasonPhrase(): string;

    /** Sanitise the reason phrase for use in the HTTP status header. */
    public function sanitisedReasonPhrase(): string
    {
        return str_replace(["\r", "\n",], " ", $this->reasonPhrase());
    }
}
