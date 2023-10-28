<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

interface Transport
{
    /**
     * Attempt to deliver a message.
     */
    public function send(Message $message): void;
}
