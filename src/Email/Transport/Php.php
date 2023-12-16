<?php

declare(strict_types=1);

namespace Bead\Email\Transport;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Transport as TransportContract;
use Bead\Email\MimeBuilder;
use Bead\Exceptions\Email\MimeException;
use Bead\Exceptions\Email\TransportException;

class Php implements TransportContract
{
    /**
     * @param MessageContract $message
     * @throws TransportException if the message can't be encoded as a MIME message or can't be sent.
     */
    public function send(MessageContract $message): void
    {
        /** @psalm-suppress MissingThrowsDocblock Default construction never throws. */
        $builder = new MimeBuilder();

        try {
            $headers = $builder->headers($message);
            $body = $builder->body($message);
        } catch (MimeException $err) {
            throw new TransportException("Unable to generate MIME for message with subject \"{$message->subject()}\": {$err->getMessage()}", previous: $err);
        }

        if (
            !mail(
                implode(",", array_unique([...$message->to(), ...$message->cc(), ...$message->bcc(),])),
                $message->subject(),
                $body,
                $headers
            )
        ) {
            throw new TransportException("Failed to transport message with subject \"{$message->subject()}\"");
        }
    }
}
