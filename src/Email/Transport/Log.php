<?php

declare(strict_types=1);

namespace Bead\Email\Transport;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Transport as TransportContract;
use Bead\Email\Mime;
use Bead\Email\MimeBuilder;
use Bead\Exceptions\Email\TransportException;
use Bead\Facades\Log as LogFacade;

/**
 * Mail transport that just logs the MIME content of the message.
 */
class Log implements TransportContract
{
    public function send(MessageContract $message): void
    {
        $builder = new MimeBuilder();

        LogFacade::info("--- BEGIN MIME Email message transport ---");
        LogFacade::info($builder->headers($message) . Mime::Rfc822LineEnd . $builder->body($message));
        LogFacade::info("---  END  MIME Email message transport ---");
    }
}
