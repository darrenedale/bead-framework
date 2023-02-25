<?php

namespace Bead\Contracts\Session;

/**
 * Interface for session storage backends.
 */
interface Handler
{
    /**
     * Initialise a new handler.
     *
     * If the ID is given the session with that ID should be loaded; if no ID is given, a new session is started with a
     * new ID.
     *
     * @param string|null $id
     */
    public function __construct(?string $id = null);

    /**
     * Fetch the unique identifier for the session.
     *
     * @return string The ID.
     */
    public function id(): string;

    /**
     * Get the value stored with a key.
     *
     * @param string $key The key of the value sought.
     *
     * @return mixed The value stored with the key, or `null` if the key is not set.
     */
    public function get(string $key);

    /**
     * Fetch all the data in the session.
     *
     * @return array<string, mixed> All the data.
     */
    public function all(): array;

    /**
     * Set the value stored with a key.
     *
     * @param string $key The key of the value to set.
     * @param mixed $data The value to store.
     */
    public function set(string $key, $data);

    /**
     * Remove a value from the session.
     *
     * @param string $key The key of the value to remove.
     */
    public function remove(string $key): void;

    /**
     * Empty all the data from the session.
     */
    public function clear(): void;

    /**
     * Regenerate the session ID, preserving the session data.
     *
     * This is likely to commit the changed data, but is not required to do so.
     *
     * @return string The new ID.
     */
    public function regenerateId(): string;

    /**
     * Fetch the time at which the session was first created.
     *
     * @return int The timestamp.
     */
    public function createdAt(): int;

    /**
     * Fetch the time at which the session was last used.
     *
     * @return int The timestamp.
     */
    public function lastUsedAt(): int;

    /**
     * Fetch the time at which the session's current ID was generated.
     *
     * @return int The timestamp.
     */
    public function idGeneratedAt(): int;

    /**
     * The time at which the ID expired.
     *
     * The ID expiring doesn't necessarily mean the session has expired, just that the ID has been regenerated.
     *
     * @return int|null The timestamp, or `null` if the ID has not been regenerated.
     */
    public function idExpiredAt(): ?int;

    /**
     * Mark the session as last used at a given time or the current time if not specified.
     *
     * @param int|null $time The time at which to mark the session as last used.
     */
    public function touch(?int $time = null): void;

    /**
     * Check whether the session has expired.
     *
     * Sessions should only survive for a limited time from their inception before their ID is regenerated. Once
     * regenerated, the session with the old ID survives for a short period (usually a minute or so) of time to
     * accommodate poor connectivity and other conditions that may prevent legitimate clients from receiving the updated
     * session ID immediately. During this short period, the session is expired but hasn't been destroyed.
     *
     * @return bool `true` if the session has expired, `false` if it's still active or has been destroyed.
     */
    public function idHasExpired(): bool;

    /**
     * Fetch the ID of the session that has replaced this one if it's expired.
     *
     * When a session ID is regenerated, the replacement ID can be discovered using this method, for a short grace
     * period while the old session is expired but not destroyed.
     *
     * @return string|null The ID that was re-generated for the session, or `null` if the session hasn't expired or has
     * been destroyed.
     */
    public function replacementId(): ?string;

    /**
     * Write the session data to permanent storage.
     */
    public function commit(): void;

    /**
     * Load a session from permanent storage, discarding any updated data.
     */
    public function load(string $id): void;

    /**
     * Reload the session from permanent storage, discarding any updated data.
     */
    public function reload(): void;

    /**
     * Destroy the session.
     */
    public function destroy(): void;

    /**
     * Purge destroyed sessions, recovering any space they occupy in permanent storage.
     */
    public static function prune(): void;
}
