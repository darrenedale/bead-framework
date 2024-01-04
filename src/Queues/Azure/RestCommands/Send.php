<?php

namespace Bead\Queues\Azure\RestCommands;

use Psr\Http\Message\StreamInterface;

class Send extends AbstractQueueCommand
{
    use HasNoHeaders;
    use ResponseHasNoBody;

    private string|StreamInterface $body;

    public function __construct(string $namespace, string $queueName, string|StreamInterface $body)
    {
        parent::__construct($namespace, $queueName);
        $this->body = $body;
    }

    public function uri(): string
    {
        return $this->baseUri();
    }

    public function method(): string
    {
        return "POST";
    }

    public function body(): string|StreamInterface
    {
        return $this->body;
    }
}