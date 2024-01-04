<?php

namespace Bead\Queues\Azure\RestCommands;

use Bead\Contracts\Azure\RestCommand;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

abstract class AbstractQueueCommand implements RestCommand
{
    private string $namespace;

    private string $queueName;

    public function __construct(string $namespace, string $queueName)
    {
        $this->namespace = $namespace;
        $this->queueName = $queueName;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function queue(): string
    {
        return $this->queueName;
    }

    protected function baseUri(): string
    {
        return "https://{$this->namespace()}.servicebus.windows.net/{$this->queue()}/messages";
    }

    abstract public function headers(): array;

    abstract public function uri(): string;

    abstract public function method(): string;

    abstract public function body(): string|StreamInterface;

    abstract public function parseResponse(ResponseInterface $response): mixed;
}