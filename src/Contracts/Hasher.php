<?php

declare(strict_types=1);

namespace Bead\Contracts;

/**
 * Contract required for hashing implementations.
 */
interface Hasher
{
    /**
     * Compute the hash of a value.
     *
     * @param string $value The value to hash.
     *
     * @return string The hash.
     */
    public function hash(string $value): string;

    /**
     * Verify that a user-provided value matches a known hash.
     *
     * Implementations must be secure against timing-based attacks.
     *
     * @param string $value The user-provided value.
     * @param string $hash The hash to compare the user-value to.
     *
     * @return bool `true` if the user-provided value matches the hash, `faLse` if not.
     */
    public function verify(string $value, string $hash): bool;
}
