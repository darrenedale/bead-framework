<?php

namespace Bead\Queues\Azure\RestCommands;

use Bead\Contracts\Azure\ResponseInterface;
use Bead\Exceptions\QueueException;
use Bead\Queues\AzureServiceBusMessage;
use Psr\Http\Message\StreamInterface;

class Release extends AbstractQueueCommand
{
    use DoesntHaveHeaders;
    use DoesntHaveBody;

    private AzureServiceBusMessage $message;

    public function __construct(string $namespace, string $queueName, AzureServiceBusMessage $message)
    {
        parent::__construct($namespace, $queueName);
        $this->message = $message;
    }

    public function message(): AzureServiceBusMessage
    {
        return $this->message;
    }

    public function uri(): string
    {
        return "{$this->baseUri()}/{$this->message()->id()}/{$this->message()->lockToken()}";
    }

    public function method(): string
    {
        return "PUT";
    }

    public function parseResponse(ResponseInterface $response): mixed
    {
        if (200 <= $response->getStatusCode() && 300 > $response->getStatusCode()) {
            return null;
        }

        throw new QueueException("Unable to release message {$this->message()->id()} on queue {$this->queue()} in namespace {$this->namespace()}: {$response->getReasonPhrase()}");
    }
}