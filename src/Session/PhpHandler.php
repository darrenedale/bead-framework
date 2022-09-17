<?php

namespace Equit\Session;

/**
 * Session handler that uses PHP's built-in sessions.
 *
 * This is a thin wrapper around PHP's built-in sessions, implementing the SessionHandler interface. If you use this
 * session storage backend you are advised not to access `$_SESSION` directly. Only one session using this handler can
 * be in use at any given time.
 */
class PhpHandler implements Handler
{
    /** @inheritDoc */
    public function __construct(?string $id = null)
    {
        if (isset($id)) {
            session_id($id);
            $now = time();
            $_SESSION["__created_at"] = $now;
            $_SESSION["__last_used_at"] = $now;
            $_SESSION["__id_created_at"] = $now;
        }

        if (!isset($_SESSION["__data"]) || !is_array($_SESSION["__data"])) {
            $_SESSION["__data"] = [];
        }
    }

    /** @inheritDoc */
    public function id(): string
    {
        return session_id();
    }

    /** @inheritDoc */
    public function createdAt(): int
    {
        return $_SESSION["__created_at"];
    }

    /** @inheritDoc */
    public function lastUsedAt(): int
    {
        return $_SESSION["__last_used_at"];
    }

    /** @inheritDoc */
    public function idGeneratedAt(): int
    {
        return $_SESSION["__id_created_at"];
    }

    /** @inheritDoc */
    public function touch(?int $time = null): void
    {
        $_SESSION["__last_used_at"] = $time ?? time();
    }
    
    /**
     * The timestamp when the session was expired.
     * @return int|null
     */
    public function idExpiredAt(): ?int
    {
        return $_SESSION["__id_expired_at"];
    }

    /** @inheritDoc */
    public function get(string $key)
    {
        return $_SESSION["__data"][$key] ?? null;
    }

    /** @inheritDoc */
    public function all(): array
    {
        return $_SESSION;
    }

    /** @inheritDoc */
    public function set(string $key, $data)
    {
        $_SESSION["__data"][$key] = $data;
    }

    /**
     * @inheritDoc
     */
    public function remove(string $key): void
    {
        unset($_SESSION["__data"][$key]);
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $_SESSION["__data"] = [];
    }

    /**
     * @inheritDoc
     *
     * Regenerating the ID with this session handler commits the session.
     */
    public function regenerateId(): string
    {
        $newId = session_create_id();
        $_SESSION["__id_expired_at"] = time();
        $_SESSION["__replacement_id"] = $newId;
        $this->commit();
        session_id($newId);
        unset($_SESSION["__id_expired_at"]);
        $_SESSION["__id_created_at"] = time();
        $this->commit();
        return $newId;
    }

    /**
     * @inheritDoc
     */
    public function idHasExpired(): bool
    {
        return isset($_SESSION["__id_expired_at"]);
    }

    /**
     * @inheritDoc
     */
    public function replacementId(): ?string
    {
        return ($this->idHasExpired() ? $_SESSION["__replacement_id"] : null);
    }

    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        $_SESSION["__last_used_at"] = time();
        session_commit();
    }

    /**
     * @inheritDoc
     */
    public function reload(): void
    {
        session_reset();
    }

    /**
     * @inheritDoc
     */
    public function destroy(): void
    {
        session_destroy();
    }

    /**
     * @inheritDoc
     */
    public static function prune(): void
    public static function purge(): void
    {
        session_gc();
    }
}
