<?php

declare(strict_types=1);

namespace Bead\Email\Transport;

use Bead\Contracts\Email\Message as MessageContract;
use Bead\Contracts\Email\Transport as TransportContract;
use Bead\Email\MimeBuilder;
use Bead\Exceptions\Email\TransportException;

class Php implements TransportContract
{
    public function send(MessageContract $message): void
    {
        $builder = new MimeBuilder();

        if (!mail(
            implode(",", array_unique([...$message->to(), ...$message->cc(), ...$message->bcc(),])),
            $message->subject(),
            $builder->body($message),
            $builder->headers($message)
        )) {
            throw new TransportException("Failed to transport message with subject \"{$message->subject()}\"");
        }
    }
}
