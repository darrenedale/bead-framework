<?php

namespace Bead\Queues\Azure\RestCommands;

use Bead\Exceptions\QueueException;
use Psr\Http\Message\StreamInterface;
use function Bead\Helpers\Iterable\map;
use function Bead\Helpers\Iterable\toArray;

class BatchSend extends AbstractQueueCommand
{
    use ResponseHasNoBody;

    /** @var iterable<string|StreamInterface>  */
    private iterable $bodies;

    /**
     * @param string $namespace
     * @param string $queueName
     * @param iterable<string|StreamInterface> $bodies
     */
    public function __construct(string $namespace, string $queueName, iterable $bodies)
    {
        parent::__construct($namespace, $queueName);
        $this->bodies = $bodies;
    }

    /** @return iterable<string|StreamInterface> */
    public function bodies(): iterable
    {
        return $this->bodies;
    }

    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->bodies[] = $body;
        return $clone;
    }

    public function withBodies(iterable $bodies): self
    {
        $clone = clone $this;
        $clone->bodies = $bodies;
        return $clone;
    }

    public function uri(): string
    {
        return $this->baseUri();
    }

    public function headers(): array
    {
        return ["content-type" => "application/vnd.microsoft.servicebus.json",];
    }

    public function method(): string
    {
        return "POST";
    }

    public function body(): string|StreamInterface
    {
        return json_encode(
            toArray(
                map(
                    $this->bodies(),
                    fn (string|StreamInterface $body): array => ["Body" => (string) $body,]
                )
            )
        );
    }

    private function errorMessage(): string
    {
        return "Error sending message batch to queue \"{$this->queue()}\" in namespace {$this->namespace()}";
    }
}
