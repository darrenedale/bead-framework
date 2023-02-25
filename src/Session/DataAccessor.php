<?php

namespace Bead\Session;

use ArrayAccess;

/**
 * Interface for accessing session data.
 */
interface DataAccessor extends ArrayAccess
{
    /**
     * Check whether a key is set in the session.
     *
     * @param string $key The key
     *
     * @return bool `true` if the session has data for the key, `false` otherwise.
     */
    public function has(string $key): bool;

    /**
     * Fetch the data for a key in the session.
     * @param string $key The key of the data to fetch.
     * @param mixed $default The default value, if any, to return if the key is not set. Defaults to `null`.
     *
     * @return mixed|null The value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Extract the data for one or more keys from the session.
     *
     * The keys extracted will be removed from the session data.
     *
     * @param $keys string|array<string> The key(s) to extract.
     *
     * @return mixed|array<string,mixed> The extracted data.
     */
    public function extract(string|array $keys): mixed;

    /**
     * Fetch all the session data.
     *
     * @return array<string, mixed> The session data.
     */
    public function all(): array;

    /**
     * Set one or more values in the session.
     *
     * @param string|array<string, mixed> $keyOrData The key to set, or an array of key-value pairs to set.
     * @param mixed|null $data The data to set if `$keyOrData` is a string key. Ignored otherwise.
     */
    public function set(string|array $keyOrData, mixed $data = null): void;

    /**
     * Push an item onto the end of an array stored in the session.
     *
     * @param string $key The key that contains the array.
     * @param mixed $data The item to push onto the end of it.
     */
    public function push(string $key, mixed $data): void;

    /**
     * Push a set of items onto the end of an array stored in the session.
     *
     * @param string $key The key that contains the array.
     * @param array $data The items to push onto the end of it.
     */
    public function pushAll(string $key, array $data): void;

    /**
     * Pop one or more items off the end of an array stored in the session.
     *
     * If one item is popped, it is returned; otherwise an array of the items poppped is returned.
     *
     * @param string $key The key that contains the array.
     * @param int $n The optional number of items. Defaults to 1.
     *
     * @return mixed|array The popped item(s).
     */
    public function pop(string $key, int $n = 1): mixed;

    /**
     * Get a session data accessor that prefixes all keys with a given string.
     *
     * If you're working with lots of session data that has a common prefix, this can help make the code less cluttered
     * and avoid boilerplate.
     *
     * @param string $prefix The prefix to apply.
     *
     * @return DataAccessor
     */
    public function prefixed(string $prefix): DataAccessor;

    /**
     * Set one or more values in the session.
     *
     * The value is set for the next request only, after which it is automatically removed.
     *
     * @param string|array<string, mixed> $keyOrData The key to set, or an array of key-value pairs to set.
     * @param mixed|null $data The data to set if `$keyOrData` is a string key. Ignored otherwise.
     */
    public function transientSet(string|array $keyOrData, $data = null): void;

    /**
     * Remove one or more keys from the session data.
     *
     * @param array<string>|string $keys The key or keys to remove.
     */
    public function remove(string|array $keys): void;
}
