<?php

namespace Bead\Session;

/**
 * Shared implementation of ArrayAccess interface for session DataAccessors
 */
trait ImplementsArrayAccess
{
    /**
     * Constrain trait users to provide a has() method.
     */
    abstract public function has(string $key): bool;

    /**
     * Constrain trait users to provide a get() method.
     */
    abstract public function get(string $key): mixed;

    /**
     * Constrain trait users to provide a set() method.
     */
    abstract public function set(string|array $keyOrData, mixed $data = null): void;

    /**
     * Constrain trait users to provide a remove() method.
     */
    abstract public function remove(string|array $keys): void;

    /**
     * Check whether an offset exists.
     *
     * This is part of the ArrayAccess interface.
     *
     * @param mixed $offset The offset to check.
     *
     * @return bool `true` if the session key exists, `false` otherwise.
     */
    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && $this->has($offset);
    }

    /**
     * Fetch the value for an offset.
     *
     * This is part of the ArrayAccess interface.
     *
     * @param mixed $offset The offset to retrieve.
     *
     * @return mixed The value at the offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return (is_string($offset) ? $this->get($offset) : null);
    }

    /**
     * Set the value for an offset.
     *
     * This is part of the ArrayAccess interface.
     *
     * @param mixed $offset The offset to set.
     * @param mixed $value The value to set.
     */
    public function offsetSet(mixed $offset, mixed $data): void
    {
        if (!is_string($offset)) {
            return;
        }

        /**
         * @psalm-suppress MissingThrowsDocblock $offset is definitely a valid key so set() won't throw
         * InvalidArgumentException
         */
        $this->set($offset, $data);
    }

    /**
     * Unset the value for an offset.
     *
     * This is part of the ArrayAccess interface.
     *
     * @param mixed $offset The offset to unset.
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!is_string($offset)) {
            return;
        }

        /**
         * @psalm-suppress MissingThrowsDocblock $offset is definitely a valid key so remove() won't throw
         * InvalidArgumentException
         */
        $this->remove($offset);
    }
}
