<?php

namespace Bead\Queues\Azure\RestCommands;

use Bead\Contracts\Azure\ResponseInterface;
use Bead\Contracts\Azure\RestCommand;
use Bead\Exceptions\QueueException;
use Psr\Http\Message\StreamInterface;

class Put extends AbstractQueueCommand
{
    use DoesntHaveHeaders;

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

    public function parseResponse(ResponseInterface $response): mixed
    {
        if (200 <= $response->getStatusCode() && 300 > $response->getStatusCode()) {
            return null;
        }

        throw new QueueException("Unable to publish message to queue {$this->queue()} in namespace {$this->namespace()}: {$response->getReasonPhrase()}");
    }
}