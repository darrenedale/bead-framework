<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

use Bead\Exceptions\Email\TransportException;

/**
 * Interface for classes that can transport email messages.
 */
interface Transport
{
    /**
     * Attempt to deliver a message.
     *
     * @throws TransportException
     */
    public function send(Message $message): void;
}
