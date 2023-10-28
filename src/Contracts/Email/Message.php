<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

interface Message
{
    /** @return Header[] */
    public function headers(): array;

    /** @return string The value of the subject header. */
    public function subject(): string;

    /** @return string[] The values of all the to headers. */
    public function to(): array;

    /** @return string[] The values of all the cc headers. */
    public function cc(): array;

    /** @return string[] The values of all the bcc headers. */
    public function bcc(): array;

    /** @return string The value of the from header. */
    public function from(): string;

    /**
     * The full body of the email.
     *
     * If the email is multipart, this is the body content that will be sent to the MTA, part headers, delimiters
     * and all.
     *
     * @return string The message body.
     */
    public function body(): string;
}
