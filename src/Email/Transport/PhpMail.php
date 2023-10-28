<?php

declare(strict_types=1);

namespace Bead\Email\Transport;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Transport as TransportContract;

class PhpMail implements TransportContract
{
    /**
     * some old (< 2.9 AFAIK) versions of postfix need the line end to be this on *nix
     *
     * @var string The line ending to use in the message body during transmission.
     */
    private const LineEnd = "\n";

    public function send(MessageContract $message): void
    {
        $headers = "";

        foreach ($message->headers() as $header) {
            if (0 === strcasecmp("subject", $header->name())) {
                continue;
            }

            $headers .= $header->line() . self::LineEnd;
        }

        if (!mail(
            implode(",", array_unique([...$message->to(), ...$message->cc(), ...$message->bcc(),])),
            $message->subject(),
            $message->body(),
            $headers
        )) {
            // TODO MailTransportException
            throw new \RuntimeException("Failed to transport message");
        }
    }
}
