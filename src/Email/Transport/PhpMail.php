<?php

declare(strict_types=1);

namespace Bead\Email\Transport;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Transport as TransportContract;
use Bead\Exceptions\Email\TransportException;

class PhpMail implements TransportContract
{
    /** @var string The line ending to use in the message body during transmission. */
    private const LineEnd = "\r\n";

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
            throw new TransportException("Failed to transport message with subject \"{$message->subject()}\"");
        }
    }
}
