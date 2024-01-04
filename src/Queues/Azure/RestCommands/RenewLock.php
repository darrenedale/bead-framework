<?php

declare(strict_types=1);

namespace Bead\Queues\Azure\RestCommands;

use Bead\Queues\AzureServiceBusMessage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class RenewLock extends AbstractQueueCommand
{
    use HasNoHeaders;
    use HasNoBody;
    use ResponseHasNoBody;

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
        return "POST";
    }
}