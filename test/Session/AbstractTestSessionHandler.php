<?php

declare(strict_types=1);

namespace BeadTests\Session;

use Bead\Contracts\SessionHandler;

abstract class AbstractTestSessionHandler implements SessionHandler
{
    protected string $id = "";

    public function get(string $key): mixed
    {
        SessionTest::fail("get() should not be called on the test handler.");
    }

    public function set(string $key, $data): void
    {
        SessionTest::fail("set() should not be called on the test handler.");
    }

    public function all(): array
    {
        SessionTest::fail("all() should not be called on the test handler.");
    }

    public function id(): string
    {
        return $this->id;
    }

    public function clear(): void
    {
        SessionTest::fail("clear() should not be called on the test handler.");
    }

    public function remove(string $key): void
    {
        SessionTest::fail("remove() should not be called on the test handler.");
    }

    public function regenerateId(): string
    {
        SessionTest::fail("regenerateId() should not be called on the test handler.");
    }

    public function createdAt(): int
    {
        SessionTest::fail("createdAt() should not be called on the test handler.");
    }

    public function lastUsedAt(): int
    {
        SessionTest::fail("lastUsedAt() should not be called on the test handler.");
    }

    public function idGeneratedAt(): int
    {
        SessionTest::fail("idGeneratedAt() should not be called on the test handler.");
    }

    public function idExpiredAt(): ?int
    {
        SessionTest::fail("idExpiredAt() should not be called on the test handler.");
    }

    public function touch(?int $time = null): void
    {
        SessionTest::fail("touch() should not be called on the test handler.");
    }

    public function idHasExpired(): bool
    {
        SessionTest::fail("idHasExpired() should not be called on the test handler.");
    }

    public function replacementId(): ?string
    {
        SessionTest::fail("replacementId() should not be called on the test handler.");
    }

    public function commit(): void
    {
        SessionTest::fail("commit() should not be called on the test handler.");
    }

    public function reload(): void
    {
        SessionTest::fail("reload() should not be called on the test handler.");
    }

    public function destroy(): void
    {
        SessionTest::fail("destroy() should not be called on the test handler.");
    }

    public static function prune(): void
    {
        SessionTest::fail("prune() should not be called on the test handler.");
    }
}
