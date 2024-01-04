<?php

declare(strict_types=1);

namespace Bead\Queues\Azure\RestCommands;

use Bead\Exceptions\QueueException;
use Bead\Queues\AzureServiceBusMessage;
use JsonException;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractFetch extends AbstractQueueCommand
{
    use HasNoHeaders;
    use HasNoBody;

    public function headers(): array
    {
        return [];
    }

    public function uri(): string
    {
        return "{$this->baseUri()}/head";
    }

    abstract public function method(): string;

    public function parseResponse(ResponseInterface $response): ?AzureServiceBusMessage
    {
        if (200 === $response->getStatusCode() || 201 === $response->getStatusCode()) {
            if ($response->hasHeader("BrokerProperties")) {
                $header = $response->getHeader("BrokerProperties")[0];

                try {
                    $properties = json_decode($header, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $err) {
                    if (50 < strlen($header)) {
                        $header = substr($header, 0, 47) . "...";
                    }

                    throw new QueueException("Expected valid JSON BrokerProperties header, found {$header}: {$err->getMessage()}", previous: $err);
                }

                $id = $properties["MessageId"] ?? null;

                if (null === $id) {
                    throw new QueueException("Expected MessageId in BrokerProperties header, none found");
                }

                $lockToken = $properties["LockToken"] ?? "";
            } else {
                $id = "";
                $lockToken = "";
            }

            return new AzureServiceBusMessage($id, $lockToken, (string)$response->getBody());
        }

        if (204 === $response->getStatusCode()) {
            return null;
        }

        throw new QueueException("Failed fetching messages from service bus queue {$this->queue()} in namespace {$this->namespace()}: {$response->getReasonPhrase()}");
    }
}