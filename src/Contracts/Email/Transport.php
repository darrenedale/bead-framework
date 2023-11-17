<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

/**
 * Interface for classes that can transport email messages.
 */
interface Transport
{
    /** Attempt to deliver a message. */
    public function send(Message $message): void;
}
