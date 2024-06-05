<?php

declare(strict_types=1);

namespace Bead\Email\Transport;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Transport as TransportContract;
use Bead\Email\Mime;
use Bead\Email\MimeBuilder;
use Bead\Exceptions\Email\MimeException;
use Bead\Exceptions\Email\TransportException;
use Bead\Facades\Log as LogFacade;

/**
 * Mail transport that just logs the MIME content of the message.
 */
class Log implements TransportContract
{
    /**
     * Log a message
     *
     * An information level message will be output to the log, containing the MIME of the provided message.
     *
     * @param MessageContract $message The message to log.
     *
     * @throws TransportException if the message cannot be logged
     */
    public function send(MessageContract $message): void
    {
        /** @psalm-suppress MissingThrowsDocblock default-construction never throws */
        $builder = new MimeBuilder();

        try {
            $mime = $builder->headers($message) . Mime::Rfc822LineEnd . $builder->body($message);
        } catch (MimeException $err) {
            throw new TransportException("Unable to generate MIME message: {$err->getMessage()}", previous: $err);
        }

        LogFacade::info("--- BEGIN MIME Email message transport ---");
        LogFacade::info($mime);
        LogFacade::info("---  END  MIME Email message transport ---");
    }
}
