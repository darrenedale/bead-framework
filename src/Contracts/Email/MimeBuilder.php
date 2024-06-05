<?php

declare(strict_types=1);

namespace Bead\Contracts\Email;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Part as PartContract;

/**
 * Interface for classes that build MIME representations of Messages and their Parts.
 */
interface MimeBuilder
{
    /**
     * Build a full MIME message.
     *
     * @param MessageContract $message The message to turn into MIME.
     *
     * @return string The MIME for the message.
     */
    public function mime(MessageContract $message): string;

    /**
     * Fetch the MIME header block for a message or part.
     *
     * @api
     * @param MessageContract|PartContract $source The message or part whose headers are required.
     *
     * @return string The MIME header block.
     */
    public function headers(MessageContract|PartContract $source): string;

    /**
     * Fetch the MIME body for a message or part.
     *
     * If the message or part has multiple parts, the full body containing all the parts, including the constituent
     * part headers, is returned. (Note the headers of the `$source` message or part are NOT included as these are not
     * part of its MIME body.)
     *
     * @param MessageContract|PartContract $source
     *
     * @return string The MIME body.
     */
    public function body(MessageContract|PartContract $source): string;
}
