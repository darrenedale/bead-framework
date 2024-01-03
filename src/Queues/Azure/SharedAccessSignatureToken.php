<?php

declare(strict_types=1);

namespace Bead\Queues\Azure;

use Bead\Contracts\Azure\SharedAccessSignatureToken as AzureSharedAccessSignatureToken;
use Bead\Encryption\ScrubsStrings;

class SharedAccessSignatureToken extends AzureSharedAccessSignatureToken
{
    use ScrubsStrings;

    private string $service;

    private string $keyName;

    private string $key;

    private int $expiry;

    public function __construct(string $service, string $keyName, string $key, int $expiry)
    {
        $this->service = $service;
        $this->keyName = $keyName;
        $this->key = $key;
        $this->expiry = $expiry;
    }

    public function __destruct()
    {
        self::scrubString($this->key);
    }

    public function service(): string
    {
        return $this->service;
    }

    public function withService(string $service): self
    {
        $clone = clone $this;
        $clone->service = $service;
        return $clone;
    }

    public function keyName(): string
    {
        return $this->keyName;
    }

    public function withKeyName(string $keyName): self
    {
        $clone = clone $this;
        $clone->keyName = $keyName;
        return $clone;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function withKey(string $key): self
    {
        $clone = clone $this;
        $clone->key = $key;
        return $clone;
    }

    public function expiry(): int
    {
        return $this->expiry;
    }

    public function withExpiry(int $expiry): self
    {
        $clone = clone $this;
        $clone->expiry = $expiry;
        return $clone;
    }

    public function headers(): array
    {
        $signature = http_build_query([
            "sr" => $this->service(),
            "skn" => $this->keyName(),
            "sig" => $this->key(),
            "se" => (string) $this->expiry(),
        ]);

        return ["Authorization" => "SharedAccessSignature {$signature}",];
    }
}