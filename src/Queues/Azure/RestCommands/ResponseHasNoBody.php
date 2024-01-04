<?php

declare(strict_types=1);

namespace Bead\Queues\Azure\RestCommands;

use Bead\Exceptions\QueueException;
use Psr\Http\Message\ResponseInterface;

trait ResponseHasNoBody
{
    abstract public function uri(): string;

    private function errorMessage(): string
    {
        return "Error received from REST API for request {$this->uri()}";
    }

    public function parseResponse(ResponseInterface $response): mixed
    {
        if (200 <= $response->getStatusCode() && 300 > $response->getStatusCode()) {
            return null;
        }

        throw new QueueException("{$this->errorMessage()}: {$response->getReasonPhrase()}");
    }
}
