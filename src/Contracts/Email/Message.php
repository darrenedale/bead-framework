<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

use Bead\Contracts\Email\Header as HeaderContract;

/**
 * Interface for email messages.
 */
interface Message
{
    /** @return HeaderContract[] */
    public function headers(): array;

    /** @return string The value of the "subject" header. */
    public function subject(): string;

    /** @return string[] The values of all the "to" headers. */
    public function to(): array;

    /** @return string[] The values of all the "cc" headers. */
    public function cc(): array;

    /** @return string[] The values of all the "bcc" headers. */
    public function bcc(): array;

    /** @return string The value of the "from" header. */
    public function from(): string;

    /**
     * If the message is not multipart, provides the message body (if set).
     *
     * If the message is multipart, this is null.
     *
     * @return string|null The message body.
     */
    public function body(): ?string;
}
