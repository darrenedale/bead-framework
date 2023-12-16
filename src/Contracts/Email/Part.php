<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

use Bead\Contracts\Email\Header as HeaderContract;

/**
 * Interface for a part in a multipart message/part.
 */
interface Part
{
    /** @return HeaderContract[] The message part's headers. */
    public function headers(): array;

    /**
     * If the part is not multipart, provides the body (if set).
     *
     * If the part is multipart, this is null.
     *
     * @return string|null The part body.
     */
    public function body(): ?string;
}
