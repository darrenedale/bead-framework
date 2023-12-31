<?php

declare(strict_types=1);

namespace Bead\Queues;

use Bead\Contracts\Queues\Message as MessageContract;
use LogicException;
use function Bead\Helpers\Iterable\all;

class Message implements MessageContract
{
    private string $payload;

    private array $properties;

    public function __construct(string $payload)
    {
        $this->payload = $payload;
    }

    public function payload(): string
    {
        return $this->payload;
    }

    public function withPayload(string $payload): self
    {
        $clone = clone $this;
        $clone->payload = $payload;
        return $clone;
    }

    public function property(string $key): mixed
    {
        return $this->properties[$key] ?? null;
    }

    public function withProperties(array $properties): self
    {
        assert (all(array_keys($properties), "is_string"), new LogicException("Expected array with string keys, found non-string key"));
        $clone = clone $this;
        $clone->properties = $properties;
        return $clone;
    }

    public function withProperty(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->properties[$key] = $value;
        return $clone;
    }
}
