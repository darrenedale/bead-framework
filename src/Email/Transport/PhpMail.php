<?php

declare(strict_types=1);

namespace Bead\Email\Transport;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Transport as TransportContract;
use Bead\Email\Mime;
use Bead\Exceptions\Email\TransportException;

class PhpMail implements TransportContract
{
    public function send(MessageContract $message): void
    {
        $headers = [];

        foreach ($message->headers() as $header) {
            if ("subject" === strtolower($header->name())) {
                continue;
            }

            $headers[] = $header->line();
        }

        if (!mail(
            implode(",", array_unique([...$message->to(), ...$message->cc(), ...$message->bcc(),])),
            $message->subject(),
            $message->body(),
            implode( Mime::Rfc822LineEnd, $headers)
        )) {
            throw new TransportException("Failed to transport message with subject \"{$message->subject()}\"");
        }
    }
}
